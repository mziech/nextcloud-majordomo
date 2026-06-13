<script setup>
const model = defineModel({
  type: Number
});
</script>

<template>
  <select v-model="model">
    <option :value="0">{{ t('majordomo', 'Nobody') }}</option>
    <option :value="1">{{ t('majordomo', 'Administrators') }}</option>
    <option :value="2">{{ t('majordomo', 'Moderators') }}</option>
    <option :value="4">{{ t('majordomo', 'Members') }}</option>
    <option :value="6">{{ t('majordomo', 'Everyone') }}</option>
  </select>
</template>

<style scoped>

</style>