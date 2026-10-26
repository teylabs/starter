<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { provideSurface } from '@/components/Kit/Surface/surface';
import { readOnlyLabel } from '@/components/Kit/Form/locked';
import { useLocked } from '@/components/Kit/Form/useLocked';

/*
 * A centred pill of page actions and status. `auto` (default) sits inline under short content, floats above the viewport's foot while long content scrolls past
 * and settles at its container's end (sticky); `float` stays fixed; `inline` stays in flow. `reveal` is a selector (the page header's actions): the slot's
 * `revealed` (also `v-model:revealed`) is true while that element is out of view, so the host can repeat those actions only when they cannot be reached. Actions
 * and status come in through the slot; nothing here reads a page or bench store. The slot is for ActionBar and STATUS only: every action is a server descriptor
 * rendered by ActionBar so it overflows into the ⌄ menu on phones. A button or link slotted directly cannot overflow (it would clip), so it warns in development. It provides the inverse tone and pill shape to the controls inside.
 * Sits under dialogs and sheets (z-40), honours the bottom safe-area inset, and needs ancestors that do not clip overflow. Attributes go on the pill.
 * Below `sm` the pill keeps one row (ActionBar collapses to its main action and a ⌄ menu, which keep their size while other content truncates); dark mode uses the popover surface with a border ring.
 * Inside a locked LockedForm (the LockedForm spec) the pill shows the read-only label as status text after the slot, the reason or "View only", in the save action's place; the ActionBar inside hides edit actions itself.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ mode?: 'auto' | 'float' | 'inline'; reveal?: string; label?: string }>(), { mode: 'auto', reveal: undefined, label: 'Page actions' });
const pill = useTemplateRef<HTMLElement>('pill');
const revealed = defineModel<boolean>('revealed', { default: false });
const lock = useLocked();
provideSurface(() => ({ tone: 'inverse', shape: 'pill' }));
let observer: IntersectionObserver | undefined;
// Development guard: a control in the pill that ActionBar did not render is not supported (it cannot move into the phone menu).
function warnOnStrayControls(): void {
    if (!import.meta.env?.DEV) return;
    const stray = [...(pill.value?.querySelectorAll('button, a[href], [role="button"]') ?? [])].filter(el => !el.closest('[data-action-bar]'));
    if (stray.length) console.warn('FloatingToolbar: pass actions as descriptors to ActionBar; its slot is status only. Found a control outside ActionBar:', stray[0]);
}
onMounted(() => {
    warnOnStrayControls();
    const target = props.reveal ? document.querySelector(props.reveal) : null;
    if (!target || !('IntersectionObserver' in window)) return;
    observer = new IntersectionObserver(([entry]) => { revealed.value = !entry.isIntersecting; }, { threshold: 0 });
    observer.observe(target);
});
onBeforeUnmount(() => observer?.disconnect());
</script>
<template>
    <div
        data-floating-toolbar
        :data-mode="mode"
        :class="[
            'pointer-events-none z-40 flex justify-center px-4',
            mode === 'float' && 'fixed inset-x-0 bottom-[calc(1rem+env(safe-area-inset-bottom))]',
            mode === 'auto' && 'sticky bottom-[calc(1rem+env(safe-area-inset-bottom))] mt-6',
            mode === 'inline' && 'mt-6',
        ]"
    >
        <div
            ref="pill"
            v-bind="$attrs"
            role="toolbar"
            :aria-label="label"
            class="pointer-events-auto flex max-w-full flex-wrap max-sm:flex-nowrap max-sm:*:min-w-0 max-sm:[&>:not(.shrink-0)]:shrink max-sm:[&>:not(.shrink-0)]:truncate items-center justify-center gap-x-3 gap-y-2 rounded-full bg-foreground/87 px-3 py-2 text-sm text-background shadow-lg ring-1 ring-white/15 backdrop-blur-md supports-[not(backdrop-filter:blur(1px))]:bg-foreground [@media(prefers-reduced-transparency:reduce)]:bg-foreground [@media(prefers-reduced-transparency:reduce)]:backdrop-blur-none [--kit-paper:transparent] [--kit-outline:color-mix(in_oklab,currentColor_20%,transparent)] [--kit-outline-foreground:currentColor] [--kit-outline-muted:currentColor] [--kit-outline-wash:color-mix(in_oklab,currentColor_10%,transparent)] dark:bg-popover/87 dark:text-popover-foreground dark:ring-border dark:supports-[not(backdrop-filter:blur(1px))]:bg-popover dark:[@media(prefers-reduced-transparency:reduce)]:bg-popover"
        >
            <slot :revealed="revealed" />
            <span v-if="lock.locked" class="text-sm" data-floating-toolbar-readonly>{{ readOnlyLabel(lock) }}</span>
        </div>
    </div>
</template>
