<script setup lang="ts">
// Deprecated: Checkbox is now InputCheckbox. This alias passes its attributes, v-model and label slot through, and is removed in 0.4.
import type { Component } from 'vue';
import InputCheckbox from './InputCheckbox.vue';

defineOptions({ inheritAttrs: false });
// Typed as a plain component: the props arrive through $attrs, so they are checked where InputCheckbox is used, not here.
const target: Component = InputCheckbox;
</script>
<template>
    <component :is="target" v-bind="$attrs"><slot /></component>
</template>
