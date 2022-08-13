<!--
  - @copyright Copyright (c) 2022 Marco Ziech <marco+nc@ziech.net>
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
  <EmptyContent v-if="loading" icon="icon-loading">
    {{ t('majordomo', 'Loading ...') }}
  </EmptyContent>
  <div v-else>
    <h2>{{ t('majordomo', 'Bounced Messages') }}</h2>
    <div v-if="bounces.length === 0">
      {{ t('majordomo', 'There are currently no bounced messages.') }}
    </div>
    <ul v-else>
      <ListItem v-for="bounce of bounces" :title="bounce.from" :details="bounce.date" :key="bounce.uid"
                :to="$route.params.uid === `${bounce.uid}` ? { name: 'bounces' } : { name: 'bounces', params: { uid: `${bounce.uid}` }, }">
        <template #icon>
          <div class="icon-triangle-s" v-if="$route.params.uid === `${bounce.uid}`"/>
          <div class="icon-triangle-e" v-else/>
        </template>
        <template #subtitle>
          → {{bounce.list_title}}
        </template>
        <template #actions>
          <ActionRouter icon="icon-toggle" :to="{ name: 'bounces', params: { uid: `${bounce.uid}` }, }">
            {{ t('majordomo', 'View') }}
          </ActionRouter>
          <ActionButton icon="icon-checkmark" :disabled="writing" @click="approve(bounce.uid)">
            {{ t('majordomo', 'Approve') }}
          </ActionButton>
          <ActionButton icon="icon-delete" :disabled="writing" @click="reject(bounce.uid)">
            {{ t('majordomo', 'Reject') }}
          </ActionButton>
        </template>
        <template #extra v-if="$route.params.uid === `${bounce.uid}`">
          <div v-if="bodyLoading" class="icon-loading"/>
          <pre v-else class="bounce-body">{{ body }}</pre>
        </template>
      </ListItem>
    </ul>
  </div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton';
import ActionRouter from "@nextcloud/vue/dist/Components/ActionRouter";
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent';
import ListItem from '@nextcloud/vue/dist/Components/ListItem';
import api from "./api";

export default {
  components: {
    ActionButton,
    ActionRouter,
    EmptyContent,
    ListItem,
  },
  name: "Bounces",
  watch: {
    "$route.params.uid"(newUid, oldUid) {
      if (newUid) {
        if (oldUid !== newUid) {
          this.loadBody(newUid);
        }
      }
    }
  },
  data() {
    return {
      loading: true,
      bounces: [],
      bodyLoading: true,
      body: "",
      writing: false,
    };
  },
  mounted() {
    api.get('/bounces').then(bounces => {
      this.bounces = bounces;
      this.loading = false;
    });
    this.loadBody(this.$route.params.uid);
  },
  methods: {
    approve(uid) {
      this.writing = true;
      api.post(`/bounces/${uid}/approve`)
          .then(() => {
            this.bounces = this.bounces.filter(bounce => bounce.uid !== uid)
            OC.Notification.showTemporary(t("majordomo", "Bounced message was approved."));
          })
          .catch(err => {
            OC.Notification.showTemporary(t("majordomo", "Failed to approve bounced message."), {type: "error"});
          })
          .then(() => this.writing = false);
    },
    reject(uid) {
      api.post(`/bounces/${uid}/reject`)
        .then(() => {
          this.bounces = this.bounces.filter(bounce => bounce.uid !== uid)
          OC.Notification.showTemporary(t("majordomo", "Bounced message was rejected."));
        })
        .catch(err => {
          OC.Notification.showTemporary(t("majordomo", "Failed to reject bounced message."), {type: "error"});
        }).then(() => this.writing = false);
    },
    loadBody(uid) {
      this.bodyLoading = true;
      this.body = "";
      if (uid) {
        api.get(`/bounces/${uid}`)
            .then(data => this.body = data.body)
            .catch(err => {
              this.body = t("majordomo", "Failed to load bounced message.");
            }).then(() => this.bodyLoading = false)
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.bounce-body {
  font-family: "Courier New", monospace;
  background-color: var(--color-background-dark);
  border-color: var(--color-border-dark);
  border-radius: var(--border-radius);
  border-width: 1px;
  border-style: solid;
  padding: var(--border-radius);
  margin-left: 20px;
  overflow: auto;
  max-width: 100%;
  max-height: 100%;
}
</style>
