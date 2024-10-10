<!--
  - @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
  -
  - @license AGPL-3.0-or-later
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
      <template #list>
        <AppNavigationNew v-if="appContext.admin"
                          :text="t('majordomo', 'New Mailinglist')"
                          :disabled="false"
                          button-id="new-mailinglist-button"
                          button-class="icon-add"
                          v-on:click="$router.push({ name: 'list', params: { id: 'new' }})"
        />
        <ul>
          <AppNavigationItem :name="t('majordomo', 'Loading ...')" :loading="true" v-if="loading"/>
          <AppNavigationItem :key="list.id" :name="list.title" :icon="list.syncActive ? 'icon-play' : 'icon-pause'"
                             :to="{ name: 'list', params: { id: list.id }}" v-for="list in lists"/>
        </ul>
      </template>
      <template #footer>
        <AppNavigationItem icon="icon-error" :name="t('majordomo', 'Bounces')" :to="{ name: 'bounces' }" v-if="appContext.moderator"/>
        <AppNavigationItem icon="icon-settings-dark" :name="t('majordomo', 'Settings')" :to="{ name: 'settings' }" v-if="appContext.admin"/>
      </template>
    </AppNavigation>
    <AppContent>
      <router-view @saved="load()"/>
    </AppContent>
  </Content>
</template>

<script>

import Content from '@nextcloud/vue/dist/Components/NcContent.js';
import AppContent from '@nextcloud/vue/dist/Components/NcAppContent.js';
import AppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js';
import AppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js';
import AppNavigationNew from '@nextcloud/vue/dist/Components/NcAppNavigationNew.js';
import AppNavigationSettings from '@nextcloud/vue/dist/Components/NcAppNavigationSettings.js';
import ActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js';
import ActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js';
import AppNavigationIconBullet from '@nextcloud/vue/dist/Components/NcAppNavigationIconBullet.js';
import ActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js';
import ActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js';
import ActionRouter from '@nextcloud/vue/dist/Components/NcActionRouter.js';
import ActionText from '@nextcloud/vue/dist/Components/NcActionText.js';
import ActionTextEditable from '@nextcloud/vue/dist/Components/NcActionTextEditable.js';
import VueRouter from "vue-router";

import api from "./api";
import Bounces from "./Bounces";
import MailingList from "./MailingList";
import Settings from "./Settings";
import {linkTo} from "@nextcloud/router";

const router = new VueRouter({
  mode: 'hash',
  base: linkTo('majordomo', ''),
  routes: [
    {path: "/", component: {template: ""}},
    {path: "/settings", name: 'settings', component: Settings},
    {path: "/bounces/:uid?", name: 'bounces', component: Bounces},
    {path: "/lists/:id", name: 'list', component: MailingList}
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
  data: function () {
    return {
      loading: false,
      lists: [],
    }
  },
  methods: {
    load() {
      this.loading = true;
      api.get("/lists").then(lists => {
        this.loading = false;
        this.lists = lists;
      }).catch(() => {
        OC.Notification.showTemporary(t("majordomo", "Failed to load mailing lists."), {type: "error"});
      });
    }
  },
  mounted: function () {
    this.load();
  },
  router
};
</script>
