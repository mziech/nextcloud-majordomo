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
  <div v-else>
    <h2>{{ t('majordomo', 'Global Settings') }}</h2>
    <form v-on:submit.prevent="save()">
      <p class="centered-input">
        <label for="server">{{ t('majordomo', 'IMAP Server Address') }}:</label>
        <input id="server" :title="settings.imap.server" v-model="server.hostname" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <label for="encryption">{{ t('majordomo', 'Encryption') }}:</label>
        <select id="encryption" v-model="server.encryption" @change="makeDirty()">
          <option value="">{{ t('majordomo', '(default)') }}</option>
          <option value="/ssl">{{ t('majordomo', 'Use SSL') }}</option>
          <option value="/tls">{{ t('majordomo', 'Force TLS') }}</option>
          <option value="/notls">{{ t('majordomo', 'Disable TLS') }}</option>
        </select>
      </p>
      <p class="centered-input">
        <CheckboxRadioSwitch :checked.sync="server.novalidatecert" class="right" @update:checked="makeDirty()">
          {{ t('majordomo', 'Skip SSL/TLS certificate validation') }}
        </CheckboxRadioSwitch>
      </p>
      <p class="centered-input">
        <CheckboxRadioSwitch :checked.sync="server.secure" class="right" @update:checked="makeDirty()">
          {{ t('majordomo', 'Never send password in plaintext') }}
        </CheckboxRadioSwitch>
      </p>
      <p class="centered-input">
        <label for="from">{{ t('majordomo', 'E-Mail address') }}:</label>
        <input id="from" v-model="settings.imap.from" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <label for="user">{{ t('majordomo', 'IMAP Username') }}:</label>
        <input id="user" v-model="settings.imap.user" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <label for="password">{{ t('majordomo', 'IMAP Password') }}:</label>
        <input id="password" type="password" v-model="settings.imap.password" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <label for="inbox">{{ t('majordomo', 'IMAP Inbox folder name') }}:</label>
        <input id="inbox" v-model="settings.imap.inbox" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <label for="archive">{{ t('majordomo', 'IMAP Archive folder name') }}:</label>
        <input id="archive" v-model="settings.imap.archive" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <label for="errors">{{ t('majordomo', 'IMAP Errors folder name') }}:</label>
        <input id="errors" v-model="settings.imap.errors" @change="makeDirty()"/>
      </p>
      <p class="centered-input">
        <CheckboxRadioSwitch :checked.sync="settings.imap.resend" class="right" @update:checked="makeDirty()">
          {{ t('majordomo', 'Enable built-in list manager without external Majordomo') }}
          <NcCounterBubble type="highlighted">BETA</NcCounterBubble>
        </CheckboxRadioSwitch>

      </p>
      <p class="centered-input">
        <CheckboxRadioSwitch :checked.sync="settings.webhook.enabled" class="right" @update:checked="maybeGenerateToken()">
          {{ t('majordomo', 'Enable webhook to process inbound emails') }}
        </CheckboxRadioSwitch>
      </p>
      <p class="centered-input" v-if="settings.webhook.enabled">
        <label for="webhookToken">{{ t('majordomo', 'Webhook token') }}:</label>
        <input id="webhookToken" v-model="settings.webhook.token" @change="makeDirty()"/>
        <button @click="generateRandomToken()">Generate</button>
      </p>
      <h5 v-if="settings.webhook.enabled">
        {{ t('majordomo', 'Usage example inside a Procmail configuration') }}:
      </h5>
      <pre v-if="settings.webhook.enabled">
:0 c:
|curl -d 'token={{ settings.webhook.token }}' '{{ webhookPath }}'
      </pre>

      <p class="centered-input">
        <button class="primary" :disabled="saving">{{ t('majordomo', 'Save') }}</button>
        <span v-if="saving" class="icon-loading-small inlineblock"></span>
      </p>
      <p class="centered-input">
        <button type="button" :disabled="saving || testing || dirty" v-on:click="test">
          {{ t('majordomo', 'Test IMAP Connection') }}
        </button>
        <span v-if="testing" class="status-icon icon-loading-small inlineblock"></span>
        <span v-if="testSuccess === true" class="status-icon  icon-checkmark-color inlineblock"></span>
        <span v-if="testSuccess === false" class="status-icon  icon-error-color inlineblock"></span>
        <span v-if="testSuccess === false" class="test-error">{{ testError }}</span>
      </p>
    </form>
    <p class="centered-input">
      <button type="button" :disabled="saving || processing || dirty" v-on:click="process">
        {{ t('majordomo', 'Process incoming mails now') }}
      </button>
      <span v-if="processing" class="status-icon  icon-loading-small inlineblock"></span>
      <span v-if="processSuccess === true" class="status-icon  icon-checkmark-color inlineblock"></span>
      <span v-if="processSuccess === false" class="status-icon  icon-error-color inlineblock"></span>
    </p>
  </div>
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js';
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js';
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js';
import api from "./api";

function extractImapOption(server, option) {
  const pos = server.hostname.indexOf(`/${option}`);
  if (pos >= 0) {
    server.hostname = server.hostname.substring(0, pos) + server.hostname.substring(pos + option.length + 1);
    return true;
  } else {
    return false;
  }
}

export default {
  components: {
    EmptyContent,
    CheckboxRadioSwitch,
    NcCounterBubble,
  },
  name: "Settings",
  data() {
    return {
      loading: true,
      dirty: false,
      saving: false,
      testing: false,
      testSuccess: null,
      testError: "",
      processing: false,
      processSuccess: null,
      settings: {imap: {}, webhook: {}},
      server: {},
      webhookPath: location.origin + api.path("/webhook"),
    };
  },
  mounted() {
    api.get('/settings').then(settings => {
      this.settings = Object.assign({imap: {}, webhook: {}}, settings);
      const server = { hostname: this.settings.imap.server || "", encryption: "" };
      server.secure = extractImapOption(server, "secure");
      server.novalidatecert = extractImapOption(server, "novalidate-cert");
      if (extractImapOption(server, "notls")) {
        server.encryption = "/notls";
      }
      if (extractImapOption(server, "tls")) {
        server.encryption = "/tls";
      }
      if (extractImapOption(server, "ssl")) {
        server.encryption = "/ssl";
      }
      this.server = server;
      this.loading = false;
      this.dirty = false;
    });
  },
  methods: {
    save() {
      this.saving = true;
      this.settings.imap.server = this.server.hostname + this.server.encryption +
          (this.server.novalidatecert ? "/novalidate-cert" : "") +
          (this.server.secure ? "/secure" : "");
      api.post('/settings', this.settings).then(() => {
        OC.Notification.showTemporary(t("majordomo", "IMAP settings successfully updated"));
        this.saving = false;
        this.dirty = false;
      }).catch(() => {
        OC.Notification.showTemporary(t("majordomo", "Failed to store IMAP settings!"), {type: "error"});
        this.saving = false;
      });
    },
    makeDirty() {
      this.dirty = true;
    },
    maybeGenerateToken() {
      if (this.settings.webhook.enabled && !this.settings.webhook.token) {
        this.generateRandomToken();
      }
      this.makeDirty();
    },
    generateRandomToken() {
      const bytes = new Uint8Array(32);
      crypto.getRandomValues(bytes);
      this.settings.webhook.token = btoa(String.fromCodePoint(...bytes));
      this.makeDirty();
    },
    test() {
      this.testing = true;
      this.testError = "";
      api.post('/settings/test').then(result => {
        this.testSuccess = result.success;
        this.testError = result.error;
        this.testing = false;
      }).catch(() => {
        OC.Notification.showTemporary(t("majordomo", "Failed to test IMAP settings!"), {type: "error"});
        this.testSuccess = false;
        this.testing = false;
      });
    },
    process() {
      this.processing = true;
      api.post('/process-mails').then(() => {
        this.processSuccess = true;
        this.processing = false;
      }).catch(() => {
        this.processSuccess = false;
        this.processing = false;
      });
    }
  }
}
</script>

<style lang="scss" scoped>

$leftWidth: 350px;
$rightWidth: 250px;

.centered-input {
  label {
    display: inline-block;
    min-width: $leftWidth;
    text-align: right;
  }

  input, select {
    vertical-align: middle;
    min-width: $rightWidth;
  }

  .right {
    margin-left: $leftWidth;
  }

  button {
    margin-left: $leftWidth;
  }

  .test-error {
    color: var(--color-error);
    font-size: smaller;
    vertical-align: middle;
  }

  .status-icon {
    vertical-align: middle;
  }
}

h5, pre {
  margin-left: $leftWidth;
}

pre {
  font-family: monospace;
}

</style>
