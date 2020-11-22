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

namespace OCA\Majordomo\Job;


use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Service\OutboundService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class WritePendingChangesJob extends TimedJob {

    /**
     * @var MailingListMapper
     */
    private $mailingListMapper;
    /**
     * @var OutboundService
     */
    private $outboundService;
    /**
     * @var LoggerInterface
     */
    private $logger;
    private $AppName;

    public function __construct(ITimeFactory $time, MailingListMapper $mailingListMapper, OutboundService $outboundService, LoggerInterface $logger, $AppName) {
        parent::__construct($time);

        // Run once an hour
        parent::setInterval(3600);
        $this->mailingListMapper = $mailingListMapper;
        $this->outboundService = $outboundService;
        $this->logger = $logger;
        $this->AppName = $AppName;
    }

    /**
     * @inheritDoc
     */
    protected function run($argument) {
        $numModified = 0;
        $mailingLists = $this->mailingListMapper->findAllIdsBySyncActiveIsTrue();
        foreach ($mailingLists as $ml) {
            $this->logger->debug("Automatically syncing mailing list id {$ml->getId()}", [ 'app' => $this->AppName ]);
            if ($this->outboundService->updateMailingListMembership($ml->getId())["id"] !== null) {
                $numModified++;
            }
        }
        if ($numModified > 0) {
            $this->logger->info("Applied changes to $numModified of " . count($mailingLists) . " mailing lists during auto-sync", ['app' => $this->AppName]);
        } else {
            $this->logger->debug("None of " . count($mailingLists) . " mailing lists was modified during auto-sync", ['app' => $this->AppName]);
        }
    }

}
