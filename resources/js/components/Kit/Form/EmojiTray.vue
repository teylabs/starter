<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    DEFAULT_REACTIONS,
    type EmojiReaction,
    rovingIndex,
    throttleGate,
} from './emojiTray';

/*
 * A compact reaction picker (an audience reacting from a phone): a short, configurable row of emoji, not a full picker. It is a `role="toolbar"` named by `label`
 * with a roving tabindex: one tab stop, and the arrow keys, Home and End move between reactions. Each button is named by its reaction's `label` (the emoji itself
 * is hidden from screen readers). A press emits `react` with the reaction, at most once every `throttle` milliseconds across the tray (rate-limit friendly);
 * presses inside the window are dropped, not queued. The press animation is off under reduced motion. Utilities and tokens only.
 */
const props = withDefaults(
    defineProps<{
        reactions?: EmojiReaction[];
        label?: string;
        throttle?: number;
        disabled?: boolean;
    }>(),
    {
        reactions: () => DEFAULT_REACTIONS,
        label: 'Reactions',
        throttle: 1000,
        disabled: false,
    },
);
const emit = defineEmits<{ react: [reaction: EmojiReaction] }>();
const buttons = ref<HTMLButtonElement[]>([]);
const active = ref(0);
const tabStop = computed(() =>
    Math.min(active.value, Math.max(0, props.reactions.length - 1)),
);
let gate = throttleGate(props.throttle);
let gateMs = props.throttle;
function press(reaction: EmojiReaction, index: number): void {
    active.value = index;
    if (props.disabled) return;
    if (gateMs !== props.throttle) {
        gate = throttleGate(props.throttle);
        gateMs = props.throttle;
    }
    if (gate()) emit('react', reaction);
}
function move(event: KeyboardEvent, index: number): void {
    const next = rovingIndex(event.key, index, props.reactions.length);
    if (next === null) return;
    event.preventDefault();
    active.value = next;
    buttons.value[next]?.focus();
}
</script>
<template>
    <div
        role="toolbar"
        :aria-label="label"
        :aria-disabled="disabled || undefined"
        class="bg-card inline-flex flex-wrap items-center gap-1 rounded-full border p-1"
        data-emoji-tray
    >
        <button
            v-for="(reaction, index) in reactions"
            :key="reaction.emoji"
            :ref="
                (el) => {
                    if (el) buttons[index] = el as HTMLButtonElement;
                }
            "
            type="button"
            :tabindex="index === tabStop ? 0 : -1"
            :aria-label="reaction.label"
            :disabled="disabled"
            class="hover:bg-accent focus-visible:outline-ring inline-flex size-11 items-center justify-center rounded-full text-2xl transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 motion-safe:active:scale-90 motion-reduce:transition-none"
            data-emoji-reaction
            @click="press(reaction, index)"
            @keydown="move($event, index)"
        >
            <span aria-hidden="true">{{ reaction.emoji }}</span>
        </button>
    </div>
</template>
