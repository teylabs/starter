<script setup lang="ts">
import Card from './Card.vue';
import CardBody from './CardBody.vue';
import CardFooter from './CardFooter.vue';
import CardHeader from './CardHeader.vue';

// A panel of one thing with its actions (a settings group, a details panel): a muted header with an optional #icon and #actions, the content (or #body for
// your own parts), and a sticky #footer.
withDefaults(defineProps<{ title: string; level?: 1 | 2 | 3 | 4 | 5 | 6 }>(), {
    level: 3,
});
</script>
<template>
    <Card data-action-card>
        <CardHeader :title="title" :level="level" tone="muted">
            <template v-if="$slots.icon" #avatar
                ><span class="text-muted-foreground" aria-hidden="true"
                    ><slot name="icon" /></span
            ></template>
            <template v-if="$slots.actions" #actions
                ><slot name="actions"
            /></template>
        </CardHeader>
        <slot name="body"
            ><CardBody class="overflow-auto"><slot /></CardBody
        ></slot>
        <CardFooter v-if="$slots.footer" sticky
            ><slot name="footer"
        /></CardFooter>
    </Card>
</template>
