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
use Psr\Log\LoggerInterface;

class ImapLoader {

    const SUBJECT_PREFIX = "Majordomo results: " . MajordomoCommands::MAGIC . " ";

    private $AppName;
    private $imap = NULL;
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

    function __construct($AppName, Settings $settings, InboundService $inboundService, LoggerInterface $logger) {
        $this->imapSettings = $settings->getImapSettings();
        $this->logger = $logger;
        $this->inboundService = $inboundService;
        $this->AppName = $AppName;
    }

    public function isEnabled() {
        return $this->imapSettings !== NULL && !empty($this->imapSettings->server);
    }

    public function getImap() {
        if ($this->imap === NULL) {
            $this->imap = $this->imap = imap_open(
                '{' . $this->imapSettings->server . '}' . $this->imapSettings->inbox,
                $this->imapSettings->user, $this->imapSettings->password
            );
        }

        return $this->imap;
    }

    function __destruct() {
        if ($this->imap !== NULL && $this->imap !== FALSE) {
            imap_close($this->imap);
        }
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
        foreach ([ $this->imapSettings->archive, $this->imapSettings->errors ] as $required) {
            if (!in_array($required, $folders)) {
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
            }
        }

        if ($expunge) {
            imap_expunge($imap);
        }
    }
    
}
