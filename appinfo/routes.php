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

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Majordomo\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
	    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'api#appContext', 'url' => '/api/app-context', 'verb' => 'GET'],
        ['name' => 'api#lists', 'url' => '/api/lists', 'verb' => 'GET'],
        ['name' => 'api#getList', 'url' => '/api/lists/{id}', 'verb' => 'GET'],
        ['name' => 'api#getListStatus', 'url' => '/api/lists/{id}/status', 'verb' => 'GET'],
        ['name' => 'api#postList', 'url' => '/api/lists/{id}', 'verb' => 'POST'],
        ['name' => 'api#getPendingChanges', 'url' => '/api/lists/{id}/pending', 'verb' => 'GET'],
        ['name' => 'api#postListCheck', 'url' => '/api/lists/{id}/requests/check', 'verb' => 'POST'],
        ['name' => 'api#postListImport', 'url' => '/api/lists/{id}/requests/import', 'verb' => 'POST'],
        ['name' => 'api#postListSync', 'url' => '/api/lists/{id}/requests/sync', 'verb' => 'POST'],
        ['name' => 'api#getRequestStatus', 'url' => '/api/requests/{id}', 'verb' => 'GET'],
        ['name' => 'api#getSettings', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'api#postSettings', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'api#postSettingsTest', 'url' => '/api/settings/test', 'verb' => 'POST'],
        ['name' => 'api#postProcessMails', 'url' => '/api/process-mails', 'verb' => 'POST'],
        ['name' => 'page#catchAll', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => array('path' => '.+')],
    ]
];
