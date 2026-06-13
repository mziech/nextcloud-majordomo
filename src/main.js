/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import appContext from "./context";

import { translate, translatePlural } from '@nextcloud/l10n';
import { generateUrl } from '@nextcloud/router'

import App from './App.vue';
import Settings from "./Settings.vue";
import Bounces from "./Bounces.vue";
import MailingList from "./MailingList.vue";

const app = createApp(App);
app.config.globalProperties.appContext = appContext;
app.config.globalProperties.t = translate;
app.config.globalProperties.n = translatePlural;
app.config.globalProperties.OC = window.OC;
app.config.globalProperties.OCA = window.OCA;

const router = createRouter({
    history: createWebHistory(generateUrl('/apps/majordomo', '')),
    routes: [
        {path: "/", component: {template: ""}},
        {path: "/settings", name: 'settings', component: Settings},
        {path: "/bounces/:uid?", name: 'bounces', component: Bounces},
        {path: "/lists/:id", name: 'list', component: MailingList}
    ]
});
app.use(router);

app.mount("#content");

export default app;
