<?php


namespace OCA\Majordomo\Job;


use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Service\OutboundService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;

class ReadCurrentListMembersJob extends TimedJob {

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

        // Run once a week
        parent::setInterval(7 * 24 * 3600);
        $this->mailingListMapper = $mailingListMapper;
        $this->outboundService = $outboundService;
        $this->logger = $logger;
        $this->AppName = $AppName;
    }

    /**
     * @inheritDoc
     */
    protected function run($argument) {
        $mailingLists = $this->mailingListMapper->findAllIdsBySyncActiveIsTrue();
        foreach ($mailingLists as $ml) {
            $this->logger->info("Refreshing members of mailing list id {$ml->getId()}", [ 'app' => $this->AppName ]);
            $this->outboundService->retrieveCurrentMembers($ml->getId(), false);
        }
        $this->logger->info("Refreshed members of " . count($mailingLists) . " mailing lists", ['app' => $this->AppName]);
    }

}
