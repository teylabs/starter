<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref, watch } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import Input from '@/components/Kit/Form/Input.vue';

/*
 * A button that copies `value` to the clipboard. Success is claimed only after the browser's write resolves, and spoken once through a polite status; a refused
 * or missing clipboard says so and shows the value in a read-only input, selected on focus, so it can still be copied by hand. A changed `value` resets it.
 * `label` names the button ("Copy DKIM name"); `icon` makes it a small icon button with the label as its accessible name and tooltip; `copiedLabel` replaces
 * the label while copied. `variant` is the kit Button's secondary (default) or ghost. Marks `data-copy-button` and `data-state` (idle, copying, copied, failed).
 */
const props = withDefaults(
    defineProps<{
        value: string;
        label?: string;
        copiedLabel?: string;
        icon?: boolean;
        variant?: 'secondary' | 'ghost';
    }>(),
    {
        label: 'Copy',
        copiedLabel: undefined,
        icon: false,
        variant: 'secondary',
    },
);
const state = ref<'idle' | 'copying' | 'copied' | 'failed'>('idle');
// A press that resolves after `value` changed claims nothing about the new value.
let revision = 0;
watch(
    () => props.value,
    () => {
        revision++;
        state.value = 'idle';
    },
);
async function copy(): Promise<void> {
    if (state.value === 'copying') return;
    const attempt = revision;
    state.value = 'copying';
    try {
        if (!navigator.clipboard?.writeText)
            throw new Error('clipboard unavailable');
        await navigator.clipboard.writeText(props.value);
        if (attempt === revision) state.value = 'copied';
    } catch {
        if (attempt === revision) state.value = 'failed';
    }
}
</script>
<template>
    <span
        class="inline-grid min-w-0 gap-2"
        data-copy-button
        :data-state="state"
    >
        <span class="inline-flex items-center gap-2">
            <Button
                v-if="icon"
                type="button"
                :variant="variant"
                size="icon"
                class="size-7 shrink-0"
                :aria-label="
                    state === 'copied' ? (copiedLabel ?? label) : label
                "
                :title="label"
                :disabled="state === 'copying'"
                @click="copy"
            >
                <Check
                    v-if="state === 'copied'"
                    class="size-4"
                    aria-hidden="true"
                />
                <Copy v-else class="size-4" aria-hidden="true" />
            </Button>
            <Button
                v-else
                type="button"
                :variant="variant"
                :disabled="state === 'copying'"
                @click="copy"
                >{{
                    state === 'copied' && copiedLabel ? copiedLabel : label
                }}</Button
            >
            <span role="status" aria-live="polite" class="sr-only">{{
                state === 'copied'
                    ? 'Copied.'
                    : state === 'failed'
                      ? 'Could not copy. Select the text to copy it, or try again.'
                      : ''
            }}</span>
        </span>
        <Input
            v-if="state === 'failed'"
            :model-value="value"
            :aria-label="`${label}: select to copy`"
            readonly
            @focus="($event.target as HTMLInputElement).select()"
        />
    </span>
</template>
