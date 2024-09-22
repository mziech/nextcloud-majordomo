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


use OCA\Majordomo\Db\MailingList;
use OCA\Majordomo\Db\Member;
use OCA\Majordomo\Db\MemberMapper;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MemberResolver {

    private $AppName;
    /**
     * @var IUserManager
     */
    private $userManager;
    /**
     * @var IGroupManager
     */
    private $groupManager;
    /**
     * @var MemberMapper
     */
    private $memberMapper;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        $AppName,
        LoggerInterface $logger,
        IUserManager $userManager,
        IGroupManager $groupManager,
        MemberMapper $memberMapper
    ) {
        $this->AppName = $AppName;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->memberMapper = $memberMapper;
    }

    /**
     * Find all lists the given user has access permissions to
     *
     * @param $userId the user ID
     * @param $listId an optional list ID if only the access level of a specific list is relevant
     * @return array[int, int] a map of listId to access level for that list
     */
    public function resolveListsByMember($userId, $listId = NULL) {
        $groups = $this->groupManager->getUserGroups($this->userManager->get($userId));
        $groupIds = array_map(function ($group) {
            return $group->getGID();
        }, $groups);

        $listAccess = [];
        $excludedListAccess = [];
        foreach ($this->memberMapper->findAllByUserAndGroups($userId, $groupIds, $listId) as $member) {
            if (in_array($member->type, Member::TYPES_EXCLUDE)) {
                $excludedListAccess[$member->listId] = true;
            } else if (in_array($member->type, Member::TYPES_ADMIN)) {
                $listAccess[$member->listId] = max($listAccess[$member->listId] ?? 0, MailingList::ACCESS_ADMIN);
            } else if (in_array($member->type, Member::TYPES_MODERATOR)) {
                $listAccess[$member->listId] = max($listAccess[$member->listId] ?? 0, MailingList::ACCESS_MODERATORS);
            } else if (in_array($member->type, Member::TYPES_RECIPIENT)) {
                $listAccess[$member->listId] = max($listAccess[$member->listId] ?? 0, MailingList::ACCESS_MEMBERS);
            } else {
                $this->logger->error("Cannot map list ID $member->listId membership type $member->type to access level: $member->reference");
            }
        }

        foreach (array_keys($excludedListAccess) as $excluded) {
            unset($listAccess[$excluded]);
        }
        $this->logger->info("List access levels from user ID $userId with group IDs " .
            implode(", ", $groupIds) . ": " . print_r($listAccess, true));

        return $listAccess;
    }

    public function getMemberEmails($id, $types = Member::TYPES_RECIPIENT) {
        $emails = [];
        $exclusions = [];

        foreach ($this->memberMapper->findAllByListIdAndTypes($id, $types) as $member) {
            if ($member->getType() === Member::TYPE_EXCLUDE
                || $member->getType() === Member::TYPE_EXCLUDE_USER
                || $member->getType() === Member::TYPE_EXCLUDE_GROUP) {
                $exclusions = array_merge($exclusions, $this->resolveEmailsForMember($member));
            } else {
                $emails = array_merge($emails, $this->resolveEmailsForMember($member));
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    array_diff($emails, $exclusions),
                    function ($email) { return $email !== null && $email !== ""; }
                )
            )
        );
    }

    private function resolveEmailsForMember(Member $member): array {
        switch ($member->getType()) {
            case Member::TYPE_EXTRA:
            case Member::TYPE_MODERATOR_EXTRA:
            case Member::TYPE_EXCLUDE:
                return [ strtolower($member->getReference()) ];

            case Member::TYPE_USER:
            case Member::TYPE_MODERATOR_USER:
            case Member::TYPE_ADMIN_USER:
            case Member::TYPE_EXCLUDE_USER:
                $user = $this->userManager->get($member->getReference());
                if ($user !== null && $user->isEnabled()) {
                    return [ strtolower($user->getEMailAddress()) ];
                } else if ($user === null) {
                    $this->logger->error("Unknown user {$member->getReference()} for member id {$member->getId()}", ["app" => $this->AppName]);
                }
                break;

            case Member::TYPE_GROUP:
            case Member::TYPE_MODERATOR_GROUP:
            case Member::TYPE_ADMIN_GROUP:
            case Member::TYPE_EXCLUDE_GROUP:
                $group = $this->groupManager->get($member->getReference());
                if ($group !== null) {
                    return array_values(array_map(function (IUser $user) {
                        return strtolower($user->getEMailAddress());
                    }, array_filter($group->getUsers(), function (IUser $user) {
                        return $user->isEnabled();
                    })));
                } else {
                    $this->logger->error("Unknown group {$member->getReference()} for member id {$member->getId()}", ["app" => $this->AppName]);
                }
                break;

            default:
                $this->logger->error("Unknown type {$member->type} for member id {$member->id}", ["app" => $this->AppName]);
        }
        return [];
    }

}