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


use OCP\IConfig;

class Settings {

    /**
     * @var IConfig
     */
    private $config;
    private $AppName;

    function __construct($AppName, IConfig $config) {
        $this->config = $config;
        $this->AppName = $AppName;
    }

    public function setImapSettings(array $arr) {
        $this->config->setAppValue($this->AppName, "imap", json_encode($this->arrayToObject($arr, new ImapSettings())));
    }

    /**
     * @return ImapSettings
     */
    public function getImapSettings() {
        $value = $this->config->getAppValue($this->AppName, "imap");
        return $this->jsonToObject($value, new ImapSettings());
    }

    private function jsonToObject($value, $obj) {
        $arr = json_decode($value, true);
        if ($arr === NULL) {
            return new ImapSettings();
        }

        return $this->arrayToObject($arr, $obj);
    }

    private function arrayToObject($arr, $obj) {
        $fields = get_class_vars(get_class($obj));
        foreach ($arr as $k => $v) {
            if (array_key_exists($k, $fields)) {
                $obj->$k = $v;
            }
        }
        return $obj;
    }

}
