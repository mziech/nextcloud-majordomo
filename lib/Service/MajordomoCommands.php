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

use OC\Mail\Message;
use OCA\Majordomo\Db\MailingList;
use OCP\Mail\IMailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Encoder\EightBitContentEncoder;
use Symfony\Component\Mime\Part\TextPart;

class MajordomoCommands {

    const MAGIC = "NC-Majordomo";

    private $commands = array();
    /**
     * @var MailingList
     */
    private $ml;
    /**
     * @var IMailer
     */
    private $mailer;

    private $requestId;
    /**
     * @var Settings
     */
    private $settings;

    public function __construct($requestId, MailingList $ml, IMailer $mailer, Settings $settings) {
        $this->ml = $ml;
        $this->mailer = $mailer;
        $this->requestId = $requestId;
        $this->settings = $settings;
    }

    function subscribe($email) {
        $this->commands[] = implode(' ', [
            'approve',
            $this->ml->getPassword(),
            'subscribe',
            $this->ml->getListname(),
            $email
        ]);
        return $this;
    }

    function subscribeList($emails) {
        foreach ($emails as $email) {
            $this->subscribe($email);
        }
        return $this;
    }

    function unsubscribe($email) {
        $this->commands[] = implode(' ', [
            'approve',
            $this->ml->getPassword(),
            'unsubscribe',
            $this->ml->getListname(),
            $email
        ]);
        return $this;
    }

    function unsubscribeList($emails) {
        foreach ($emails as $email) {
            $this->unsubscribe($email);
        }
        return $this;
    }

    function who() {
        $this->commands[] = implode(' ', [
            'approve',
            $this->ml->getPassword(),
            'who',
            $this->ml->getListname()
        ]);
        return $this;
    }

    function apply() {
        $this->commands[] = 'end';
        $this->commands[] = '';
        $body = implode("\r\n", $this->commands);
        $to = $this->ml->manager;

        $this->sendRawMail($to, $body);

        $this->commands = [];
    }

    function approveBounce(string $body) {
        $this->sendRawMail($this->ml->bounceAddress, "Approved: " . $this->ml->password . "\r\n" . $body);
    }

    private function sendRawMail(string $to, string $body) {
        $from = $this->settings->getImapSettings()->from;
        $subject = self::MAGIC . " " . $this->requestId;
        $message = $this->createPlaintextMessage($body);
        if ($from) {
            $message->setFrom([$from]);
        }
        $message->setTo([$to]);
        $message->setSubject($subject);
        $failedRecipients = $this->mailer->send($message);
        if (in_array($to, $failedRecipients)) {
            throw new OutboundException("Failed to send Majordomo command for MailingList {$this->ml->id} to " . implode(", ", $failedRecipients));
        }
    }

    /**
     * Creates a plaintext message using private API which is not available from IMailer
     *
     * @return Message
     */
    protected function createPlaintextMessage(string $text): Message {
        // Horrible stuff is happening here ... but I couldn't find any public API to achieve all of this
        if ((new \ReflectionClass(Message::class))->getConstructor()->getParameters()[0]->getType()->getName() === "Swift_Message") {
            $message = new Message(new \Swift_Message(), true);
            $message->getSwiftMessage()->setEncoder(new \Swift_Mime_ContentEncoder_RawContentEncoder());  // disable quoted-printable encoding
            $message->getSwiftMessage()->setMaxLineLength(0);  // disable word-wrap
            $message->setPlainBody($text);
            return $message;
        } else { // Nextcloud version >= 26
            $email = new Email();
            $email->setBody(new TextPart($text, "utf-8", "plain", "8bit"));
            $message = new Message($email, true);
            $message->setPlainBody("overwritten");
            return $message;
        }
    }

}
