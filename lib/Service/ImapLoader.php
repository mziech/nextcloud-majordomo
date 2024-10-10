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
use OCA\Majordomo\Db\MailingList;
use Psr\Log\LoggerInterface;

class ImapLoader {

    const SUBJECT_PREFIX = "Majordomo results: " . MajordomoCommands::MAGIC . " ";
    const BOUNCE_PATTERN = "/BOUNCE +([^@]*@[^:]*): +Non-member submission from \[([^]]*)]/";

    private $AppName;
    private $imap = [];
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

    function __construct(
        $AppName,
        Settings $settings,
        InboundService $inboundService,
        ResendService $resendService,
        LoggerInterface $logger
    ) {
        $this->imapSettings = $settings->getImapSettings();
        $this->logger = $logger;
        $this->inboundService = $inboundService;
        $this->AppName = $AppName;
        $this->resendService = $resendService;
    }

    public function isEnabled() {
        return $this->imapSettings !== NULL && !empty($this->imapSettings->server);
    }

    public function getImap($folder = NULL) {
        if ($folder == NULL) {
            $folder = $this->imapSettings->inbox;
        }

        if (!isset($this->imap[$folder])) {
            $mailbox = '{' . $this->imapSettings->server . '}' . $folder;
            $this->logger->debug("Opening IMAP connection: $mailbox");
            $imap = imap_open(
                $mailbox, $this->imapSettings->user, $this->imapSettings->password
            );
            if ($imap !== false) {
                $this->imap[$folder] = $imap;
            } else {
                $this->logger->error("Failed to open IMAP connection to $mailbox: " . imap_last_error());
                return false;
            }
        }

        return $this->imap[$folder];
    }

    function __destruct() {
        foreach (array_values($this->imap) as $imap) {
            imap_close($imap);
        }
        $this->imap = [];
    }

    public function test() {
        $imap = $this->getImap();
        if (!$imap) {
            throw new \RuntimeException("IMAP connection failed: " . imap_last_error());
        }

        return [
            "folders" => $this->getFolders()
        ];
    }

    private function getFolders() {
        $imap = $this->getImap();
        $ref = '{' . $this->imapSettings->server . '}';
        return array_map(function ($folder) use ($ref) {
            return strpos($folder, $ref) === 0 ? substr($folder, strlen($ref)) : $folder;
        }, imap_list($imap, $ref, '*'));
    }

    private function ensureFoldersExist() {
        $imap = $this->getImap();
        $ref = '{' . $this->imapSettings->server . '}';
        $folders = $this->getFolders();
        foreach ([ $this->imapSettings->archive, $this->imapSettings->errors, $this->imapSettings->bounces ] as $required) {
            if ($required !== NULL && !in_array($required, $folders)) {
                imap_createmailbox($imap, $ref . $required);
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
    
    private function fetchMails() {
        $imap = $this->getImap();
        $out = array();
        $this->ensureFoldersExist();
        $nrs = imap_sort($imap, SORTARRIVAL, 1);
        if ($nrs !== false) {
            $mails = array();
            foreach (imap_fetch_overview($imap, join(",", $nrs)) as $mail) {
                $mails[$mail->msgno] = $mail;
            }
            foreach ($nrs as $nr) {
                $out[] = $mails[$nr];
            }
        }
        return $out;
    }
    
    public function processMails() {
        $imap = $this->getImap();
        $expunge = false;
        foreach ($this->fetchMails() as $mail) {
            $from = strtolower($mail->from);
            $this->logger->debug("Checking inbound mail from {$mail->from} with subject '{$mail->subject}'");
            if (strncasecmp($mail->subject, self::SUBJECT_PREFIX, strlen(self::SUBJECT_PREFIX)) == 0) {
                try {
                    $requestId = substr($mail->subject, strlen(self::SUBJECT_PREFIX));
                    $results = $this->parseMailBody(imap_body($imap, $mail->msgno));
                    $this->inboundService->handleResult($requestId, $results, $from);
                    imap_mail_move($imap, $mail->msgno, $this->imapSettings->archive);
                    $this->logger->info("Processed mail {$mail->msgno} '{$mail->subject}' from {$mail->from}", [ "app" => $this->AppName ]);
                } catch (\Exception $e) {
                    $this->logger->error("Failed to process mail {$mail->msgno} '{$mail->subject}' from {$mail->from}", [
                        "app" => $this->AppName,
                        "exception" => $e
                    ]);
                    imap_mail_move($imap, $mail->msgno, $this->imapSettings->errors);
                }
                $expunge = true;
            } else if (!empty($this->imapSettings->bounces) && preg_match(self::BOUNCE_PATTERN, $mail->subject)) {
                imap_mail_move($imap, $mail->msgno, $this->imapSettings->bounces);
                $this->logger->info("Moved bounce {$mail->msgno} '{$mail->subject}' from {$mail->from}", [ "app" => $this->AppName ]);
                $expunge = true;
            } else if ($this->resendService->isEnabled()) {
                $header = imap_headerinfo($imap, $mail->msgno);
                if (isset($header->to)) {
                    $this->logger->debug("header: " . print_r($header, true));
                    $to = array_map(function ($t) {
                        return strtolower($t->mailbox . '@' . $t->host);
                    }, $header->to);

                    $rawHeader = null;
                    $body = null;
                    foreach ($this->resendService->getLists($to) as $ml) {
                        if ($body === null) {
                            $rawHeader = imap_fetchheader($imap, $mail->msgno);
                            $body = imap_body($imap, $mail->msgno);
                        }
                        $this->logger->info("Resending mail from $from to " . $ml->title, ["app" => $this->AppName]);
                        $this->resendService->bounceOrResend($ml, $from, $rawHeader, $body);
                    }
                    if ($body !== null) {
                        imap_mail_move($imap, $mail->msgno, $this->imapSettings->archive);
                        $expunge = true;
                    }
                }
            }
        }

        if ($expunge) {
            imap_expunge($imap);
        }
    }

    public function getBounces() {
        $imap = $this->getImap($this->imapSettings->bounces);
        $out = array();
        $this->ensureFoldersExist();
        $nrs = imap_sort($imap, SORTARRIVAL, 1);
        $bouncerMapping = $this->inboundService->getBouncerMapping();
        if ($nrs !== false) {
            $mails = [];
            foreach (imap_fetch_overview($imap, join(",", $nrs)) as $mail) {
                $m = [];
                if (isset($mail->subject) && preg_match(self::BOUNCE_PATTERN, $mail->subject, $m)) {
                    if (!array_key_exists($m[1], $bouncerMapping)) {
                        continue;
                    }

                    if ($mail->deleted) {
                        continue;
                    }

                    $ml = $bouncerMapping[$m[1]];
                    $mails[$mail->msgno] = [
                        "list_id" => $ml->id,
                        "list_title" => $ml->title,
                        "list_address" => $m[1],
                        "from" => $m[2],
                        "date" => $mail->date,
                        "mid" => $mail->message_id,
                        "uid" => $mail->uid,
                    ];
                }
            }
            foreach ($nrs as $nr) {
                if (isset($mails[$nr])) {
                    $out[] = $mails[$nr];
                }
            }
        }
        return $out;
    }

    public function getBounce($uid) {
        $imap = $this->getImap($this->imapSettings->bounces);
        $ml = $this->assertBounce($uid);
        return [
            "ml" => $ml,
            "body" => imap_body($imap, $uid, FT_UID),
        ];
    }

    public function deleteBounce($uid) {
        $imap = $this->getImap($this->imapSettings->bounces);
        $this->assertBounce($uid);
        imap_mail_move($imap, $uid, $this->imapSettings->archive, FT_UID);
    }

    protected function assertBounce($uid): MailingList {
        $imap = $this->getImap($this->imapSettings->bounces);
        $mail = imap_fetch_overview($imap, "$uid", FT_UID)[0];
        $m = [];
        if (preg_match(self::BOUNCE_PATTERN, $mail->subject, $m)) {
            return $this->inboundService->getListByBounceAddress($m[1]);
        }
        throw new \RuntimeException("The mail $uid is not a bounced message");
    }
}
