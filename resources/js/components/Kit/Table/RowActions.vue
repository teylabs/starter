<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Ellipsis } from '@lucide/vue';
import { computed, ref, shallowRef, useId, type Directive } from 'vue';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import ButtonGroup from '@/components/Kit/Action/ButtonGroup.vue';
import Button from '@/components/Kit/Form/Button.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import { Link } from '@inertiajs/vue3';
import {
    opensNewTab,
    rendersAsLink,
} from '@/components/Kit/Action/actionPlacement';
import { confirmationData } from '@/components/Kit/Overlay/confirmText';
import {
    actionName,
    clampLabel,
    confirmRecord,
    confirmTitle,
    layOut,
    tooLong,
    warnLongLabel,
    type RowActionVariant,
} from './rowActionLayout';

// One row's actions, as the server decided them: nothing here checks a permission, an action that is not in the list is not shown. Two layouts (rowActionLayout.ts):
// `split`, the primary action joined in a ButtonGroup to a chevron menu of the rest, and `overflow`, quiet inline actions plus a "…" menu. Without `variant` a row
// of exactly one primary action plus menu items is split, any other overflow. A destructive action is a menu item whenever another action exists (a lone one is a button), and
// always asks first with the action's own `confirm` text (and its `typed` text to type, sent as `confirmation`). `intercept` names actions the page performs itself (a dialog) instead of an Inertia visit.
// The menu trigger is named by `label` ("Actions for Ana Lopez"). Each action's accessible name is its `ariaLabel`, else "<Label> <record>" (`record`, else
// `label` without "Actions for "); a confirmation without a title asks a short "<Label> this <noun>?" (`noun`, DataTable passes TableQuery::noun()) or "<Label> this item?" and names the record in the body, where it wraps (never in the bold title).
// In a menu, an action's `description` shows under its label (muted, one line, truncated) and describes the item (aria-describedby): several "Remove" rows name their grant. A visible label over 24
// characters or 3 words is clamped with an ellipsis, its full text in `title` (and a development warning: labels are verbs, the record goes in the confirm).
// A destructive action is never a danger button, a lone one included: red is only the menu item's text and the confirmation Dialog's commit, and follows
// `destructive` alone; a `confirm` without it, such as a role change, asks in a neutral Dialog.
// While one action's visit is in flight its control is aria-busy and every action of the row waits (a second click runs nothing). A `disabled` action stays
// focusable (aria-disabled), shows its `reason` as a tooltip and is described by it (a visually hidden element on aria-describedby); choosing it does nothing.
type RowAction = {
    key: string | null;
    label: string;
    url: string;
    method: string;
    destructive: boolean;
    disabled?: boolean;
    reason?: string | null;
    ariaLabel?: string | null;
    description?: string | null;
    newTab?: boolean | null;
    intent?: string | null;
    confirm: {
        title?: string | null;
        description: string;
        label: string;
        typed?: string | null;
    } | null;
};
const props = defineProps<{
    actions: RowAction[];
    label: string;
    intercept?: string[];
    variant?: RowActionVariant | null;
    record?: string | null;
    noun?: string | null;
}>();
const emit = defineEmits<{ act: [action: RowAction] }>();
const laid = computed(() => layOut(props.actions, props.variant));
const inline = computed(() => laid.value.inline);
const menu = computed(() => laid.value.menu);
const lone = computed(() => laid.value.lone);
const primary = computed(() => laid.value.primary);
// The row's name: `record`, else `label` without its "Actions for " prefix, else `label` itself (a caller that passes the row title as the label).
const recordName = computed(
    () =>
        props.record ??
        (props.label.startsWith('Actions for ')
            ? props.label.slice('Actions for '.length)
            : props.label) ??
        null,
);
const named = (action: RowAction) => actionName(action, recordName.value);
const shownLabel = (action: RowAction) => {
    warnLongLabel(action.label);
    return clampLabel(action.label);
};
const asking = ref<RowAction | null>(null);
// The dialog closes itself on Confirm (clearing `asking`) around the same click, so the action to run is kept apart.
let pending: RowAction | null = null;
// Its text stays after the dialog closes: the dialog must not be removed in the middle of the click that confirms it, or the confirm never fires.
const shown = ref<
    | (NonNullable<RowAction['confirm']> & {
          title: string;
          record: string | null;
      })
    | null
>(null);
const danger = ref(false);
const asked = computed({
    get: () => asking.value !== null,
    set: (open: boolean) => {
        if (!open) asking.value = null;
    },
});

// The action whose visit is in flight; set before the visit starts, so a second click in the same tick is refused too.
const running = shallowRef<RowAction | null>(null);
// By key (or URL) as well as identity: a response can replace the page's props, and these descriptors with them, before the visit finishes.
const isRunning = (action: RowAction) =>
    running.value !== null &&
    (running.value === action ||
        (running.value.key ?? running.value.url) ===
            (action.key ?? action.url));
const baseId = useId();
const reasonId = (action: RowAction) =>
    `${baseId}-reason-${props.actions.indexOf(action)}`;
const describes = (action: RowAction) =>
    Boolean(action.disabled && action.reason);
const descriptionId = (action: RowAction) =>
    `${baseId}-description-${props.actions.indexOf(action)}`;
// The ids describing a control: its `description` (menu items only; a button shows no description) and a disabled action's `reason`.
const describedBy = (action: RowAction, inMenu = false) =>
    [
        inMenu && action.description ? descriptionId(action) : null,
        describes(action) ? reasonId(action) : null,
    ]
        .filter(Boolean)
        .join(' ') || undefined;
// While an action is in flight a link renders as the guarded button too, so it cannot navigate away mid-visit.
const linked = (action: RowAction) =>
    !action.disabled &&
    running.value === null &&
    rendersAsLink(action, props.intercept);

function perform(action: RowAction) {
    if (action.disabled || running.value !== null) return;
    if (props.intercept?.includes(action.key ?? '')) return emit('act', action);
    if (action.confirm) {
        pending = action;
        shown.value = {
            ...action.confirm,
            title: confirmTitle(action, props.noun),
            record: confirmRecord(action, recordName.value),
        };
        danger.value = action.destructive;
        // After the menu has closed and given focus back: opened in the same tick, the menu's own dismissal swallows the confirmation.
        setTimeout(() => (asking.value = action), 0);
        return;
    }
    run(action);
}
function run(action: RowAction, confirmation?: string) {
    if (running.value !== null) return;
    running.value = action;
    router.visit(action.url, {
        method: action.method as 'get' | 'post' | 'put' | 'patch' | 'delete',
        data: confirmationData(confirmation),
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (running.value = null),
    });
}
// aria-disabled, never the disabled attribute: a disabled control has no hover (no tooltip) and no focus in some browsers.
const disabledNow = (action: RowAction) =>
    Boolean(action.disabled || running.value !== null);
function state(action: RowAction, inMenu = false) {
    const waiting = running.value !== null;
    return {
        'aria-disabled': action.disabled || waiting || undefined,
        'aria-busy': isRunning(action) || undefined,
        'aria-describedby': describedBy(action, inMenu),
        'aria-label': named(action),
        title:
            (describes(action) && action.reason) ||
            (tooLong(action.label) ? action.label : undefined),
        class: (action.disabled || waiting) && 'cursor-not-allowed opacity-50',
    };
}
// A link's name and clamped label's tooltip; a link is never disabled, so it has no reason to show.
const linkState = (action: RowAction) => ({
    'aria-label': named(action),
    title: tooLong(action.label) ? action.label : undefined,
});
const menuLinkState = (action: RowAction) => ({
    ...linkState(action),
    'aria-describedby': describedBy(action, true),
});
function choose(action: RowAction, event: Event) {
    // A disabled row, or any row while an action is in flight, keeps the menu open, as choosing it does nothing.
    if (action.disabled || running.value !== null)
        return event.preventDefault();
    perform(action);
}
// Reka's menu item writes its own aria-disabled (from its `disabled`, which would also drop the row from keyboard navigation) over a passed one, so a row that
// stays focusable but cannot be chosen sets the attribute itself once rendered.
const vAriaDisabled: Directive<HTMLElement, boolean | undefined> = {
    mounted: (el, { value }) => ariaDisabled(el, value),
    updated: (el, { value }) => ariaDisabled(el, value),
};
function ariaDisabled(el: HTMLElement, value: boolean | undefined) {
    if (value) el.setAttribute('aria-disabled', 'true');
    else el.removeAttribute('aria-disabled');
}
function confirmed(confirmation?: string) {
    if (pending) run(pending, confirmation);
    pending = null;
    asking.value = null;
}
</script>
<template>
    <div
        v-if="actions.length"
        class="flex items-center justify-end gap-2"
        :data-row-actions="laid.variant"
    >
        <template v-for="action in inline" :key="action.key ?? action.label">
            <Button
                v-if="linked(action)"
                variant="ghost"
                size="sm"
                :href="action.url"
                :external="opensNewTab(action)"
                :target="opensNewTab(action) ? '_blank' : undefined"
                v-bind="linkState(action)"
                :data-action="action.key"
                >{{ shownLabel(action) }}</Button
            >
            <Button
                v-else
                variant="ghost"
                size="sm"
                type="button"
                v-bind="state(action)"
                :data-action="action.key"
                @click="perform(action)"
                >{{ shownLabel(action)
                }}<span
                    v-if="describes(action)"
                    :id="reasonId(action)"
                    class="sr-only"
                    aria-hidden="true"
                    data-action-reason
                    >{{ action.reason }}</span
                ></Button
            >
        </template>
        <Button
            v-for="action in lone"
            :key="action.key ?? action.label"
            variant="ghost"
            size="sm"
            type="button"
            v-bind="state(action)"
            :data-action="action.key"
            @click="perform(action)"
            >{{ shownLabel(action)
            }}<span
                v-if="describes(action)"
                :id="reasonId(action)"
                class="sr-only"
                aria-hidden="true"
                data-action-reason
                >{{ action.reason }}</span
            ></Button
        >
        <ButtonGroup v-if="primary" :menu-label="label" data-row-split>
            <Button
                v-if="linked(primary)"
                variant="ghost"
                size="sm"
                :href="primary.url"
                :external="opensNewTab(primary)"
                :target="opensNewTab(primary) ? '_blank' : undefined"
                v-bind="linkState(primary)"
                :data-action="primary.key"
                >{{ shownLabel(primary) }}</Button
            >
            <Button
                v-else
                variant="ghost"
                size="sm"
                type="button"
                v-bind="state(primary)"
                :data-action="primary.key"
                @click="perform(primary)"
                >{{ shownLabel(primary)
                }}<span
                    v-if="describes(primary)"
                    :id="reasonId(primary)"
                    class="sr-only"
                    aria-hidden="true"
                    data-action-reason
                    >{{ primary.reason }}</span
                ></Button
            >
            <template v-if="menu.length" #menu>
                <template
                    v-for="action in menu"
                    :key="action.key ?? action.label"
                >
                    <DropdownMenuItem
                        v-if="linked(action)"
                        as-child
                        v-bind="menuLinkState(action)"
                        :data-action="action.key"
                    >
                        <a
                            v-if="opensNewTab(action)"
                            :href="action.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><span class="flex min-w-0 flex-col"
                                ><span>{{ shownLabel(action) }}</span
                                ><span
                                    v-if="action.description"
                                    :id="descriptionId(action)"
                                    class="text-muted-foreground truncate text-xs"
                                    data-action-description
                                    >{{ action.description }}</span
                                ></span
                            ></a
                        >
                        <Link v-else :href="action.url"
                            ><span class="flex min-w-0 flex-col"
                                ><span>{{ shownLabel(action) }}</span
                                ><span
                                    v-if="action.description"
                                    :id="descriptionId(action)"
                                    class="text-muted-foreground truncate text-xs"
                                    data-action-description
                                    >{{ action.description }}</span
                                ></span
                            ></Link
                        >
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-else
                        :class="
                            action.destructive
                                ? 'text-destructive-text focus:text-destructive-text'
                                : ''
                        "
                        v-bind="state(action, true)"
                        v-aria-disabled="disabledNow(action)"
                        :data-action="action.key"
                        @select="choose(action, $event)"
                        ><span class="flex min-w-0 flex-col"
                            ><span>{{ shownLabel(action) }}</span
                            ><span
                                v-if="action.description"
                                :id="descriptionId(action)"
                                class="text-muted-foreground truncate text-xs"
                                data-action-description
                                >{{ action.description }}</span
                            ></span
                        ><span
                            v-if="describes(action)"
                            :id="reasonId(action)"
                            class="sr-only"
                            aria-hidden="true"
                            data-action-reason
                            >{{ action.reason }}</span
                        ></DropdownMenuItem
                    >
                </template>
            </template>
        </ButtonGroup>
        <DropdownMenu v-else-if="menu.length">
            <DropdownMenuTrigger as-child
                ><Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    class="text-muted-foreground hover:text-foreground size-8"
                    :aria-label="label"
                    data-row-menu
                    ><Ellipsis class="size-4" aria-hidden="true" /></Button
            ></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <template
                    v-for="action in menu"
                    :key="action.key ?? action.label"
                >
                    <DropdownMenuItem
                        v-if="linked(action)"
                        as-child
                        v-bind="menuLinkState(action)"
                        :data-action="action.key"
                    >
                        <a
                            v-if="opensNewTab(action)"
                            :href="action.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><span class="flex min-w-0 flex-col"
                                ><span>{{ shownLabel(action) }}</span
                                ><span
                                    v-if="action.description"
                                    :id="descriptionId(action)"
                                    class="text-muted-foreground truncate text-xs"
                                    data-action-description
                                    >{{ action.description }}</span
                                ></span
                            ></a
                        >
                        <Link v-else :href="action.url"
                            ><span class="flex min-w-0 flex-col"
                                ><span>{{ shownLabel(action) }}</span
                                ><span
                                    v-if="action.description"
                                    :id="descriptionId(action)"
                                    class="text-muted-foreground truncate text-xs"
                                    data-action-description
                                    >{{ action.description }}</span
                                ></span
                            ></Link
                        >
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-else
                        :class="
                            action.destructive
                                ? 'text-destructive-text focus:text-destructive-text'
                                : ''
                        "
                        v-bind="state(action, true)"
                        v-aria-disabled="disabledNow(action)"
                        :data-action="action.key"
                        @select="choose(action, $event)"
                        ><span class="flex min-w-0 flex-col"
                            ><span>{{ shownLabel(action) }}</span
                            ><span
                                v-if="action.description"
                                :id="descriptionId(action)"
                                class="text-muted-foreground truncate text-xs"
                                data-action-description
                                >{{ action.description }}</span
                            ></span
                        ><span
                            v-if="describes(action)"
                            :id="reasonId(action)"
                            class="sr-only"
                            aria-hidden="true"
                            data-action-reason
                            >{{ action.reason }}</span
                        ></DropdownMenuItem
                    >
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
        <Dialog
            :variant="danger ? 'danger' : 'neutral'"
            v-if="shown"
            v-model:open="asked"
            :title="shown.title"
            :description="shown.description"
            :confirm-label="shown.label"
            :confirm-text="shown.typed ?? null"
            @confirm="confirmed"
            ><template v-if="shown.record" #body
                ><p
                    class="text-foreground text-sm font-medium break-words"
                    data-confirm-record
                >
                    {{ shown.record }}
                </p></template
            ></Dialog
        >
    </div>
</template>
