<?php
/**
 * @copyright Copyright (c) 2025 Marco Ziech <marco+nc@ziech.net>
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
namespace OCA\Majordomo\Command;

use OC\Log\PsrLoggerAdapter;
use OCA\Majordomo\Service\ImapLoader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Idle extends Command {

    private ImapLoader $imapLoader;

    public function __construct(ImapLoader $imapLoader) {
        parent::__construct();
        $this->imapLoader = $imapLoader;
    }

    protected function configure() {
        $this->setName("majordomo:idle")
            ->setDescription("Wait for new messages and process them");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        try {
            $output->writeln("Processing all existing mails");
            $this->imapLoader->processMails();
            $output->writeln("Waiting for new mails");
            $this->imapLoader->idle();
            return 0;
        } catch (\Exception $e) {
            $output->writeln("Exception occurred: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return 1;
        }
    }

}