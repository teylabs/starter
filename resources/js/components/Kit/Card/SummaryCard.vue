<script setup lang="ts">
import Card from './Card.vue';
import CardBody from './CardBody.vue';
import CardHeader from './CardHeader.vue';

// A short summary with a call to action (a plan, a setting, a next step): title and #description on one side, #actions on the other, more below.
withDefaults(defineProps<{ title: string; level?: 1 | 2 | 3 | 4 | 5 | 6 }>(), {
    level: 3,
});
</script>
<template>
    <Card data-summary-card>
        <CardHeader :title="title" :level="level">
            <template v-if="$slots.description" #meta
                ><p class="max-w-xl"><slot name="description" /></p
            ></template>
            <template v-if="$slots.actions" #actions
                ><slot name="actions"
            /></template>
        </CardHeader>
        <CardBody v-if="$slots.default"><slot /></CardBody>
    </Card>
</template>
