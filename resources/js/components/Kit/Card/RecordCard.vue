<script setup lang="ts">
import Card from './Card.vue';
import CardBody from './CardBody.vue';
import CardFooter from './CardFooter.vue';
import CardHeader from './CardHeader.vue';

// One record as a card (a comparable, an order): header (title, #meta, #actions), the record's details in the body, and a muted #footer line.
withDefaults(
    defineProps<{ title?: string | null; level?: 1 | 2 | 3 | 4 | 5 | 6 }>(),
    { title: null, level: 3 },
);
</script>
<template>
    <Card data-record-card>
        <CardHeader :title="title" :level="level">
            <template v-if="$slots.title" #default
                ><slot name="title"
            /></template>
            <template v-if="$slots.leading" #leading
                ><slot name="leading"
            /></template>
            <template v-if="$slots.meta" #meta><slot name="meta" /></template>
            <template v-if="$slots.status" #status
                ><slot name="status"
            /></template>
            <template v-if="$slots.actions" #actions
                ><slot name="actions"
            /></template>
        </CardHeader>
        <CardBody v-if="$slots.default"><slot /></CardBody>
        <CardFooter v-if="$slots.footer" class="text-muted-foreground"
            ><slot name="footer"
        /></CardFooter>
    </Card>
</template>
