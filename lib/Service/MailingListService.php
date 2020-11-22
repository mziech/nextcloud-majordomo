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


use OCA\Majordomo\Db\CurrentEmailMapper;
use OCA\Majordomo\Db\MailingList;
use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Db\Member;
use OCA\Majordomo\Db\MemberMapper;
use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;
use OCP\IUserManager;

class MailingListService {

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

    public function __construct(
        MailingListMapper $mailingListMapper,
        MemberMapper $memberMapper,
        MemberResolver $memberResolver,
        CurrentEmailMapper $currentEmailMapper,
        IDBConnection $db,
        IUserManager $userManager
    ) {
        $this->mailingListMapper = $mailingListMapper;
        $this->memberMapper = $memberMapper;
        $this->memberResolver = $memberResolver;
        $this->currentEmailMapper = $currentEmailMapper;
        $this->db = $db;
        $this->userManager = $userManager;
    }

    public function read($id) {
        $dto = ["members" => []];
        $ml = $this->mailingListMapper->find($id);
        self::mapEntityToDto($ml, $dto, ["password"]);
        foreach ($this->memberMapper->findAllByListId($ml->id) as $member) {
            $memberDto = [];
            self::mapEntityToDto($member, $memberDto, ["id", "listId"]);
            $dto["members"][] = $memberDto;
        }
        return $dto;
    }

    public function create($post) {
        $this->db->beginTransaction();

        $ml = new MailingList();
        self::mapPostToEntity($post, $ml);
        $newId = $this->mailingListMapper->insert($ml)->id;
        $this->updateMembers($newId, $post);

        $this->db->commit();
        return $newId;
    }

    public function update($id, $post) {
        $this->db->beginTransaction();

        $ml = $this->mailingListMapper->find($id);
        self::mapPostToEntity($post, $ml);
        $this->mailingListMapper->update($ml);
        $this->updateMembers($id, $post);

        $this->db->commit();
    }

    public function getListStatus($id) {
        $expected = $this->memberResolver->getMemberEmails($id);
        $actual = $this->currentEmailMapper->findEmailsByListId($id);
        $toAdd = array_diff($expected, $actual);
        $toDelete = array_diff($actual, $expected);

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

    /**
     * @param $listId
     * @param array|Member $oldMembers
     * @param array $post
     */
    private function updateMembers($listId, array $post) {
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
            $this->memberMapper->delete($oldMembersMap[$toDelete]);
        }

        foreach (array_diff(array_keys($newMemberMap), array_keys($oldMembersMap)) as $toCreate) {
            $newMember = new Member();
            self::mapPostToEntity($newMemberMap[$toCreate], $newMember);
            $newMember->setListId($listId);
            $this->memberMapper->insert($newMember);
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

    private static function mapEntityToDto(Entity $entity, array &$dto, array $exclude = []) {
        $vars = get_object_vars($entity);
        foreach ($vars as $key => $value) {
            if (!in_array($key, $exclude)) {
                $dto[$key] = $value;
            }
        }
    }

}