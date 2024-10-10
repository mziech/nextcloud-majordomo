<?php
/**
 * @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Majordomo\Service;


use OC\ForbiddenException;
use OCA\Majordomo\Db\CurrentEmailMapper;
use OCA\Majordomo\Db\MailingList;
use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Db\Member;
use OCA\Majordomo\Db\MemberMapper;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MailingListService {

    private LoggerInterface $logger;
    /**
     * @var MailingListMapper
     */
    private $mailingListMapper;
    /**
     * @var MemberMapper
     */
    private $memberMapper;
    /**
     * @var MemberResolver
     */
    private $memberResolver;
    /**
     * @var IDBConnection
     */
    private $db;
    /**
     * @var IUserManager
     */
    private $userManager;
    /**
     * @var CurrentEmailMapper
     */
    private $currentEmailMapper;
    private IGroupManager $groupManager;
    private $UserId;

    public function __construct(
        LoggerInterface $logger,
        MailingListMapper $mailingListMapper,
        MemberMapper $memberMapper,
        MemberResolver $memberResolver,
        CurrentEmailMapper $currentEmailMapper,
        IDBConnection $db,
        IUserManager $userManager,
        IGroupManager $groupManager,
        $UserId
    ) {
        $this->mailingListMapper = $mailingListMapper;
        $this->memberMapper = $memberMapper;
        $this->memberResolver = $memberResolver;
        $this->currentEmailMapper = $currentEmailMapper;
        $this->db = $db;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->UserId = $UserId;
        $this->logger = $logger;
    }

    public function read($id): array {
        if ($id == "new") {
            $dto = [
                "access" => MailingListAccess::forNew()
            ];
            self::mapEntityToDto(MailingList::create(), $dto);
            return $dto;
        }

        $ml = $this->mailingListMapper->find($id);
        $access = $this->getListAccess($ml);
        if (!$access->canView) {
            $this->logger->error("Rejecting read access to mailing list ID $id to unauthorized user $this->UserId ($access)");
            throw new ForbiddenException();
        }

        $dto = ["access" => $access, "members" => []];
        if ($access->canAdmin) {
            self::mapEntityToDto($ml, $dto, ["password"]);
        } else {
            self::mapEntityToDto($ml, $dto, [], ["id", "resendAddress", "title"]);
        }

        if ($access->canEditMembers) {
            foreach ($this->memberMapper->findAllByListId($ml->id) as $member) {
                $memberDto = [];
                self::mapEntityToDto($member, $memberDto, ["id", "listId"]);
                $dto["members"][] = $memberDto;
            }
        }

        return $dto;
    }

    public function list(): array {
        $admin = $this->groupManager->isAdmin($this->UserId);
        if ($admin) {
            $mls = $this->mailingListMapper->findAll();
        } else {
            $mls = $this->mailingListMapper->findAllByAccessLevel(
                $this->memberResolver->resolveListsByMember($this->UserId)
            );
        }

        return array_map(function (MailingList $ml) use ($admin) {
            $dto = [];
            return self::mapEntityToDto($ml, $dto, [], $admin ? ["id", "title", "syncActive"] : ["id", "title"]);
        }, $mls);
    }

    public function canEditMembers(): bool {
        $admin = $this->groupManager->isAdmin($this->UserId);
        if ($admin) {
            return true;
        } else {
            return !empty($this->mailingListMapper->findAllByAccessLevel(
                $this->memberResolver->resolveListsByMember($this->UserId),
                MailingListAccess::MEMBER_EDIT_ACCESS
            ));
        }
    }

    public function canModerate(): bool {
        $admin = $this->groupManager->isAdmin($this->UserId);
        if ($admin) {
            return true;
        } else {
            $accessLevels = $this->memberResolver->resolveListsByMember($this->UserId);
            return !empty($accessLevels) && max(array_values($accessLevels)) <= MailingList::ACCESS_MODERATORS;
        }
    }

    public function create($post) {
        if (!$this->groupManager->isAdmin($this->UserId)) {
            throw new ForbiddenException("List creation is not allowed");
        }

        $this->db->beginTransaction();

        $ml = new MailingList();
        self::mapPostToEntity($post, $ml);
        $newId = $this->mailingListMapper->insert($ml)->id;
        $this->updateMembers($newId, $post);

        $this->db->commit();
        return $newId;
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    public function update($id, $post) {
        $this->db->beginTransaction();

        $ml = $this->mailingListMapper->find($id);
        $access = $this->getListAccess($ml);
        if (!$access->canEditMembers) {
            $this->db->rollBack();
            $this->logger->error("Received update for mailing list ID $id from unauthorized user $this->UserId ($access)");
            throw new ForbiddenException();
        }

        if ($access->canAdmin) {
            self::mapPostToEntity($post, $ml);
        }
        $this->mailingListMapper->update($ml);
        $this->updateMembers($id, $post, $access->editableTypes);

        $this->db->commit();
    }

    public function getListStatus($id) {
        $access = $this->getListAccessByListId($id);
        if (!$access->canView) {
            $this->logger->error("Rejecting list status access of list ID $id for user ID $this->UserId ($access)");
            throw new ForbiddenException();
        }

        $ml = $this->mailingListMapper->find($id);
        $expected = $this->memberResolver->getMemberEmails($id);
        $actual = $ml->manager != '' ? $this->currentEmailMapper->findEmailsByListId($id) : $expected;
        $toAdd = array_diff($expected, $actual);
        $toDelete = array_diff($actual, $expected);

        if (!$access->canListMembers) {
            $user = $this->userManager->get($this->UserId);
            $email = $user->getEMailAddress();
            if (!in_array($email, $expected) && !in_array($email, $actual)) {
                return [];
            }

            return [
                [
                    "uid" => $user->getUID(),
                    "displayName" => $user->getDisplayName(),
                    "email" => $email,
                    'status' => in_array($email, $toAdd)
                        ? 'ADD'
                        : (in_array($email, $toDelete)
                            ? 'DELETE'
                            : 'UNCHANGED')
                ]
            ];
        }

        $status = [];
        foreach (array_unique(array_merge(array_values($expected), array_values($actual))) as $email) {
            $users = $this->userManager->getByEmail($email);
            $status[] = [
                'uid' => count($users) == 1 ? $users[0]->getUID() : null,
                'displayName' => count($users) == 1 ? $users[0]->getDisplayName() : null,
                'email' => $email,
                'status' => in_array($email, $toAdd)
                    ? 'ADD'
                    : (in_array($email, $toDelete)
                        ? 'DELETE'
                        : 'UNCHANGED')
            ];
        }
        usort($status, function ($a, $b) {
            if ($a['status'] !== $b['status']) {
                return strcmp($a['status'], $b['status']);
            } else if ($a['displayName'] !== $b['displayName']) {
                return strcasecmp($a['displayName'], $b['displayName']);
            } else if ($a['email'] !== $b['email']) {
                return strcmp($a['email'], $b['email']);
            } else if ($a['uid'] !== $b['uid']) {
                return strcasecmp($a['uid'], $b['uid']);
            }
            return 0;
        });
        return $status;
    }

    private function getListAccessLevel($listId): int {
        if ($this->groupManager->isAdmin($this->UserId)) {
            // can access even inaccessible things
            return MailingList::ACCESS_NONE;
        }

        $access = $this->memberResolver->resolveListsByMember($this->UserId, $listId);
        if (!isset($access[$listId])) {
            return MailingList::ACCESS_OPEN;
        }

        return $access[$listId];
    }

    private function getListAccess(MailingList $ml): MailingListAccess {
        return new MailingListAccess($ml, $this->getListAccessLevel($ml->id));
    }

    public function getListAccessByListId($listId): MailingListAccess {
        return $this->getListAccess($this->mailingListMapper->find($listId));
    }

    private function updateMembers($listId, array $post, array $editableTypes = NULL) {
        if (!isset($post["members"]) || !is_array($post["members"])) {
            return;
        }

        $oldMembersMap = [];
        foreach ($this->memberMapper->findAllByListId($listId) as $oldMember) {
            $oldMembersMap[$oldMember->type."=".$oldMember->reference] = $oldMember;
        }

        $newMemberMap = [];
        foreach ($post["members"] as $newMember) {
            $newMemberMap[$newMember["type"]."=".$newMember["reference"]] = $newMember;
        }

        foreach (array_diff(array_keys($oldMembersMap), array_keys($newMemberMap)) as $toDelete) {
            if ($editableTypes === NULL || in_array($oldMembersMap[$toDelete]->type, $editableTypes)) {
                $this->memberMapper->delete($oldMembersMap[$toDelete]);
            } else {
                $this->logger->error("Rejecting to delete member $toDelete to list ID $listId by user ID $this->UserId: " .
                    implode(", ", $editableTypes));
            }
        }

        foreach (array_diff(array_keys($newMemberMap), array_keys($oldMembersMap)) as $toCreate) {
            $newMember = new Member();
            self::mapPostToEntity($newMemberMap[$toCreate], $newMember);
            $newMember->setListId($listId);
            if ($editableTypes === NULL || in_array($newMember->type, $editableTypes)) {
                $this->memberMapper->insert($newMember);
            } else {
                $this->logger->error("Rejecting to add member $toCreate to list ID $listId by user ID $this->UserId: " .
                    implode(", ", $editableTypes));
            }
        }
    }

    private static function mapPostToEntity(array $post, Entity $entity, array $exclude = ["id"]) {
        $vars = get_object_vars($entity);
        foreach ($post as $key => $value) {
            if (array_key_exists($key, $vars) && !in_array($key, $exclude)) {
                $setter = "set" . ucfirst($key);
                $entity->$setter($value);
            }
        }
    }

    private static function mapEntityToDto(Entity $entity, array &$dto = [], array $exclude = [], array $include = null) {
        $vars = get_object_vars($entity);
        foreach ($vars as $key => $value) {
            if (!in_array($key, $exclude) && ($include === null || in_array($key, $include))) {
                $dto[$key] = $value;
            }
        }
        return $dto;
    }

}
