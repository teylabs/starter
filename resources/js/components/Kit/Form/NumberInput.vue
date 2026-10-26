<script setup lang="ts">
// Deprecated: NumberInput is now InputNumber. This alias passes its attributes and v-model through, and is removed in 0.4.
import type { Component } from 'vue';
import InputNumber from './InputNumber.vue';

defineOptions({ inheritAttrs: false });
// Typed as a plain component: the props arrive through $attrs, so they are checked where InputNumber is used, not here.
const target: Component = InputNumber;
</script>
<template>
    <component :is="target" v-bind="$attrs" />
</template>
