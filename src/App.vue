<!--
  - @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<Content app-name="majordomo">
		<AppNavigation>
			<AppNavigationNew v-if="true"
							  :text="t('majordomo', 'New Mailinglist')"
							  :disabled="false"
							  button-id="new-mailinglist-button"
							  button-class="icon-add"
							  v-on:click="$router.push({ name: 'list', params: { id: 'new' }})"
			/>
			<ul>
				<AppNavigationItem :title="t('majordomo', 'Loading ...')" :loading="true" v-if="loading" />
				<AppNavigationItem :key="list.id" :title="list.title" icon="icon-group" :to="{ name: 'list', params: { id: list.id }}" v-for="list in lists"/>
			</ul>
			<AppNavigationItem icon="icon-settings" :title="t('majordomo', 'Settings')" :to="{ name: 'settings' }"/>
		</AppNavigation>
		<AppContent>
			<router-view/>
		</AppContent>
	</Content>
</template>

<script>

import { AppNavigationItem } from '@nextcloud/vue';
import Content from '@nextcloud/vue/dist/Components/Content';
import AppContent from '@nextcloud/vue/dist/Components/AppContent';
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation';
//import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem';
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew';
import AppNavigationCounter from '@nextcloud/vue/dist/Components/AppNavigationCounter';
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings';
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton';
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink';
import AppNavigationIconBullet from '@nextcloud/vue/dist/Components/AppNavigationIconBullet';
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox';
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput';
import ActionRouter from '@nextcloud/vue/dist/Components/ActionRouter';
import ActionText from '@nextcloud/vue/dist/Components/ActionText';
import ActionTextEditable from '@nextcloud/vue/dist/Components/ActionTextEditable';
import VueRouter from "vue-router";

import api from "./api";
import MailingList from "./MailingList";
import Settings from "./Settings";

const router = new VueRouter({
	mode: 'hash',
	base: oc_appswebroots['majordomo'] + '/',
	routes: [
		{ path: "/", component: { template: "" } },
		{ path: "/settings", name: 'settings', component: Settings },
		{ path: "/lists/:id", name: 'list', component: MailingList }
	]
});

export default {
	name: 'App',
	components: {
		Content,
		AppContent,
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		AppNavigationCounter,
		AppNavigationSettings,
		ActionButton,
		ActionLink,
		AppNavigationIconBullet,
		ActionCheckbox,
		ActionInput,
		ActionRouter,
		ActionText,
		ActionTextEditable,
		MailingList,
	},
	data: function() {
		return {
			loading: false,
			lists: [],
		}
	},
	mounted: function () {
		this.loading = true;
		api.get("/lists").then(data => {
			this.loading = false;
			this.lists = data;
		});
	},
	router
};
</script>
