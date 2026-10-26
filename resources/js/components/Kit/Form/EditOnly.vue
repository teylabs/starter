<script setup lang="ts">
import { useLocked } from './useLocked';

// A renderless wrapper for caller-owned edit actions (the LockedForm spec): its slot renders only while the nearest LockedForm is editable, and nothing at all
// (no placeholder, no reserved space) while locked. Copy, download, preview and links are not edit actions and stay outside it.
const state = useLocked();
</script>
<template>
    <slot v-if="!state.locked" />
</template>
