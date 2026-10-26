<script setup lang="ts">
import { computed, useId } from 'vue';
import { implicitSubmit } from './locked';
import { provideLocked } from './useLocked';

// A form someone may read but not change (the LockedForm spec). It wraps one or more FormCards, or a lone FormFieldset, and locks them when `save`, the page's
// save descriptor, is `null`: the server decides, never the client. Locked, every kit control inside turns read-only through the context (never `disabled`),
// kit edit actions and `intent: 'edit'` descriptors are hidden, caller buttons in EditOnly vanish, each FormCard shows the notice in place of FormActions, and
// nothing submits: Enter in a field does nothing and a `<form>` inside never sends. With a descriptor it renders its slot unchanged. A locked ancestor locks
// every nested area. The root describes itself by the first notice. `quantity="one"` picks the singular notice, `notice` replaces it, `reason` adds a muted line.
const props = withDefaults(defineProps<{ save: object | null; quantity?: 'many' | 'one'; notice?: string; reason?: string | null }>(), { quantity: 'many', notice: undefined, reason: null });
const context = provideLocked(() => props, `${useId()}-notice`);
const locked = computed(() => context.state.value.locked);
const describedBy = computed(() => (locked.value && context.claimed.value ? context.noticeId : undefined));
// Nothing submits while locked: a submit event is stopped before the form sees it, and Enter in a single-line input loses its implicit submission.
function guardSubmit(event: Event) {
    if (!locked.value) return;
    event.preventDefault();
    event.stopPropagation();
}
function guardKey(event: KeyboardEvent) {
    const target = event.target as HTMLElement | null;
    if (locked.value && target && implicitSubmit(event.key, target.tagName, target.getAttribute('type'))) event.preventDefault();
}
</script>
<template>
    <div data-locked-form :data-locked="locked || undefined" :aria-describedby="describedBy" @submit.capture="guardSubmit" @keydown.capture="guardKey"><slot :locked="locked" /></div>
</template>
