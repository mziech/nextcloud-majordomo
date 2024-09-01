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
          <input id="title" v-model="list.title" :disabled="!list.access.canAdmin" />
        </p>
        <p class="centered-input">
          <label for="resendAddress">
            {{ t('majordomo', 'Email address for built-in list manager') }}:
            <NcCounterBubble type="highlight">BETA</NcCounterBubble>
          </label>
          <input id="resendAddress" type="email" :disabled="!list.access.canAdmin"/>
        </p>
        <template v-if="list.access.canAdmin">
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
            <label for="bounceAddress">{{ t('majordomo', 'Mail address to approve bounces') }}:</label>
            <input id="bounceAddress" type="email" v-model="list.bounceAddress"/>
          </p>
          <p>
            <input type="checkbox" id="syncActive" class="checkbox" v-model="list.syncActive">
            <label for="syncActive">{{ t('majordomo', 'Enable automatic synchronization of list members') }}</label><br>
          </p>
          <h3>{{ t('majordomo', 'Access Control') }}</h3>
          <p class="centered-input">
            <label for="viewAccess">{{ t('majordomo', 'Writing emails to the list') }}:</label>
            <MailingListAccess v-model="list.resendAccess" />
          </p>
          <p class="centered-input">
            <label for="viewAccess">{{ t('majordomo', 'Visibility in list') }}:</label>
            <MailingListAccess v-model="list.viewAccess" />
          </p>
          <p class="centered-input">
            <label for="viewAccess">{{ t('majordomo', 'Viewing members') }}:</label>
            <MailingListAccess v-model="list.memberListAccess" />
          </p>
          <p class="centered-input">
            <label for="viewAccess">{{ t('majordomo', 'Editing members') }}:</label>
            <MailingListAccess v-model="list.memberEditAccess" />
          </p>
        </template>
        <p class="centered-input" v-if="list.access.canEditMembers">
          <button class="primary">{{ t('majordomo', 'Save') }}</button>
        </p>
        <p class="centered-input" v-if="!isNew && list.access.canAdmin">
          <RequestButton :list-id="list.id" action="check" @success="reload()" :disabled="dirty">
            {{ t('majordomo', 'Retrieve current status from list manager') }}
          </RequestButton>
        </p>
        <p class="centered-input" v-if="!isNew && list.access.canAdmin">
          <RequestButton :list-id="list.id" action="import" @success="reload()" :disabled="dirty">
            {{ t('majordomo', 'Retrieve current status from list manager AND apply to settings') }}
          </RequestButton>
        </p>
        <p class="centered-input" v-if="!isNew && list.access.canAdmin">
          <RequestButton :list-id="list.id" action="sync" @success="reload()" :disabled="dirty">
            {{ t('majordomo', 'Write desired changes to list manager') }}
          </RequestButton>
        </p>
      </form>
      <h3  v-if="list.access.canListMembers">{{ t('majordomo', 'Member Policy') }}</h3>
      <div v-if="list.access.canEditMembers">
        <select v-model="addMemberType" @change="onMemberTypeChange()">
          <option value="">{{ t('majordomo', '- Please select -') }}</option>
          <optgroup :label="t('majordomo', 'Members')">
            <option value="GROUP">{{ t('majordomo', 'Members of group') }}</option>
            <option value="USER">{{ t('majordomo', 'E-Mail of user') }}</option>
            <option value="EXTRA">{{ t('majordomo', 'Additional E-Mail') }}</option>
            <option value="NOTGROUP">{{ t('majordomo', 'Except members of group') }}</option>
            <option value="NOTUSER">{{ t('majordomo', 'Except E-Mail of user') }}</option>
            <option value="EXCLUDE">{{ t('majordomo', 'Exclude E-Mail') }}</option>
          </optgroup>
          <optgroup :label="t('majordomo', 'Moderators')">
            <option value="MODGROUP">{{ t('majordomo', 'Moderation group') }}</option>
            <option value="MODUSER">{{ t('majordomo', 'Moderation user') }}</option>
            <option value="MODEXTRA">{{ t('majordomo', 'Additional moderator E-Mail') }}</option>
          </optgroup>
          <optgroup :label="t('majordomo', 'List administrators')">
            <option value="ADMGROUP">{{ t('majordomo', 'Admin group') }}</option>
            <option value="ADMUSER">{{ t('majordomo', 'Admin user') }}</option>
          </optgroup>
        </select>
        <span v-if="[...appContext.types.user, ...appContext.types.group].indexOf(addMemberType) >= 0 && availableMembers === null" class="icon-loading-small inlineblock"></span>
        <NcSelect v-model="addMemberReference"
                  v-if="[...appContext.types.user, ...appContext.types.group].indexOf(addMemberType) >= 0 && availableMembers !== null"
                  :userSelect="true"
                  :options="availableMembers">
        </NcSelect>
        <input v-model="addMemberReference" v-else/>
        <button type="button"
                v-on:click="addMember()"
                :disabled="addMemberReference === ''"
        ><span class="icon-add inlineblock"></span>{{ t('majordomo', 'Add policy') }}
        </button>
      </div>
      <div class="mailing-list-policies" v-if="list.access.canListMembers">
        <div v-for="member in list.members" class="mailing-list-policy">
          <Avatar v-if="member.type in appContext.types.group" iconClass="icon-group-white" :isNoUser="true"/>
          <Avatar v-else-if="member.type in appContext.types.user" :user="member.reference"/>
          <Avatar v-else iconClass="icon-mail-white" :isNoUser="true"/>
          <div class="mailing-list-policy--text">
            <div v-if="member.type === 'GROUP'">{{ t('majordomo', 'Members of group') }}</div>
            <div v-else-if="member.type === 'USER'">{{ t('majordomo', 'E-Mail of user') }}</div>
            <div v-else-if="member.type === 'EXTRA'">{{ t('majordomo', 'Additional E-Mail') }}</div>
            <div v-else-if="member.type === 'NOTGROUP'">{{ t('majordomo', 'Except members of group') }}</div>
            <div v-else-if="member.type === 'NOTUSER'">{{ t('majordomo', 'Except E-Mail of user') }}</div>
            <div v-else-if="member.type === 'EXCLUDE'">{{ t('majordomo', 'Exclude E-Mail') }}</div>
            <div v-else-if="member.type === 'MODUSER'">{{ t('majordomo', 'Moderation user') }}</div>
            <div v-else-if="member.type === 'MODGROUP'">{{ t('majordomo', 'Moderation group') }}</div>
            <div v-else-if="member.type === 'MODEXTRA'">{{ t('majordomo', 'Additional moderator E-Mail') }}</div>
            <div v-else-if="member.type === 'ADMUSER'">{{ t('majordomo', 'Admin user') }}</div>
            <div v-else-if="member.type === 'ADMGROUP'">{{ t('majordomo', 'Admin group') }}</div>
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
import AppContentList from '@nextcloud/vue/dist/Components/NcAppContentList';
import AppContentDetails from '@nextcloud/vue/dist/Components/NcAppContentDetails';
import Avatar from '@nextcloud/vue/dist/Components/NcAvatar';
import EmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent';
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble';
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect';
import api from "./api";
import RequestButton from "./RequestButton";
import MailingListAccess from "./MailingListAccess";
import appContext from "./context";

export default {
  components: {
    MailingListAccess,
    RequestButton,
    AppContentList,
    AppContentDetails,
    Avatar,
    EmptyContent,
    NcCounterBubble,
    NcSelect,
  },
  data() {
    return {
      addMemberType: '',
      addMemberReference: '',
      availableMembers: null,
      availableUsers: null,
      availableGroups: null,
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
    onMemberTypeChange() {
      this.addMemberReference = '';
      this.availableMembers = null;
      if (appContext.types.group.indexOf(this.addMemberType) >= 0) {
        this.availableMembers = this.availableGroups;
        if (this.availableGroups === null) {
          api.get("/search/groups").then(groups => {
            this.availableGroups = groups;
            if (appContext.types.group.indexOf(this.addMemberType) >= 0) {
              this.availableMembers = groups;
            }
          }).catch(() => {
            OC.Notification.showTemporary(t("majordomo", "Failed to load groups."), {type: "error"});
          });
        }
      } else if (appContext.types.user.indexOf(this.addMemberType) >= 0) {
        this.availableMembers = this.availableUsers;
        if (this.availableUsers === null) {
          api.get("/search/users").then(users => {
            this.availableUsers = users;
            if (appContext.types.user.indexOf(this.addMemberType) >= 0) {
              this.availableMembers = users;
            }
          }).catch(() => {
            OC.Notification.showTemporary(t("majordomo", "Failed to load users."), {type: "error"});
          });
        }
      }
    },
    addMember() {
      const memberToAdd = {
        type: this.addMemberType,
        reference: typeof this.addMemberReference === 'string' ? this.addMemberReference : this.addMemberReference.id
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
    min-width: 400px;
    text-align: right;

    .counter-bubble__counter {
      display: inline-block;
    }
  }

  input {
    min-width: 200px;
  }

  button {
    margin-left: 400px;
  }
}

</style>