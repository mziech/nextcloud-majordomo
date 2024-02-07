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

use OC\Mail\Mailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\TextPart;

class RawMailer extends Mailer {

    public function isSupported(): bool {
        return in_array('getInstance', get_class_methods($this)) &&
            class_exists('\\Symfony\\Component\\Mailer\\Mailer');
    }

    public function sendRaw(string $body, string $from, array $to, Headers $headers = null) {
        $msg = new Message($headers === null ? new Headers() : $headers, new TextPart($body));
        $envelope = new Envelope(Address::create($from), Address::createArray($to));
        $this->getInstance()->send($msg, $envelope);
    }

}