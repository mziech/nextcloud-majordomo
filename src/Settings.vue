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
    <div>
        <h2>{{ t('majordomo', 'Global Settings') }}</h2>
        <div v-if="loading" class="centered-loading">
            <span class="icon-loading"></span>
            {{ t('majordomo', 'Loading ...') }}
        </div>
        <form v-if="!loading" v-on:submit.prevent="save()">
            <p class="centered-input">
                <label for="server">{{ t('majordomo', 'IMAP Server Address') }}:</label>
                <input id="server" v-model="settings.imap.server"/>
            </p>
            <p class="centered-input">
                <label for="from">{{ t('majordomo', 'E-Mail address') }}:</label>
                <input id="from" v-model="settings.imap.from"/>
            </p>
            <p class="centered-input">
                <label for="user">{{ t('majordomo', 'IMAP Username') }}:</label>
                <input id="user" v-model="settings.imap.user"/>
            </p>
            <p class="centered-input">
                <label for="password">{{ t('majordomo', 'IMAP Password') }}:</label>
                <input id="password" type="password" v-model="settings.imap.password"/>
            </p>
            <p class="centered-input">
                <label for="inbox">{{ t('majordomo', 'IMAP Inbox folder name') }}:</label>
                <input id="inbox" v-model="settings.imap.inbox"/>
            </p>
            <p class="centered-input">
                <label for="archive">{{ t('majordomo', 'IMAP Archive folder name') }}:</label>
                <input id="archive" v-model="settings.imap.archive"/>
            </p>
            <p class="centered-input">
                <label for="errors">{{ t('majordomo', 'IMAP Errors folder name') }}:</label>
                <input id="errors" v-model="settings.imap.errors"/>
            </p>
            <p class="centered-input">
                <button class="primary" :disabled="saving">{{ t('majordomo', 'Save') }}</button>
                <span v-if="saving" class="icon-loading-small inlineblock"></span>
            </p>
            <p class="centered-input">
                <button type="button" :disabled="saving || testing" v-on:click="test">{{ t('majordomo', 'Test IMAP Connection') }}</button>
                <span v-if="testing" class="icon-loading-small inlineblock"></span>
                <span v-if="testSuccess === true" class="icon-checkmark-color inlineblock"></span>
                <span v-if="testSuccess === false" class="icon-error-color inlineblock"></span>
            </p>
        </form>
        <p class="centered-input">
            <button type="button" :disabled="saving || processing" v-on:click="process">{{ t('majordomo', 'Process incoming mails now') }}</button>
            <span v-if="processing" class="icon-loading-small inlineblock"></span>
            <span v-if="processSuccess === true" class="icon-checkmark-color inlineblock"></span>
            <span v-if="processSuccess === false" class="icon-error-color inlineblock"></span>
        </p>
    </div>
</template>

<script>
    import api from "./api";

    export default {
        name: "Settings",
        data() {
            return {
                loading: true,
                saving: false,
                testing: false,
                testSuccess: null,
                processing: false,
                processSuccess: null,
                settings: {imap: {}}
            };
        },
        mounted() {
            api.get('/settings').then(settings => {
                this.settings = Object.assign({imap: {}}, settings);
                this.loading = false;
            });
        },
        methods: {
            save() {
                this.saving = true;
                api.post('/settings', this.settings).then(() => {
                    OC.Notification.showTemporary(t("majordomo", "IMAP settings successfully updated"));
                    this.saving = false;
                }).catch(() => {
                    OC.Notification.showTemporary(t("majordomo", "Failed to store IMAP settings!"), {type: "error"});
                    this.saving = false;
                });
            },
            test() {
                this.testing = true;
                api.post('/settings/test').then(result => {
                    this.testSuccess = result.success;
                    this.testing = false;
                }).catch(() => {
                    OC.Notification.showTemporary(t("majordomo", "Failed to test IMAP settings!"), {type: "error"});
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
