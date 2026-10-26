<script setup lang="ts">
import { SettingsFrame } from '@kit/ui';

// The persistent layout of the Account pages (the nearest folder Layout.vue): the section navigation sits beside the page, and stays mounted while you move between sections.
// Which sections exist, and which is current, is the page's `layout.navigation`, decided on the server.
</script>
<template>
    <SettingsFrame><slot /></SettingsFrame>
</template>
