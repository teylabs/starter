<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import ActionMenuItems from '@/components/Kit/Action/ActionMenuItems.vue';
import { useActionRunner } from '@/components/Kit/Action/actionRunner';
import ActionSegment from '@/components/Kit/Action/ActionSegment.vue';
import ButtonGroup from '@/components/Kit/Action/ButtonGroup.vue';
import { fitActions, sameMeasure } from '@/components/Kit/Action/actionFit';
import {
    layoutActions,
    placeActions,
    type ActionDescriptor,
} from '@/components/Kit/Action/actionPlacement';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import Toolbar from '@/components/Kit/Layout/Toolbar.vue';
import {
    inverseControl,
    inverseFilled,
    useSurface,
} from '@/components/Kit/Surface/surface';
import { withoutEditActions } from '@/components/Kit/Form/locked';
import { useLocked } from '@/components/Kit/Form/useLocked';

/*
 * Server action descriptors as button groups. With declared `group`s each group (or a standalone action) is one ButtonGroup, its `menu` actions behind a ⌄;
 * without, the placement rule decides. Dialog intent opens the route modal, document intent a new tab, ordinary GETs navigate; every other action is emitted for
 * the host to run (confirmation included). A host that does not listen to `action` lets the bar run it: its `confirm` first (a typed one when it has `typed`, sent
 * as `confirmation` for the server to re-check), then an Inertia visit. Nothing here checks a permission. `busy` blocks all but a standalone document link; `dialogOpen` holds menu focus for the
 * dialog the host opens (pill surface). On an inverse pill surface (FloatingToolbar) it keeps segments of its own instead of ButtonGroup: the pill's tokens do restyle a
 * ButtonGroup (tone), but ButtonGroup has no hook for the ⌄ trigger's name, the focus hold or the pill's radius and 36px height, and has no filled segment of its own: `filled` fills the leading segment there (opt-in; `primary` alone keeps it semibold on the outline). When its full
 * layout does not fit its own width (a ResizeObserver on its root, never the viewport or a media query: a Workbench's actions bar is narrow at any viewport) the bar
 * keeps one row: the main action and a ⌄ menu with every other action, in order (fitActions). The full layout stays laid out, invisible and inert, so its natural
 * width is still measured and the bar returns to it when there is room. `wrap` (opt-in, for a phone header) never compacts: nothing goes behind a More menu, every action
 * (each its own button without declared groups; declared groups and their `menu` kept) stays visible and the row wraps onto as many rows as it needs, so there is no
 * hidden full layout and nothing is measured. Inside a locked LockedForm (the LockedForm spec) an `intent: 'edit'` descriptor is hidden; the server should not send it, this covers one that slips through.
 */
const props = withDefaults(
    defineProps<{
        actions: ActionDescriptor[];
        busy?: boolean;
        dialogOpen?: boolean;
        primary?: boolean;
        filled?: boolean;
        fillAll?: boolean;
        dividerClass?: string;
        triggerClass?: string;
        menuTriggerLabel?: string | null;
        wrap?: boolean;
        itemClass?: string;
    }>(),
    {
        busy: false,
        dialogOpen: false,
        primary: false,
        filled: false,
        fillAll: false,
        dividerClass: undefined,
        triggerClass: undefined,
        menuTriggerLabel: undefined,
        wrap: false,
        itemClass: undefined,
    },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const { pending, asking, run, visit, running, isRunning } =
    useActionRunner(emit);
const blocked = computed(() => props.busy || running.value !== null);
// The menu rows hand their action here after the menu has closed and unmounted them; ActionBar itself stays mounted, so its emit reaches the host.
const surface = useSurface();
const lock = useLocked();
const shown = computed(() => withoutEditActions(props.actions, lock.locked));
const parts = computed(
    () =>
        layoutActions(shown.value)?.map((part) =>
            part.kind === 'group'
                ? part
                : {
                      key: part.key,
                      inline: [part.action],
                      menu: [] as ActionDescriptor[],
                  },
        ) ??
        (props.wrap
            ? shown.value.map((action, index) => ({
                  key: `wrap-${index}`,
                  inline: [action],
                  menu: [] as ActionDescriptor[],
              }))
            : [{ key: 'actions', ...placeActions(shown.value) }]),
);
// A segment's place for the leading style: its index in its part, except that wrap's one-action parts count across the bar, so only the first is the lead.
const at = (key: string, index: number) =>
    key.startsWith('wrap-') ? Number(key.slice(5)) : index;
const pill = computed(() => surface.value.shape === 'pill');
const inverse = computed(() => surface.value.tone === 'inverse');
const ordered = computed(() =>
    parts.value.flatMap((part) => [...part.inline, ...part.menu]),
);
// The fit: the root's width against the full layout's natural part widths and gap, measured on resize (null until mounted, so a server render shows everything).
const root = ref<HTMLElement>();
const full = ref<HTMLElement>();
const measured = ref<{
    container: number;
    parts: number[];
    gap: number;
} | null>(null);
// The last fit's state, so returning to the full layout needs some slack (fitActions' hysteresis) and a width at the boundary cannot oscillate.
const wasCompact = ref(false);
const fit = computed(() =>
    fitActions(
        ordered.value,
        measured.value ?? { container: 0, parts: [] },
        wasCompact.value,
    ),
);
// The compact layout is on only outside wrap mode.
const compacted = computed(() => !props.wrap && fit.value.compact);
watch(
    () => fit.value.compact,
    (value) => {
        wasCompact.value = value;
    },
);
const compact = computed(() => ({
    key: 'compact',
    inline: fit.value.inline,
    menu: fit.value.menu,
}));
let frame = 0;
function measure(): void {
    frame = 0;
    if (!root.value || !full.value) return;
    const row = full.value.querySelector<HTMLElement>('[data-slot="toolbar"]');
    const parts = [...(row?.children ?? [])].map(
        (part) => (part as HTMLElement).scrollWidth,
    );
    const next = {
        container: root.value.clientWidth,
        parts,
        gap: row ? parseFloat(getComputedStyle(row).columnGap) || 0 : 0,
    };
    if (!sameMeasure(measured.value, next)) measured.value = next;
}
// The observer never writes in its own callback: flipping the layout there resizes the observed root in the same frame (a ResizeObserver loop). It asks for one
// measure in the next animation frame, replacing any it already asked for.
function schedule(): void {
    if (frame) cancelAnimationFrame(frame);
    frame = requestAnimationFrame(measure);
}
let observer: ResizeObserver | undefined;
function observe(): void {
    observer?.disconnect();
    if (props.wrap) {
        measured.value = null;
        return;
    }
    if (typeof ResizeObserver === 'undefined' || !root.value) return;
    observer = new ResizeObserver(schedule);
    observer.observe(root.value);
    if (full.value) observer.observe(full.value);
    measure();
}
onMounted(observe);
watch([root, () => props.actions, () => props.wrap], observe, {
    flush: 'post',
});
onBeforeUnmount(() => {
    observer?.disconnect();
    if (frame) cancelAnimationFrame(frame);
    frame = 0;
});
// `fillAll` (opt-in, pill surface only): the whole group is one primary pill, every segment on it with the primary text, py-2, the first pl-3.5 and the rest
// px-3; a disabled segment still dims. Its labels keep ButtonGroup's semibold, and its segments ButtonGroup's divider (a right border on all but the last) in
// the outline token, `border-outline`, with `--kit-outline` set on the group the way the inverse FloatingToolbar sets it (the current colour, here the primary
// text, at 20%), so it reads on the fill. The divider rule is under :where() (no specificity), so `dividerClass` (fillAll only, put on each
// segment) overrides any of it: its colour, or `border-r-0` to drop it. `triggerClass` goes last on the ⌄ trigger (the fillAll segment here; on the ButtonGroup path, ButtonGroup's trigger), so it can set the
// trigger's padding, its chevron (`[&>svg]:size-5`) or a ring; only a plain (non-fillAll) pill's trigger ignores it. fillAll and `dividerClass` need a pill
// surface and do nothing without one; the default pill padding is unchanged.
const fillAll = computed(() => pill.value && props.fillAll);
const group = computed(() => [
    'inline-flex isolate min-w-0 [&>*+*]:-ml-px',
    pill.value
        ? '[&>*:first-child]:rounded-l-full [&>*:last-child]:rounded-r-full'
        : '[&>*:first-child]:rounded-l-md [&>*:last-child]:rounded-r-md',
    fillAll.value &&
        'rounded-full bg-primary text-primary-foreground shadow-xs [--kit-outline:color-mix(in_oklab,currentColor_20%,transparent)] [:where(&>*:not(:last-child))]:border-r [:where(&>*:not(:last-child))]:border-solid [:where(&>*:not(:last-child))]:border-outline',
]);
function segment(filled: boolean, first = false): string[] {
    const base =
        'inline-flex min-w-0 shrink items-center justify-center rounded-none text-sm font-medium whitespace-nowrap no-underline transition-colors focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50';
    if (fillAll.value)
        return [
            base.replace('font-medium', 'font-semibold'),
            'bg-transparent py-2 text-primary-foreground hover:bg-primary-foreground/10',
            first ? 'pr-3 pl-3.5' : 'px-3',
            props.dividerClass ?? '',
        ];
    return [
        base,
        pill.value ? 'h-9 px-3.5' : 'h-9 px-3',
        inverse.value
            ? filled
                ? `${inverseFilled} [&+*]:border-l [&+*]:border-primary-foreground/25`
                : inverseControl
            : filled
              ? 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90'
              : 'border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:hover:bg-input/50',
    ];
}
const filled = (index: number) =>
    (props.primary || props.filled) && index === 0;
// The ButtonGroup path's leading segment: semibold with `primary`; `filled` also gives it the primary fill. Important, so it outranks the group's transparent
// segment reset; a disabled segment still dims (ButtonGroup's disabled rule).
const lead = (index: number) =>
    filled(index) && [
        'primary-action font-semibold!',
        props.filled &&
            'bg-primary! text-primary-foreground! hover:bg-primary/90!',
    ];
// A menu-only group's trigger (no inline segment) on the ButtonGroup path: 'Actions' beside the ⌄ unless the host gives `menuTriggerLabel`; null leaves the ⌄
// alone, named "More actions" for assistive tech (ButtonGroup's menuLabel default).
const menuOnlyLabel = computed(() =>
    props.menuTriggerLabel === undefined ? 'Actions' : props.menuTriggerLabel,
);
const holdFocus = (event: Event) => {
    if (props.dialogOpen) event.preventDefault();
};
</script>
<template>
    <div
        v-if="shown.length"
        ref="root"
        class="grid max-w-full min-w-0 overflow-visible!"
        data-action-bar
        :data-compact="compacted || undefined"
        :data-wrap="wrap || undefined"
    >
        <div
            ref="full"
            :class="[
                'col-start-1 row-start-1 min-w-0',
                compacted &&
                    'pointer-events-none invisible h-0 overflow-hidden',
            ]"
            :aria-hidden="compacted || undefined"
            :inert="compacted || undefined"
            data-action-layout-full
        >
            <Toolbar v-if="pill" class="shrink-0">
                <div
                    v-for="part in parts"
                    :key="part.key"
                    role="group"
                    :class="group"
                    data-action-group
                    :data-action-layout="part.key"
                >
                    <ActionSegment
                        v-for="(action, index) in part.inline"
                        :key="action.key ?? action.url"
                        :action="action"
                        :pending="isRunning(action)"
                        :busy="blocked"
                        :class="
                            segment(filled(at(part.key, index)), index === 0)
                        "
                        @action="run($event)"
                    />
                    <DropdownMenu v-if="part.menu.length">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                :class="[
                                    segment(false, part.inline.length === 0),
                                    !fillAll && 'px-2.5',
                                    fillAll && triggerClass,
                                ]"
                                :aria-label="
                                    part.inline.length
                                        ? `More actions: ${part.inline[0].label}`
                                        : 'More actions'
                                "
                                data-action-menu
                            >
                                <ChevronDown
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            @close-auto-focus="holdFocus"
                        >
                            <ActionMenuItems
                                :actions="part.menu"
                                :busy="blocked"
                                :on-action="run"
                                :item-class="itemClass"
                            />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </Toolbar>
            <Toolbar v-else>
                <ButtonGroup
                    :item-class="itemClass"
                    v-for="part in parts"
                    weight="semibold"
                    :key="part.key"
                    :label="part.inline.length ? undefined : menuOnlyLabel"
                    :trigger-class="triggerClass"
                    data-action-group
                    :data-action-layout="part.key"
                >
                    <template v-if="part.inline.length" #default>
                        <ActionSegment
                            v-for="(action, index) in part.inline"
                            :key="action.key ?? action.url"
                            :action="action"
                            :pending="isRunning(action)"
                            :busy="blocked"
                            :class="lead(at(part.key, index))"
                            @action="run($event)"
                        />
                    </template>
                    <template v-if="part.menu.length" #menu>
                        <ActionMenuItems
                            :actions="part.menu"
                            :busy="blocked"
                            :on-action="run"
                            :item-class="itemClass"
                        />
                    </template>
                </ButtonGroup>
            </Toolbar>
        </div>
        <div
            v-if="compacted"
            class="col-start-1 row-start-1 flex min-w-0 flex-nowrap"
            data-action-layout-compact
        >
            <div
                v-if="pill"
                role="group"
                :class="[group, 'shrink-0 *:shrink-0']"
                data-action-group
                data-action-layout="compact"
            >
                <ActionSegment
                    v-for="(action, index) in compact.inline"
                    :key="action.key ?? action.url"
                    :action="action"
                    :pending="isRunning(action)"
                    :busy="blocked"
                    :class="segment(filled(index), index === 0)"
                    @action="run($event)"
                />
                <DropdownMenu v-if="compact.menu.length">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            :class="[
                                segment(false, compact.inline.length === 0),
                                !fillAll && 'px-2.5',
                                fillAll && triggerClass,
                            ]"
                            :aria-label="
                                compact.inline.length
                                    ? `More actions: ${compact.inline[0].label}`
                                    : 'More actions'
                            "
                            data-action-menu
                        >
                            <ChevronDown class="size-4" aria-hidden="true" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        @close-auto-focus="holdFocus"
                    >
                        <ActionMenuItems
                            :actions="compact.menu"
                            :busy="blocked"
                            :on-action="run"
                            :item-class="itemClass"
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <ButtonGroup
                :item-class="itemClass"
                :trigger-class="triggerClass"
                v-else
                weight="semibold"
                class="shrink-0"
                data-action-group
                data-action-layout="compact"
            >
                <template #default>
                    <ActionSegment
                        v-for="(action, index) in compact.inline"
                        :key="action.key ?? action.url"
                        :action="action"
                        :pending="isRunning(action)"
                        :busy="blocked"
                        :class="lead(index)"
                        @action="run($event)"
                    />
                </template>
                <template v-if="compact.menu.length" #menu>
                    <ActionMenuItems
                        :actions="compact.menu"
                        :busy="blocked"
                        :on-action="run"
                        :item-class="itemClass"
                    />
                </template>
            </ButtonGroup>
        </div>
        <Dialog
            v-if="pending?.confirm"
            v-model:open="asking"
            :variant="pending.destructive ? 'danger' : 'neutral'"
            :title="pending.confirm.title ?? `${pending.label}?`"
            :description="pending.confirm.description"
            :confirm-label="pending.confirm.label"
            :confirm-text="pending.confirm.typed ?? null"
            @confirm="(confirmation) => pending && visit(pending, confirmation)"
        />
    </div>
</template>
