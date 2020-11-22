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

    public function getMemberEmails($id) {
        $emails = [];
        $exclusions = [];

        foreach ($this->memberMapper->findAllByListId($id) as $member) {
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
                return [ strtolower($member->getReference()) ];
                break;
            case Member::TYPE_USER:
                $user = $this->userManager->get($member->getReference());
                if ($user !== null) {
                    return [ strtolower($user->getEMailAddress()) ];
                } else {
                    $this->logger->error("Unknown user {$member->getReference()} for member id {$member->getId()}", ["app" => $this->AppName]);
                }
                break;
            case Member::TYPE_GROUP:
                $group = $this->groupManager->get($member->getReference());
                if ($group !== null) {
                    return array_values(array_map(function (IUser $user) {
                        return strtolower($user->getEMailAddress());
                    }, $group->getUsers()));
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