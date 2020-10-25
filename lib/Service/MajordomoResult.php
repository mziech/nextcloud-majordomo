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


class MajordomoResult {
    public $command;
    public $listname;
    public $emails = [];
    public $success = NULL;

    /**
     * MajordomoResult constructor.
     * @param $command
     * @param $listname
     */
    public function __construct($command, $listname) {
        $this->command = $command;
        $this->listname = $listname;
    }

    public function processLine($rawLine) {
        $line = trim($rawLine);
        if ($line === '') {
            return;
        }

        switch ($this->command) {
            case 'who':
                if (strncmp ('Members of list', $line, 15) === 0) {
                    return;
                } else if (strstr($line, " subscriber") !== FALSE) {
                    return;
                } else {
                    $this->emails[] = strtolower($line);
                }
                break;
            case 'subscribe':
            case 'unsubscribe':
                if ($line === 'Succeeded.') {
                    $this->success = true;
                }
                break;
        }

}

    public static function fromLine(string $line) : ?MajordomoResult {
        if (strncmp($line, '>>>> ', 5) == 0) {
            $words = preg_split("/ +/", $line);
            array_shift($words); // >>>>
            if ($words[0] == 'approve') {
                array_shift($words);
                array_shift($words);
            }
            return self::fromWords($words);
        } else {
            return NULL;
        }
    }

    public static function fromWords(array $words) {
        $result = new MajordomoResult(array_shift($words), array_shift($words));
        if ($result->command === 'subscribe' || $result->command === 'unsubscribe') {
            $result->emails[] = strtolower(array_shift($words));
        }
        return $result;
    }
}