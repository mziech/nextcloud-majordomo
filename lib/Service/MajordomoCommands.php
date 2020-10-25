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

    public function __construct($requestId, MailingList $ml, IMailer $mailer) {
        $this->ml = $ml;
        $this->mailer = $mailer;
        $this->requestId = $requestId;
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
        $subject = self::MAGIC . " " . $this->requestId;
        $body = implode("\r\n", $this->commands);

        $message = new Message(new \Swift_Message(), true);
        $message->setTo([ $this->ml->manager ]);
        $message->setPlainBody($body);
        $message->setSubject($subject);
        $failedReceipients = $this->mailer->send($message);
        if (in_array($this->ml->manager, $failedReceipients)) {
            throw new OutboundException("Failed to send Majordomo command for MailingList {$this->ml->id} to " . implode(", ", $failedReceipients));
        }

        $this->commands = [];
    }

}
