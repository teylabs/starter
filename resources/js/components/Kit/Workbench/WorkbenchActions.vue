<script setup lang="ts">
import { useModalStack } from '@inertiaui/modal-vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import HeaderActions from '@/components/Kit/Action/HeaderActions.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import StickyBar from '@/components/Kit/Layout/StickyBar.vue';
import { SAVE_LABELS, savedAgo } from './workbench';
import { useWorkbench } from './useWorkbench';
import WorkbenchZoom from './WorkbenchZoom.vue';

/*
 * The Workbench's actions bar, a StickyBar (so it registers with useStickyBars and toasts stack clear of it): the save state (Saving… / Saved / Unsaved changes,
 * announced politely, the header status region), a conflict when the work changed elsewhere, the zoom controls, the inspector's opener at lg, and the app's
 * actions as kit action descriptors through HeaderActions (the page header's split, headerSplit: three or fewer with no `priority` stay ActionBar's, the first
 * filled, as before; more fold into a primary, two secondaries and More, only the primary below `sm`), so `Toast::success(...)->from('publish')` finds its trigger. Actions it does not navigate to are emitted. `save-status="none"`
 * leaves the save state and the conflict to the app (its own form bar's status in #actions): no kit status region renders, and `wb.saveState` is still there to read.
 * A Workbench with `:form` puts its errors summary in #summary and its Save in #save, last, as the bar's primary; `primary` false then keeps the app's actions secondary.
 */
const props = withDefaults(
    defineProps<{
        workbenchKey: string;
        actions?: ActionDescriptor[];
        zoom?: boolean;
        label?: string;
        inspectorLabel: string;
        conflictLabel?: string;
        saveStatus?: 'kit' | 'none';
        pinned?: boolean;
        primary?: boolean;
    }>(),
    {
        primary: true,
        saveStatus: 'kit',
        pinned: false,
        actions: () => [],
        zoom: false,
        label: 'Workbench actions',
        conflictLabel:
            'Someone else changed this. Reload to see their version before you save again.',
    },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const wb = useWorkbench(props.workbenchKey);
// Saved reads "Saved · 2 min ago", refreshed each half minute.
const now = ref(Date.now());
const clock = setInterval(() => (now.value = Date.now()), 30000);
onBeforeUnmount(() => clearInterval(clock));
const status = computed(() => {
    if (wb.saveState === 'idle') return '';
    const ago =
        wb.saveState === 'saved' ? savedAgo(wb.savedAt, now.value) : null;
    return ago ? `${SAVE_LABELS.saved} · ${ago}` : SAVE_LABELS[wb.saveState];
});
// Under an open modal the bar is inert: its actions can't be clicked or focused through the dialog.
const modals = useModalStack();
const covered = computed(() => modals.stack.value.length > 0);
</script>
<template>
    <StickyBar
        :pinned="pinned"
        :aria-label="label"
        role="region"
        class="col-span-full"
        :inert="covered || undefined"
        data-workbench-actions
    >
        <p
            v-if="saveStatus === 'kit'"
            role="status"
            aria-live="polite"
            class="text-muted-foreground min-w-0 text-sm"
            :data-save-state="wb.saveState"
        >
            {{ status }}
        </p>
        <p
            v-if="saveStatus === 'kit' && wb.saveState === 'conflict'"
            role="alert"
            class="text-destructive-text min-w-0 text-sm font-medium"
            data-workbench-conflict
        >
            {{ conflictLabel }}
        </p>
        <slot name="status" />
        <slot name="summary" />
        <div
            class="ml-auto flex min-w-0 flex-wrap items-center justify-end gap-2"
        >
            <WorkbenchZoom v-if="zoom" :workbench-key="workbenchKey" />
            <button
                v-if="wb.mode === 'drawer'"
                type="button"
                class="border-input bg-background hover:bg-accent h-9 rounded-md border px-3 text-sm font-medium shadow-xs"
                :aria-expanded="wb.drawerOpen"
                data-workbench-open-inspector
                @click="wb.drawerOpen = true"
            >
                {{ inspectorLabel }}
            </button>
            <slot />
            <HeaderActions
                v-if="actions.length"
                :actions="actions"
                :primary="primary"
                :busy="wb.saveState === 'saving' || wb.reordering"
                @action="emit('action', $event)"
            />
            <slot name="save" />
        </div>
    </StickyBar>
</template>
