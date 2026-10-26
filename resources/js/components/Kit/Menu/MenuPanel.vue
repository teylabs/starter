<script setup lang="ts">
import { nextTick, provide, ref, watch, type HTMLAttributes } from 'vue';
import { DropdownMenu, DropdownMenuContent } from '@/components/ui/dropdown-menu';
import { alignThatFits, focusNeighbour, isFocusableLoose, menuPanelKey, type MenuAlign } from '@/components/Kit/Menu/useMenuPanel';

// INTERNAL to the menu family (MenuPanel.md), written from the spec on the shadcn-vue dropdown menu: opening and closing, placement (below, flipping above; `flipAlign` swaps the edge when it would overflow), the keyboard, focus return and the hold-open
// rule for an item's dialog. The panel is not modal. A wrapper passes its own width, radius, background, shadow and ring in `contentClass`; the dark popover surface is here.
const props = withDefaults(defineProps<{ align?: MenuAlign; flipAlign?: boolean; sideOffset?: number; skid?: number; contentClass?: HTMLAttributes['class']; animated?: boolean }>(), { align: 'start', flipAlign: false, sideOffset: 10, skid: 0, contentClass: undefined, animated: false });
const open = ref(false);
const held = ref(0);
const fitted = ref<MenuAlign>(props.align);
let trigger: HTMLElement | null = null;
let outside: EventTarget | null = null;
let skipReturn = false;

// The open panel, found through the trigger it names (`aria-labelledby`); Reka owns both ids.
const panel = () => (trigger?.id ? document.querySelector<HTMLElement>(`[role=menu][aria-labelledby="${trigger.id}"]`) : null);

function focusItem(which: 'first' | 'last') {
    nextTick(() => {
        const items = [...(panel()?.querySelectorAll<HTMLElement>('[role=menuitem]:not([data-disabled])') ?? [])];
        (which === 'last' ? items[items.length - 1] : items[0])?.focus();
    });
}

provide(menuPanelKey, {
    close: () => (open.value = false),
    hold: () => held.value++,
    release: () => (held.value = Math.max(0, held.value - 1)),
    isHeld: () => held.value > 0,
    // ArrowUp on a closed trigger opens the menu with the last item focused.
    openFromEnd(event) {
        if (open.value) return;
        event.preventDefault();
        open.value = true;
        focusItem('last');
    },
    setTrigger: (el) => (trigger = el),
});

// Each open measures the panel and takes the edge that fits (`alignThatFits`); closing drops any hold.
watch(open, async (now) => {
    if (!now) {
        held.value = 0;
        fitted.value = props.align;

        return;
    }

    if (!props.flipAlign) return;

    await nextTick();
    const open = panel();
    if (open && trigger) fitted.value = alignThatFits(props.align, trigger.getBoundingClientRect(), open.getBoundingClientRect().width, document.documentElement.clientWidth);
});

function keep(event: Event) {
    if (held.value > 0) event.preventDefault();
}

function pressOutside(event: Event) {
    if (held.value > 0) return event.preventDefault();
    outside = (event as CustomEvent<{ originalEvent?: Event }>).detail?.originalEvent?.target ?? null;
}

// Tab closes the menu and moves focus past the trigger (Shift+Tab before it); it never lands on the next item.
function tab(event: KeyboardEvent) {
    event.preventDefault();
    skipReturn = true;
    open.value = false;
    const from = trigger;
    if (from) nextTick(() => focusNeighbour(from, event.shiftKey ? -1 : 1));
}

// Focus returns to the trigger after a choice or Escape, and after a press on plain content; a press on something focusable keeps its focus.
function returnFocus(event: Event) {
    const plain = outside !== null && !isFocusableLoose(outside as Element);
    outside = null;
    if (skipReturn) {
        skipReturn = false;

        return event.preventDefault();
    }
    if (plain) {
        event.preventDefault();
        // After the press: the browser's own mousedown on plain content would otherwise drop focus to the body again.
        setTimeout(() => trigger?.focus(), 0);
    }
}
</script>
<template>
    <DropdownMenu v-model:open="open" :modal="false">
        <slot name="trigger" />
        <DropdownMenuContent
            :class="[contentClass, 'dark:bg-popover dark:text-popover-foreground dark:divide-border dark:ring-white/10', animated ? 'origin-top-right data-[state=closed]:duration-75 data-[state=open]:duration-100 data-[state=closed]:ease-in data-[state=open]:ease-out' : '']"
            :align="fitted"
            :side-offset="sideOffset"
            :align-offset="fitted === 'end' ? -skid : skid"
            @escape-key-down="keep"
            @focus-outside="keep"
            @interact-outside="keep"
            @pointer-down-outside="pressOutside"
            @close-auto-focus="returnFocus"
            @keydown.tab="tab"
        >
            <slot :close="() => (open = false)" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
