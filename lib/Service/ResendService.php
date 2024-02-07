<?php
/**
 * @copyright Copyright (c) 2024 Marco Ziech <marco+nc@ziech.net>
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
use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Db\Member;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;

/*
   Procmail configuration for webhook:

   :0 c:
   |curl -d token=test https://example.com/webhook/test/deadbeef
 */

/**
 * This implements a poor man's listmanager like Majordomo inside PHP.
 *
 * Inspired by: https://github.com/Distrotech/majordomo/blob/distrotech-majordomo/resend
 * RFCs:
 * https://www.ietf.org/rfc/rfc2919.txt (List-Id header)
 * https://www.ietf.org/rfc/rfc2369.txt (additional list headers)
 */
class ResendService {

    const REMOVE_HEADERS = [
        // Original majordomo rules:
        'x-confirm-reading-to',  // pegasus mail (windows)
        'disposition-notification-to',  // eudora
        'x-ack',
        'sender',
        'return-receipt-to',
        'errors-to',
        'flags',
        'priority',
        'x-pmrqc',
        'return-path',
        'encoding',  // could munge the length of the message
        // Additional rules:
        'dkim-signature',  // will be corrupted by resending
        'approved',  // accidental forwarding of legacy approved password
        'delivered-to',  // postfix mail loop
        'deliver-to',  // maybe an alias to the above
    ];

    private LoggerInterface $logger;
    private Settings $settings;
    private RawMailer $rawMailer;
    private MailingListMapper $mailingListMapper;
    private MemberResolver $memberResolver;

    /**
     * @param RawMailer $rawMailer
     * @param MailingListMapper $mailingListMapper
     */
    public function __construct(
        Settings $settings,
        LoggerInterface $logger,
        RawMailer $rawMailer,
        MailingListMapper $mailingListMapper,
        MemberResolver $memberResolver
    ) {
        $this->settings = $settings;
        $this->rawMailer = $rawMailer;
        $this->mailingListMapper = $mailingListMapper;
        $this->memberResolver = $memberResolver;
        $this->logger = $logger;
    }

    public function isEnabled(): bool {
        return $this->settings->getImapSettings()->resend && $this->rawMailer->isSupported();
    }

    /**
     * @param array<string> $to
     * @return array<MailingList>
     */
    public function getLists(array $to): array {
        return $this->mailingListMapper->findByResendAddressIn($to);
    }

    public function bounceOrResend(MailingList $ml, string $from, string $rawHeader, string $body): bool {
        if (!$this->isAllowedSender($ml, $from)) {
            $this->logger->error("Rejecting sender $from for mailing list $ml->id with resend mode $ml->resendAccess");
            return false;
        }

        if (preg_match("/^List-Id:.*<$ml->resendAddress>/mi", $rawHeader, $loopMatches)) {
            $this->logger->error("Rejecting mail loop from $from for mailing list $ml->id: " . print_r($loopMatches, true));
            return false;
        }

        $this->logger->info("Resending mail from $from to {$ml->resendAddress}");
        $headers = $this->parseHeaders($rawHeader);
        $headers->addMailboxHeader("Sender", new Address($ml->resendAddress));
        $headers->addMailboxHeader("List-Id", new Address($ml->resendAddress, $ml->title));
        $to = $this->memberResolver->getMemberEmails($ml->id);
        $this->rawMailer->sendRaw($body, $from, $to, $headers);
        return true;
    }

    private function isAllowedSender(MailingList $ml, string $from): bool {
        switch ($ml->resendAccess) {
            case MailingList::ACCESS_MODERATORS:
                return in_array($from, $this->memberResolver->getMemberEmails($ml->id, Member::TYPES_MODERATOR));
            case MailingList::ACCESS_MEMBERS:
                return in_array($from, $this->memberResolver->getMemberEmails($ml->id));
            case MailingList::ACCESS_OPEN:
                return true;
            default:
                $this->logger->error("Unknown resend mode {$ml->resendAccess} for mailing list {$ml->id}, rejecting sender $from");
                return false;
        }
    }

    private function parseHeaders(string $rawHeader): Headers {
        $headers = new Headers();
        $key = null;
        $value = '';
        foreach (explode("\n", $rawHeader) as $line) {
            if (ctype_space(substr($line, 0, 1))) {
                // Continuation of previous header
                $value .= "\n" . trim($line);
            } else if (trim($line) !== "") {
                // Next header
                $this->addParsedHeader($headers, $key, $value);
                $parts = explode(':', $line, 2);
                $key = $parts[0];
                $value = count($parts) > 1 ? trim($parts[1]) : '';
            }
        }
        $this->addParsedHeader($headers, $key, $value);
        return $headers;
    }

    private function addParsedHeader(Headers $headers, $key, $rawValue) {
        if ($key === null) {
            return;
        }

        $lkey = strtolower($key);
        if (in_array($lkey, self::REMOVE_HEADERS)) {
            $this->logger->debug("Skipping header: $key");
            return;
        }

        $value = quoted_printable_decode($rawValue);
        $this->logger->debug("Parsed header: $key: $value");
        switch ($lkey) {
            case 'date':
                // setlocale(LC_TIME, "en_US");
                $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC2822, $value);
                if ($date === false) {
                    $this->logger->warning("Invalid date '$value' in '$key' header: " . print_r(\DateTimeImmutable::getLastErrors(), true));
                    $headers->addTextHeader($key, $value);
                } else {
                    $headers->addDateHeader($key, $date);
                }
                break;
            case 'from':
            case 'to':
            case 'cc':
            case 'reply-to':
                $headers->addMailboxListHeader($key, array_map(function ($v) {
                    return quoted_printable_decode($v);
                }, explode(',', $rawValue)));
                break;
            case 'message-id':
                $headers->addIdHeader($key, array_map(function ($v) {
                    return substr($v, 0, 1) === '<' && substr($v, -1, 1) === '>'
                        ? substr($v, 1, -1) : $v;
                }, explode(',', $value)));
                break;
            case 'return-path':
                $headers->addPathHeader($key, Address::create($value));
                break;
            default:
                $headers->addTextHeader($key, $value);
        }
    }

}