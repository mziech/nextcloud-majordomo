/**
 * @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
 *
 * @license AGPL-3.0-or-later
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

import { generateUrl } from "@nextcloud/router";
import axios from "@nextcloud/axios";

const BASEPATH = '/apps/majordomo/api';
export default new class Api {
    path(path) {
        return generateUrl(BASEPATH + path);
    }

    get(path) {
        return axios.get(generateUrl(BASEPATH + path)).then(response => response.data);
    }

    post(path, data) {
        return axios.post(generateUrl(BASEPATH + path), data).then(response => response.data);
    }
};
