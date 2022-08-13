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
  <EmptyContent v-if="loading" icon="icon-loading">
    {{ t('majordomo', 'Loading ...') }}
  </EmptyContent>
  <EmptyContent v-else-if="loadingError" icon="icon-error">
    {{ loadingError }}
  </EmptyContent>
  <div v-else style="display: flex">
    <AppContentList v-if="!loading" :showDetails="true">
      <h2>{{ t('majordomo', 'Current list members') }}</h2>
      <div class="app-content-list-item" v-for="item in status">
        <div class="app-content-list-item-icon" style="background-color: rgb(141, 197, 156);" v-if="!item.uid">
          <span class="inlineblock icon-mail-white"></span>
        </div>
        <div class="app-content-list-item-line-one" v-if="!item.uid">{{ t('majordomo', 'E-Mail') }}</div>

        <div class="app-content-list-item-icon" v-if="item.uid">
          <Avatar :user="item.uid"/>
        </div>
        <div class="app-content-list-item-line-one" v-if="item.uid">{{ item.displayName }}</div>

        <div class="app-content-list-item-line-two">{{ item.email }}</div>

        <div class="icon-add" v-if="item.status === 'ADD'" :title="t('majordomo', 'Member needs to be added')"></div>
        <div class="icon-delete" v-if="item.status === 'DELETE'"
             :title="t('majordomo', 'Member needs to be removed')"></div>
      </div>
    </AppContentList>
    <AppContentDetails v-if="!loading">
      <h2 v-if="!isNew">{{ t('majordomo', 'Edit Mailinglist: {title}', list) }}</h2>
      <h2 v-if="isNew">{{ t('majordomo', 'Create Mailinglist') }}</h2>
      <form @submit.prevent="save()">
        <p class="centered-input">
          <label for="title">{{ t('majordomo', 'Mailing list title') }}:</label>
          <input id="title" v-model="list.title"/>
        </p>
        <p class="centered-input">
          <label for="manager">{{ t('majordomo', 'List manager email address') }}:</label>
          <input id="manager" type="email" v-model="list.manager"/>
        </p>
        <p class="centered-input">
          <label for="listname">{{ t('majordomo', 'List name for management') }}:</label>
          <input id="listname" v-model="list.listname"/>
        </p>
        <p class="centered-input">
          <label for="password">{{ t('majordomo', 'List manager password') }}:</label>
          <input id="password" type="password" v-model="list.password" :placeholder="isNew ? '' : '********'"/>
        </p>
        <p class="centered-input">
          <label for="manager">{{ t('majordomo', 'Mail address to approve bounces') }}:</label>
          <input id="bounceAddress" type="email" v-model="list.bounceAddress"/>
        </p>
        <p>
          <input type="checkbox" id="syncActive" class="checkbox" v-model="list.syncActive">
          <label for="syncActive">{{ t('majordomo', 'Enable automatic synchronization of list members') }}</label><br>
        </p>
        <p class="centered-input">
          <button class="primary">{{ t('majordomo', 'Save') }}</button>
        </p>
        <p class="centered-input" v-if="!isNew">
          <RequestButton :list-id="list.id" action="check" @success="reload()" :disabled="dirty">
            {{ t('majordomo', 'Retrieve current status from list manager') }}
          </RequestButton>
        </p>
        <p class="centered-input" v-if="!isNew">
          <RequestButton :list-id="list.id" action="import" @success="reload()" :disabled="dirty">
            {{ t('majordomo', 'Retrieve current status from list manager AND apply to settings') }}
          </RequestButton>
        </p>
        <p class="centered-input" v-if="!isNew">
          <RequestButton :list-id="list.id" action="sync" @success="reload()" :disabled="dirty">
            {{ t('majordomo', 'Write desired changes to list manager') }}
          </RequestButton>
        </p>
      </form>
      <h3>{{ t('majordomo', 'Member Policy') }}</h3>
      <div>
        <select v-model="addMemberType" v-on:change="addMemberReference = ''">
          <option value="GROUP">{{ t('majordomo', 'Members of group') }}</option>
          <option value="USER">{{ t('majordomo', 'E-Mail of user') }}</option>
          <option value="EXTRA">{{ t('majordomo', 'Additional E-Mail') }}</option>
          <option value="NOTGROUP">{{ t('majordomo', 'Except members of group') }}</option>
          <option value="NOTUSER">{{ t('majordomo', 'Except E-Mail of user') }}</option>
          <option value="EXCLUDE">{{ t('majordomo', 'Exclude E-Mail') }}</option>
        </select>
        <select v-model="addMemberReference" v-if="addMemberType !== 'EXTRA'">
          <option value="">{{ t('majordomo', '- Please select -') }}</option>
          <option v-if="addMemberType === 'GROUP' || addMemberType === 'NOTGROUP'" v-for="(v, k) in appContext.groups"
                  :value="k">{{ v }}
          </option>
          <option v-if="addMemberType === 'USER' || addMemberType === 'NOTUSER'" v-for="(v, k) in appContext.users"
                  :value="k">{{ v }}
          </option>
        </select>
        <input v-model="addMemberReference" v-if="addMemberType === 'EXTRA' || addMemberType === 'EXCLUDE'"/>
        <button type="button"
                v-on:click="addMember()"
                :disabled="addMemberReference === ''"
        ><span class="icon-add inlineblock"></span>{{ t('majordomo', 'Add policy') }}
        </button>
      </div>
      <div class="mailing-list-policies">
        <div v-for="member in list.members" class="mailing-list-policy">
          <Avatar v-if="member.type === 'GROUP' || member.type === 'NOTGROUP'" iconClass="icon-group-white"
                  :isNoUser="true"/>
          <Avatar v-if="member.type === 'USER' || member.type === 'NOTUSER'" :user="member.reference"/>
          <Avatar v-if="member.type === 'EXTRA' || member.type === 'EXCLUDE'" iconClass="icon-mail-white"
                  :isNoUser="true"/>
          <div class="mailing-list-policy--text">
            <div v-if="member.type === 'GROUP'">{{ t('majordomo', 'Members of group') }}</div>
            <div v-if="member.type === 'USER'">{{ t('majordomo', 'E-Mail of user') }}</div>
            <div v-if="member.type === 'EXTRA'">{{ t('majordomo', 'Additional E-Mail') }}</div>
            <div v-if="member.type === 'NOTGROUP'">{{ t('majordomo', 'Except members of group') }}</div>
            <div v-if="member.type === 'NOTUSER'">{{ t('majordomo', 'Except E-Mail of user') }}</div>
            <div v-if="member.type === 'EXCLUDE'">{{ t('majordomo', 'Exclude E-Mail') }}</div>
            <div class="mailing-list-policy--reference">{{ member.reference }}</div>
          </div>
          <button type="button"
                  class="icon-delete"
                  v-on:click="removeMember(member)"></button>
        </div>
      </div>
    </AppContentDetails>
  </div>
</template>
<script>
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList';
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails';
import Avatar from '@nextcloud/vue/dist/Components/Avatar';
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent';
import api from "./api";
import RequestButton from "./RequestButton";

export default {
  components: {
    RequestButton,
    AppContentList,
    AppContentDetails,
    Avatar,
    EmptyContent,
  },
  data() {
    return {
      addMemberType: 'GROUP',
      addMemberReference: '',
      appContext: {users: {}, groups: {}},
      loading: true,
      isNew: true,
      loadingError: null,
      dirty: false,
      list: {},
      status: []
    }
  },
  mounted() {
    this.load(this.$route.params.id);
  },
  beforeRouteUpdate(to, from, next) {
    this.load(to.params.id).then(next);
  },
  methods: {
    load(id) {
      this.isNew = id === 'new';
      this.loadingError = null;
      this.loading = true;
      return Promise.all([
        api.get('/app-context').then(appContext => {
          this.appContext = appContext;
        }),
        api.get(`/lists/${id}`).then(list => {
          this.list = list;
          this.dirty = false;
        }),
        api.get(`/lists/${id}/status`).then(status => {
          this.status = status;
        }),
      ]).catch(() => {
        this.loadingError = t('majordomo', 'An error occurred while loading the mailing list.');
      }).then(() => {
        this.loading = false;
      });
    },
    reload() {
      this.load(this.$route.params.id);
    },
    save() {
      api.post(`/lists/${this.$route.params.id}`, this.list).then(list => {
        OC.Notification.showTemporary(t("majordomo", "Mailing list saved."));
        this.$router.replace({name: 'list', params: {id: list.id}});
        this.$emit("saved");
        this.dirty = false;
      }).catch(() => {
        OC.Notification.showTemporary(t("majordomo", "Failed to save mailing list."), {type: "error"});
      });
    },
    addMember() {
      const memberToAdd = {
        type: this.addMemberType,
        reference: this.addMemberReference
      };
      this.removeMember(memberToAdd);
      this.list = {
        ...this.list,
        members: !this.list.members ? [memberToAdd] : [...this.list.members, memberToAdd]
      }
      this.addMemberReference = '';
      this.dirty = true;
    },
    removeMember(memberToRemove) {
      if (this.list.members) {
        this.list = {
          ...this.list, members: [...this.list.members.filter(member =>
              member.type !== memberToRemove.type || member.reference !== memberToRemove.reference
          )]
        };
        this.dirty = true;
      }
    },
  },
}
</script>

<style lang="scss" scoped>

.mailing-list-policies {
  .mailing-list-policy {
    border-top: 1px solid var(--color-border);
    clear: left;

    .avatardiv {
      float: left;
      margin: 5px;

      .avatar-class-icon {
        height: 32px;
      }
    }

    .mailing-list-policy--text {
      float: left;
      width: 320px;
      height: 32px;
    }

    .mailing-list-policy--reference {
      color: var(--color-text-lighter);
    }

    & > button {
      width: 32px;
      height: 32px;
      margin: 5px;
    }
  }
}

.centered-input {
  label {
    display: inline-block;
    min-width: 300px;
    text-align: right;
  }

  input {
    min-width: 200px;
  }

  button {
    margin-left: 300px;
  }
}

</style>