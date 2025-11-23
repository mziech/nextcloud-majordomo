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

use DateTime;
use DirectoryTree\ImapEngine\Address;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\MessageInterface;
use OCA\Majordomo\Db\MailingList;
use OCP\IDateTimeFormatter;
use Psr\Log\LoggerInterface;

class ImapLoader {

    const SUBJECT_PREFIX = "Majordomo results: " . MajordomoCommands::MAGIC . " ";
    const BOUNCE_PATTERN = "/BOUNCE +([^@]*@[^:]*): +Non-member submission from \[([^]]*)]/";

    private $AppName;
    private Mailbox|NULL $imap = NULL;
    private $imapSettings;
    /**
     * @var DateTime
     */
    var $date;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var InboundService
     */
    private $inboundService;
    /**
     * @var ResendService
     */
    private $resendService;
    private IDateTimeFormatter $dateTimeFormatter;

    function __construct(
        $AppName,
        Settings $settings,
        InboundService $inboundService,
        ResendService $resendService,
        IDateTimeFormatter $dateTimeFormatter,
        LoggerInterface $logger
    ) {
        $this->imapSettings = $settings->getImapSettings();
        $this->logger = $logger;
        $this->inboundService = $inboundService;
        $this->AppName = $AppName;
        $this->resendService = $resendService;
        $this->dateTimeFormatter = $dateTimeFormatter;
    }

    public function isEnabled() {
        return $this->imapSettings !== NULL && !empty($this->imapSettings->server);
    }

    public function getImap($folder = NULL): Mailbox {
        if ($folder == NULL) {
            $folder = $this->imapSettings->inbox;
        }

        if ($this->imap == NULL) {
            $mailbox = '{' . $this->imapSettings->server . '}' . $folder;
            $this->logger->debug("Opening IMAP connection: $mailbox");
            $serverParts = explode("/", strtolower($this->imapSettings->server));
            $hostParts = explode(":", array_shift($serverParts));
            $config = [
                "host" => $hostParts[0],
                "username" => $this->imapSettings->user,
                "password" => $this->imapSettings->password,
            ];

            if (isset($hostParts[1])) {
                $config["port"] = $hostParts[1];
            }

            if (in_array("tls", $serverParts)) {
                $config["encryption"] = "starttls";
            } elseif (in_array("ssl", $serverParts)) {
                $config["encryption"] = "ssl";
            } else {
                $config["encryption"] = null;
            }

            if (in_array("novalidate-cert", $serverParts)) {
                $config["validate_cert"] = false;
            }

            $this->imap = new Mailbox($config);
            $this->imap->connect();
        }

        return $this->imap;
    }

    function __destruct() {
        if ($this->imap != NULL) {
            $this->imap->disconnect();
        }
        $this->imap = NULL;
    }

    public function test() {
        return [
            "folders" => $this->getFolders()
        ];
    }

    private function getFolders(): array {
        $imap = $this->getImap();
        return $imap->folders()->get()->map(function (FolderInterface $folder): string {
            return $folder->name();
        })->toArray();
    }

    private function ensureFoldersExist() {
        $imap = $this->getImap();
        $folders = $this->getFolders();
        foreach ([ $this->imapSettings->archive, $this->imapSettings->errors, $this->imapSettings->bounces ] as $required) {
            if ($required !== NULL && !in_array($required, $folders)) {
                $imap->folders()->create($required);
            }
        }
    }

    private function parseMailBody($body) {
        $results = array();
        /** @var null|MajordomoResult $lastResult */
        $lastResult = NULL;
        
        foreach (explode("\n", $body) as $rawLine) {
            $nextResult = MajordomoResult::fromLine(trim($rawLine));
            if ($nextResult !== NULL) {
                $results[] = $nextResult;
                $lastResult = $nextResult;
            } elseif ($lastResult !== NULL) {
                $lastResult->processLine($rawLine);
            }
        }

        return $results;
    }

    /**
     * @return MessageInterface[]
     */
    private function fetchMails(): array {
        $imap = $this->getImap();
        $this->ensureFoldersExist();
        $folder = $imap->folders()->firstOrCreate($this->imapSettings->inbox);
        return $folder->messages()->all()->setFetchOrderDesc()->withBody()->withHeaders()->get()->all();
    }
    
    public function processMails() {
        $imap = $this->getImap();
        $expunge = false;
        foreach ($this->fetchMails() as $mail) {
            $expunge = $this->processMail($mail) || $expunge;
        }

        if ($expunge) {
            $imap->folders()->firstOrCreate($this->imapSettings->inbox)->expunge();
        }
    }

    public function idle() {
        $imap = $this->getImap();
        $this->logger->info("Waiting for new messages");
        $inbox = $imap->folders()->firstOrCreate($this->imapSettings->inbox);
        $inbox->idle(function (MessageInterface $message) use ($inbox) {
            $message = $inbox->messages()->uid($message->uid())->withBody()->withHeaders()->withFlags()->first();
            if ($message !== null) {
                $this->logger->info("Got new message: {$message->subject()}");
                $this->processMail($message);
            }
        });
    }

    public function getBounces() {
        $imap = $this->getImap();
        $out = array();
        $this->ensureFoldersExist();
        /** @var MessageInterface[] $bounceMails */
        $bounceMails = $imap->folders()->firstOrCreate($this->imapSettings->bounces)
            ->messages()->all()->setFetchOrderDesc()
            ->withBody()->withHeaders()->withFlags()
            ->get()->all();
        $bouncerMapping = $this->inboundService->getBouncerMapping();
        foreach ($bounceMails as $mail) {
            $m = [];
            if ($mail->subject() != null && preg_match(self::BOUNCE_PATTERN, $mail->subject(), $m)) {
                if (!array_key_exists($m[1], $bouncerMapping)) {
                    continue;
                }

                if ($mail->hasFlag("Deleted")) {
                    continue;
                }

                $ml = $bouncerMapping[$m[1]];
                $out[] = [
                    "list_id" => $ml->id,
                    "list_title" => $ml->title,
                    "list_address" => $m[1],
                    "from" => $m[2],
                    "date" => $this->dateTimeFormatter->formatDateTime($mail->date()->toDate()),
                    "mid" => $mail->messageId(),
                    "uid" => $mail->uid(),
                ];
            }
        }
        return $out;
    }

    public function getBounce($uid) {
        $imap = $this->getImap();
        $ml = $this->assertBounce($uid);
        $this->logger->info("Bounce: " . $uid);
        $body = (string)$imap->folders()->firstOrCreate($this->imapSettings->bounces)
            ->messages()->uid($uid)
            ->withHeaders()->withBody()
            ->firstOrFail();
        $this->logger->info("Mail: ". $body);
        return [
            "ml" => $ml,
            "body" => $body,
        ];
    }

    public function deleteBounce($uid) {
        $imap = $this->getImap();
        $this->assertBounce($uid);
        $imap->folders()->firstOrCreate($this->imapSettings->bounces)
            ->messages()
            ->uid($uid)
            ->firstOrFail()
            ->move($this->imapSettings->archive);
    }

    protected function assertBounce($uid): MailingList {
        $imap = $this->getImap();
        $mail = $imap->folders()->firstOrCreate($this->imapSettings->bounces)
            ->messages()
            ->uid($uid)
            ->withHeaders()
            ->firstOrFail();
        $m = [];
        if (preg_match(self::BOUNCE_PATTERN, $mail->subject(), $m)) {
            return $this->inboundService->getListByBounceAddress($m[1]);
        }
        throw new \RuntimeException("The mail $uid is not a bounced message");
    }

    protected function processMail(MessageInterface $mail): bool {
        try {
            $expunge = false;
            $from = strtolower($mail->from()->email());
            $this->logger->debug("Checking inbound mail from {$mail->from()->email()} with subject '{$mail->subject()}'");
            if (strncasecmp($mail->subject(), self::SUBJECT_PREFIX, strlen(self::SUBJECT_PREFIX)) == 0) {
                $requestId = substr($mail->subject(), strlen(self::SUBJECT_PREFIX));
                $results = $this->parseMailBody($mail->text());
                $this->inboundService->handleResult($requestId, $results, $from);
                $mail->move($this->imapSettings->archive);
                $this->logger->info("Processed mail {$mail->messageId()} '{$mail->subject()}' from {$mail->from()->email()}", ["app" => $this->AppName]);
                $expunge = true;
            } else if (!empty($this->imapSettings->bounces) && preg_match(self::BOUNCE_PATTERN, $mail->subject())) {
                $mail->move($this->imapSettings->bounces);
                $this->logger->info("Moved bounce {$mail->messageId()} '{$mail->subject()}' from {$mail->from()->email()}", ["app" => $this->AppName]);
                $expunge = true;
            } else if ($this->resendService->isEnabled()) {
                $to = array_map(function (Address $address) {
                    return $address->email();
                }, $mail->to());
                if (!empty($to)) {
                    $resent = false;
                    foreach ($this->resendService->getLists($to) as $ml) {
                        $this->logger->info("Resending mail from $from to " . $ml->title, ["app" => $this->AppName]);
                        $this->resendService->bounceOrResend($ml, $mail);
                        $resent = true;
                    }
                    if ($resent) {
                        $mail->move($this->imapSettings->archive);
                        $expunge = true;
                    }
                }
            }
            return $expunge;
        } catch (\Exception $e) {
            try {
                $this->logger->error("Failed to process mail {$mail->messageId()} '{$mail->subject()}' from {$mail->from()->email()}", [
                    "app" => $this->AppName,
                    "exception" => $e
                ]);
            } catch (\Exception $ignored) {
                $this->logger->error("Failed to process mail $mail", [
                    "app" => $this->AppName,
                    "exception" => $e
                ]);
            }
            $mail->move($this->imapSettings->errors);
            return true;
        }
    }
}
