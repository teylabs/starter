<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import ActionMenuItems from '@/components/Kit/Action/ActionMenuItems.vue';
import { useActionRunner } from '@/components/Kit/Action/actionRunner';
import ActionSegment from '@/components/Kit/Action/ActionSegment.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import {
    headerSplit,
    keepsActionBar,
} from '@/components/Kit/Action/headerSplit';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import {
    inverseControl,
    inverseFilled,
    useSurface,
} from '@/components/Kit/Surface/surface';
import { withoutEditActions } from '@/components/Kit/Form/locked';
import { useLocked } from '@/components/Kit/Form/useLocked';

/*
 * A page header's actions, split by headerSplit: one filled primary, up to two outline secondaries, then a "More" ⌄ menu (ActionMenuItems: deferred select,
 * intents, disabled with its reason). Three or fewer actions with no declared `priority` keep today's path: an ActionBar with the same props, so the markup is
 * the same as a page passing those descriptors to ActionBar itself. Below `sm` (a media query, not the container's width) only the primary stays visible and the
 * secondaries move into More. Like ActionBar, a host that listens to `action` runs every non-navigating action itself; otherwise this runs it: its `confirm`
 * first (typed when it has `typed`, sent as `confirmation`), then an Inertia visit. Inside a locked LockedForm an `intent: 'edit'` descriptor is hidden. On the
 * FloatingToolbar's inverse pill the buttons take the pill's tokens and radius. Nothing here checks a permission.
 */
const props = withDefaults(
    defineProps<{
        actions: ActionDescriptor[];
        busy?: boolean;
        dialogOpen?: boolean;
        primary?: boolean;
    }>(),
    { busy: false, dialogOpen: false, primary: false },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const { hostRuns, pending, asking, run, visit, running, isRunning } =
    useActionRunner(emit);
const blocked = computed(() => props.busy || running.value !== null);
// The ActionBar path gets a listener only when the host has one, so ActionBar runs actions itself exactly as it would in the host's own slot.
const barListeners = computed(() =>
    hostRuns()
        ? { onAction: (action: ActionDescriptor) => emit('action', action) }
        : {},
);
const lock = useLocked();
const shown = computed(() => withoutEditActions(props.actions, lock.locked));
const legacy = computed(() => keepsActionBar(props.actions));

// Phone: below Tailwind's `sm` (40rem). Unset until mounted, so a server render shows the wide split.
const phone = ref(false);
let media: MediaQueryList | undefined;
const onMedia = () => {
    phone.value = !!media?.matches;
};
onMounted(() => {
    if (typeof window === 'undefined' || !window.matchMedia) return;
    media = window.matchMedia('(max-width: 39.99rem)');
    onMedia();
    media.addEventListener('change', onMedia);
});
onBeforeUnmount(() => media?.removeEventListener('change', onMedia));
const split = computed(() => headerSplit(shown.value, { phone: phone.value }));

const surface = useSurface();
const pill = computed(() => surface.value.shape === 'pill');
const inverse = computed(() => surface.value.tone === 'inverse');
function button(filled: boolean): string[] {
    return [
        'inline-flex min-w-0 shrink-0 items-center justify-center gap-1.5 text-sm font-medium whitespace-nowrap no-underline transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50',
        pill.value ? 'h-9 rounded-full px-3.5' : 'h-9 rounded-md px-3',
        inverse.value
            ? filled
                ? inverseFilled
                : inverseControl
            : filled
              ? 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90'
              : 'border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:hover:bg-input/50',
    ];
}
const holdFocus = (event: Event) => {
    if (props.dialogOpen) event.preventDefault();
};
</script>
<template>
    <ActionBar
        v-if="legacy"
        :actions="actions"
        :busy="busy"
        :dialog-open="dialogOpen"
        :primary="primary"
        v-bind="barListeners"
    />
    <div
        v-else-if="shown.length"
        class="flex min-w-0 flex-nowrap items-center gap-2"
        data-header-actions
        :data-phone="phone || undefined"
    >
        <ActionSegment
            v-if="split.primary"
            :action="split.primary"
            :pending="isRunning(split.primary)"
            :busy="blocked"
            :class="[button(true), 'primary-action']"
            data-header-action="primary"
            @action="run($event)"
        />
        <ActionSegment
            v-for="action in split.secondary"
            :key="action.key ?? action.url"
            :action="action"
            :pending="isRunning(action)"
            :busy="blocked"
            :class="button(false)"
            data-header-action="secondary"
            @action="run($event)"
        />
        <DropdownMenu v-if="split.overflow.length">
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    :class="[button(false), 'pr-2.5']"
                    data-header-more
                    data-action-menu
                >
                    More<ChevronDown class="size-4" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                data-header-more-content
                @close-auto-focus="holdFocus"
            >
                <ActionMenuItems
                    :actions="split.overflow"
                    :busy="blocked"
                    :on-action="run"
                />
            </DropdownMenuContent>
        </DropdownMenu>
        <Dialog
            v-if="pending?.confirm"
            v-model:open="asking"
            :variant="pending.destructive ? 'danger' : 'neutral'"
            :title="pending.confirm.title"
            :description="pending.confirm.description"
            :confirm-label="pending.confirm.label"
            :confirm-text="pending.confirm.typed ?? null"
            @confirm="(confirmation) => pending && visit(pending, confirmation)"
        />
    </div>
</template>
