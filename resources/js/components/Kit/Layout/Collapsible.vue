<script setup lang="ts">
import { ChevronDown, ChevronRight } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

/*
 * A section that opens and closes (a disclosure): an inspector's "Position", "Effects". The heading holds a real <button> (Enter and Space are the browser's) with
 * `aria-expanded` and `aria-controls` naming the content region (labelled by the button), which is hidden while closed (v-show: display none, out of the tab order and the accessibility tree),
 * not merely invisible. The chevron is ChevronRight closed and ChevronDown open, swapped rather than rotated: a host's transition setup can leave a rotating glyph
 * half-turned (a "┘"). `open` is `v-model:open`, starting from `defaultOpen` when the host does not bind it. The `summary` slot replaces the `title` text inside
 * the button (keep it to text and icons: the button is the control); `actions` sits beside the button, outside it. The height and opacity transition is off
 * under prefers-reduced-motion. Utilities and tokens only.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        defaultOpen?: boolean;
        level?: 2 | 3 | 4 | 5 | 6;
        id?: string;
    }>(),
    { title: '', defaultOpen: false, level: 3, id: undefined },
);
// Bound (`v-model:open`), the host's value wins; unbound, it starts from `defaultOpen` and keeps its own state. Nothing is emitted until the user toggles.
const model = defineModel<boolean | undefined>('open');
const local = ref(props.defaultOpen);
const open = computed<boolean>({
    get: () => model.value ?? local.value,
    set: (value) => {
        local.value = value;
        model.value = value;
    },
});
const uid = useId();
const base = props.id ?? `collapsible-${uid}`;
const contentId = `${base}-content`;
const triggerId = `${base}-trigger`;
const toggle = () => {
    open.value = !open.value;
};
</script>
<template>
    <section
        :id="id"
        class="border-border border-b last:border-b-0"
        :data-state="open ? 'open' : 'closed'"
        data-collapsible
    >
        <div class="flex items-center gap-2">
            <component
                :is="`h${level}`"
                class="m-0 min-w-0 flex-1 text-sm font-medium"
            >
                <button
                    :id="triggerId"
                    type="button"
                    class="text-foreground hover:text-foreground/80 focus-visible:outline-ring flex w-full min-w-0 items-center gap-2 rounded-sm py-2 text-left focus-visible:outline-2 focus-visible:outline-offset-2"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-controls="contentId"
                    data-collapsible-trigger
                    @click="toggle"
                >
                    <component
                        :is="open ? ChevronDown : ChevronRight"
                        class="size-4 shrink-0 opacity-70"
                        aria-hidden="true"
                        data-collapsible-chevron
                        :data-open="open || undefined"
                    />
                    <span class="min-w-0 truncate"
                        ><slot name="summary" :open="open">{{
                            title
                        }}</slot></span
                    >
                </button>
            </component>
            <slot name="actions" :open="open" />
        </div>
        <Transition
            enter-active-class="transition-[grid-template-rows,opacity] duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="grid-rows-[0fr] opacity-0"
            enter-to-class="grid-rows-[1fr] opacity-100"
            leave-active-class="transition-[grid-template-rows,opacity] duration-150 ease-in motion-reduce:transition-none"
            leave-from-class="grid-rows-[1fr] opacity-100"
            leave-to-class="grid-rows-[0fr] opacity-0"
        >
            <div
                v-show="open"
                :id="contentId"
                role="region"
                :aria-labelledby="triggerId"
                class="grid grid-rows-[1fr]"
                data-collapsible-content
            >
                <div class="min-h-0 overflow-hidden">
                    <div class="pb-3"><slot :open="open" /></div>
                </div>
            </div>
        </Transition>
    </section>
</template>
