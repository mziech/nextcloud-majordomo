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

use DirectoryTree\ImapEngine\MessageInterface;
use OC\Mail\Mailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\DigestPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\IMimePart;

class RawMailer extends Mailer {

    public function isSupported(): bool {
        return in_array('getInstance', get_class_methods($this)) &&
            class_exists('\\Symfony\\Component\\Mailer\\Mailer');
    }

    public function resendRaw(MessageInterface $mail, string $from, array $to, Headers $headers = null) {
        $part = $this->mailToPart($mail->parse());
        $msg = new Message($headers, $part);
        $envelope = new Envelope(Address::create($from), Address::createArray($to));
        $this->getInstance()->send($msg, $envelope);
    }

    private function mailToPart(IMessagePart $part): AbstractPart {
        $contentType = $part->getContentType("text/plain");
        if ($part instanceof IMimePart && $part->isMultiPart()) {
            return $this->createMultipartPart($contentType, array_map(function ($child) {
                return $this->mailToPart($child);
            }, $part->getChildParts()));
        } else if (str_starts_with($contentType, "text/")) {
            return new TextPart($part->getContent(), $part->getCharset(), substr($contentType, 5), $part->getContentTransferEncoding());
        } else {
            return new DataPart($part->getBinaryContentResourceHandle(), $part->getFilename(), $contentType, $part->getContentTransferEncoding());
        }
    }

    /**
     * @param string $contentType
     * @param AbstractPart[] $children
     * @return AbstractMultipartPart
     */
    private function createMultipartPart(string $contentType, array $children): AbstractMultipartPart {
        switch ($contentType) {
            case "multipart/mixed":
                return new MixedPart(...$children);
            case "multipart/digest":
                return new DigestPart(...$children);
            case "multipart/alternative":
                return new AlternativePart(...$children);
            default:
                // Fallback to mixed if Symphony has nothing to offer
                return new MixedPart(...$children);
        }
    }

}