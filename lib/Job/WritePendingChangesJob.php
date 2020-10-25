<?php


namespace OCA\Majordomo\Job;


use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Service\OutboundService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;

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
     * @var ILogger
     */
    private $logger;
    private $AppName;

    public function __construct(ITimeFactory $time, MailingListMapper $mailingListMapper, OutboundService $outboundService, ILogger $logger, $AppName) {
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
