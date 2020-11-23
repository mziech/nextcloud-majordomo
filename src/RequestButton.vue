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
        <button class="secondary" type="button" :disabled="disabled" @click="startRequest()">
            <slot></slot>
        </button>
        <span v-if="outboundIcon" :class="outboundIcon + ' inlineblock'"></span>
        <span v-if="inboundIcon" :class="inboundIcon + ' inlineblock'"></span>
        <Modal  v-if="pendingChanges" :can-close="true" @close="pendingChanges = null">
            <div class="pending-changes-content">
                <h3>{{ t('majordomo', 'Apply pending changes') }}</h3>
                <p>{{ t('majordomo', 'Please acknowledge to apply the following pending changes:') }}</p>
                <ul>
                    <li v-for="email in pendingChanges.toDelete" class="pending-change-delete">
                        {{ t('majordomo', 'Unsubscribe: {email}', { email }) }}
                    </li>
                    <li v-for="email in pendingChanges.toAdd" class="pending-change-add">
                        {{ t('majordomo', 'Subscribe: {email}', { email }) }}
                    </li>
                </ul>
                <center>
                    <button class="primary" type="button" @click="startRequest(true)">
                        {{ t('majordomo', 'Acknowledge') }}
                    </button>
                    <button class="secondary" type="button" @click="pendingChanges = null">
                        {{ t('majordomo', 'Cancel') }}
                    </button>
                </center>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
    .pending-changes-content {
        padding: 10px;
    }

    .pending-change-delete {
        color: var(--color-error);
    }
    .pending-change-add {
        color: var(--color-success);
    }
</style>

<script>
    import api from "./api";
    import { Modal } from '@nextcloud/vue/dist/Components/Modal'

    export default {
        name: "RequestButton",
        components: {
            Modal,
        },
        props: {
            listId: Number,
            action: String,
            disabled: Boolean,
        },
        data() {
            return {
                outboundIcon: null,
                inboundIcon: null,
                requestId: null,
                pendingChanges: null,
            };
        },
        methods: {
            async startRequest(ack=false) {
                this.outboundIcon = 'icon-loading-small';
                this.inboundIcon = null;
                this.pendingChanges = null;

                if (this.action === 'sync' && !ack) {
                    this.pendingChanges = await api.get(`/lists/${this.listId}/pending`);
                    this.outboundIcon = null;
                    return;
                }

                try {
                    const data = await api.post(`/lists/${this.listId}/requests/${this.action}`);
                    this.outboundIcon = 'icon-checkmark-color';
                    this.inboundIcon = 'icon-loading-small';
                    this.requestId = data.id;
                    setTimeout(this.checkRequest.bind(this), 2000);
                } catch (e) {
                    this.outboundIcon = 'icon-error-color';
                    this.$emit('error');
                    console.error(`Error while triggering request ${this.action} for list id ${this.listId}`, e);
                }
            },
            async checkRequest() {
                try {
                    const data = await api.get(`/requests/${this.requestId}`);
                    if (data.done) {
                        this.inboundIcon = 'icon-checkmark-color';
                        this.$emit('success');
                    } else if (data.error) {
                        this.inboundIcon = 'icon-error-color';
                        this.$emit('error');
                    }
                } catch(e) {
                    console.error(`Error while checking request ${this.action} for list id ${this.listId}`, e);
                }
                setTimeout(this.checkRequest.bind(this), 10000);
            }
        }
    }
</script>
