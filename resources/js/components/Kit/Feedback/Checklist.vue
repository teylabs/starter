<script setup lang="ts">
import {
    Check,
    Flag,
    Circle,
    CircleDot,
    CircleMinus,
    LoaderCircle,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import InertiaAction from '@/components/Kit/Action/InertiaAction.vue';
import Button from '@/components/Kit/Form/Button.vue';
import {
    checklistMessage,
    type ChecklistItem,
    type ServerChecklistProgress,
} from './checklist';
import { useChecklist } from './useChecklist';

/*
 * A server-computed checklist (kit Checklist): build steps (Preparing → Rendering → Saving), a setup or readiness list, or a run's stepper. The server sends
 * ChecklistItem rows and decides every state; this only draws them. Pending steps are muted, the active one spins, done ones are checked, a failed one shows
 * its reason (as an alert), and a skipped one is struck through with its reason. `detail` is a line under the label. An item's `action` is a link with a
 * `url`, or else a button that emits `action` with the item. The `item` slot replaces a row's body, and the `actions` slot adds a last row (`complete` tells
 * it whether every required step is done). A finalize gate's steps add `errors` (each with an optional fix link) and `actions` (server descriptors, drawn as small
 * buttons); pass the server's `progress` and it decides `complete`. Every fix, `action` or `actions`, is one small button with
 * `data-step-action="<item key>:<action key>"`. The `body` slot (`#body="{ item }"`) adds a step's own content (an inline editor) under its label and
 * errors and above its fixes; map step keys to components in an object rather than branching on the key. `variant="steps"` draws the same rows as a numbered vertical stepper for a page-sized gate: a large marker per step
 * joined by a rail (filled up to the last settled step), a larger title with its detail beneath, roomy step bodies for their fixes, and the `actions` slot as the
 * rail's last step (the finalize control). `active="turn"` draws the active step as a still ring for a step a person does, not a spinner. A polite status line announces the current step or the outcome. Headless use: checklistProgress() in checklist.ts, or useChecklist().
 */
const props = withDefaults(
    defineProps<{
        items: readonly ChecklistItem[];
        label?: string;
        labels?: Partial<
            Record<
                | 'complete'
                | 'failed'
                | 'waiting'
                | 'done'
                | 'skipped'
                | 'optional',
                string
            >
        >;
        announce?: boolean;
        progress?: ServerChecklistProgress | null;
        /** How the active step looks: `running` spins (work in progress); `turn` is a still primary ring (a person's turn, as in a finalize gate). */
        active?: 'running' | 'turn';
        /** `list` is the compact list; `steps` is a numbered vertical stepper with a rail, for a page given over to the gate. */
        variant?: 'list' | 'steps';
        /** With `variant="steps"`, the title of the rail's last step, the one the `actions` slot fills (a finalize gate's "Finalize"). */
        finalLabel?: string;
    }>(),
    {
        label: undefined,
        labels: () => ({}),
        announce: true,
        progress: null,
        active: 'running',
        variant: 'list',
        finalLabel: undefined,
    },
);
const emit = defineEmits<{ action: [item: ChecklistItem] }>();
const { items, progress: computed_ } = useChecklist(() => props.items);
// The server's word on completion wins when it is given: it counts what the client cannot see.
const progress = computed(() =>
    props.progress
        ? { ...computed_.value, complete: props.progress.complete }
        : computed_.value,
);
const message = computed(() => checklistMessage(progress.value, props.labels));
const text = computed(() => ({
    done: 'done',
    skipped: 'skipped',
    optional: 'optional',
    ...props.labels,
}));
const tone: Record<string, string> = {
    pending: 'text-muted-foreground',
    active: 'text-foreground font-medium',
    done: 'text-foreground',
    failed: 'text-destructive-text',
    skipped: 'text-muted-foreground line-through',
};
const steps = computed(() => props.variant === 'steps');
// The stepper's marker per state: settled steps are filled, the current one is ringed in the primary colour, the rest are outlined.
const marker: Record<string, string> = {
    pending: 'border-border bg-background text-muted-foreground border',
    active: 'border-primary bg-background text-primary border-2',
    done: 'bg-primary text-primary-foreground',
    failed: 'border-destructive-text bg-background text-destructive-text border-2',
    skipped: 'border-border bg-muted text-muted-foreground border',
};
const settled = (state: string) => state === 'done' || state === 'skipped';
// The rail is filled from the top for as long as every step so far is settled: it shows how far the gate has come, not which steps happen to be done.
const filled = computed(() => {
    const list = items.value;
    const firstOpen = list.findIndex((item) => !settled(item.state));
    return firstOpen === -1 ? list.length : firstOpen;
});
const icon: Record<string, string> = {
    pending: 'text-muted-foreground',
    active: 'text-primary motion-safe:animate-spin',
    done: 'text-success-text',
    failed: 'text-destructive-text',
    skipped: 'text-muted-foreground',
};
</script>
<template>
    <div data-checklist :data-complete="progress.complete || undefined">
        <p
            v-if="announce"
            class="sr-only"
            role="status"
            aria-live="polite"
            data-checklist-message
        >
            {{ message }}
        </p>
        <ol
            :aria-label="label"
            :class="steps ? 'grid' : 'grid gap-3'"
            data-checklist-items
            :data-variant="variant"
        >
            <li
                v-for="(item, index) in items"
                :key="item.key"
                :class="
                    steps
                        ? 'relative flex items-start gap-4 pb-20 last:pb-0'
                        : 'flex items-start gap-3'
                "
                data-checklist-item
                :data-state="item.state"
                :aria-current="item.state === 'active' ? 'step' : undefined"
            >
                <template v-if="steps">
                    <span
                        v-if="index < items.length - 1 || $slots.actions"
                        :class="[
                            'absolute top-8 bottom-0 left-4 w-0.5 -translate-x-1/2',
                            index < filled ? 'bg-primary' : 'bg-border',
                        ]"
                        aria-hidden="true"
                        data-checklist-rail
                    />
                    <span
                        :class="[
                            'relative flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums',
                            marker[item.state] ?? marker.pending,
                        ]"
                        aria-hidden="true"
                        data-checklist-marker
                    >
                        <Check v-if="item.state === 'done'" class="size-4" />
                        <X v-else-if="item.state === 'failed'" class="size-4" />
                        <CircleMinus
                            v-else-if="item.state === 'skipped'"
                            class="size-4"
                        />
                        <LoaderCircle
                            v-else-if="
                                item.state === 'active' && active === 'running'
                            "
                            class="size-4 motion-safe:animate-spin"
                        />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                </template>
                <span
                    v-else
                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center"
                    aria-hidden="true"
                >
                    <CircleDot
                        v-if="item.state === 'active' && active === 'turn'"
                        class="text-primary size-4"
                        data-checklist-turn
                    />
                    <LoaderCircle
                        v-else-if="item.state === 'active'"
                        :class="['size-4', icon.active]"
                    />
                    <Check
                        v-else-if="item.state === 'done'"
                        :class="['size-4', icon.done]"
                    />
                    <X
                        v-else-if="item.state === 'failed'"
                        :class="['size-4', icon.failed]"
                    />
                    <CircleMinus
                        v-else-if="item.state === 'skipped'"
                        :class="['size-4', icon.skipped]"
                    />
                    <Circle v-else :class="['size-3', icon.pending]" />
                </span>
                <div
                    :class="
                        steps
                            ? 'grid min-w-0 flex-1 gap-1.5 pt-1'
                            : 'grid min-w-0 flex-1 gap-1'
                    "
                >
                    <slot name="item" :item="item">
                        <span
                            :class="[
                                steps ? 'text-lg font-semibold' : 'text-sm',
                                tone[item.state] ?? tone.pending,
                                steps && item.state === 'pending'
                                    ? 'font-medium'
                                    : '',
                            ]"
                            data-checklist-label
                        >
                            {{ item.label
                            }}<span
                                v-if="
                                    item.state === 'done' ||
                                    item.state === 'skipped'
                                "
                                class="sr-only"
                            >
                                ({{ text[item.state] }})</span
                            ><span
                                v-if="item.required === false"
                                class="text-muted-foreground ml-1 text-xs no-underline"
                                >({{ text.optional }})</span
                            >
                        </span>
                        <span
                            v-if="item.detail"
                            class="text-muted-foreground text-sm"
                            data-checklist-detail
                            >{{ item.detail }}</span
                        >
                        <span
                            v-if="item.reason && item.state === 'failed'"
                            class="text-destructive-text text-sm"
                            role="alert"
                            data-checklist-reason
                            >{{ item.reason }}</span
                        >
                        <span
                            v-else-if="item.reason && item.state === 'skipped'"
                            class="text-muted-foreground text-sm"
                            data-checklist-reason
                            >{{ item.reason }}</span
                        >
                        <ul
                            v-if="item.errors?.length && item.state !== 'done'"
                            class="grid gap-0.5"
                            data-checklist-errors
                        >
                            <li
                                v-for="(error, index) in item.errors"
                                :key="index"
                                class="text-destructive-text text-sm"
                                :data-field="error.field || undefined"
                            >
                                <a
                                    v-if="error.url"
                                    :href="error.url"
                                    class="underline underline-offset-4"
                                    >{{ error.message }}</a
                                ><template v-else>{{ error.message }}</template>
                            </li>
                        </ul>
                        <ul
                            v-if="
                                item.warnings?.length &&
                                item.state !== 'skipped'
                            "
                            class="grid gap-0.5"
                            data-checklist-warnings
                        >
                            <li
                                v-for="(warning, index) in item.warnings"
                                :key="index"
                                class="text-warning-text text-sm"
                                :data-field="warning.field || undefined"
                            >
                                <a
                                    v-if="warning.url"
                                    :href="warning.url"
                                    class="underline underline-offset-4"
                                    >{{ warning.message }}</a
                                ><template v-else>{{
                                    warning.message
                                }}</template>
                            </li>
                        </ul>
                        <div
                            v-if="$slots.body"
                            :class="steps ? 'pt-3' : 'pt-1'"
                            data-checklist-body
                        >
                            <slot name="body" :item="item" />
                        </div>
                        <span
                            v-if="item.actions?.length"
                            :class="[
                                'flex flex-wrap gap-2',
                                steps ? 'pt-3' : 'pt-1',
                            ]"
                            data-checklist-actions
                        >
                            <span
                                v-for="each in item.actions"
                                :key="each.key ?? each.label"
                                class="inline-flex"
                                :data-step-action="`${item.key}:${each.key ?? each.label}`"
                            >
                                <InertiaAction :action="each" size="sm" />
                            </span>
                        </span>
                        <span
                            v-if="item.action"
                            :class="['flex', steps ? 'pt-3' : 'pt-1']"
                            data-checklist-action
                        >
                            <span
                                class="inline-flex"
                                :data-step-action="`${item.key}:${item.action.name ?? 'action'}`"
                            >
                                <InertiaAction
                                    v-if="item.action.url"
                                    :action="{
                                        key: item.action.name ?? 'action',
                                        label: item.action.label,
                                        url: item.action.url,
                                        method: 'get',
                                    }"
                                    size="sm"
                                />
                                <Button
                                    v-else
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    @click="emit('action', item)"
                                    >{{ item.action.label }}</Button
                                >
                            </span>
                        </span>
                    </slot>
                </div>
            </li>
            <li
                v-if="$slots.actions"
                :class="
                    steps
                        ? 'relative flex items-start gap-4'
                        : 'flex items-start gap-3'
                "
                data-checklist-item
                data-checklist-final
                :data-state="
                    progress.complete
                        ? 'done'
                        : progress.failed
                          ? 'failed'
                          : 'pending'
                "
            >
                <span
                    v-if="steps"
                    :class="[
                        'relative flex size-8 shrink-0 items-center justify-center rounded-full',
                        progress.complete
                            ? 'border-primary bg-background text-primary border-2'
                            : 'border-border bg-background text-muted-foreground border',
                    ]"
                    aria-hidden="true"
                    data-checklist-marker
                >
                    <Flag class="size-4" />
                </span>
                <span
                    v-else
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div
                    :class="
                        steps
                            ? 'grid min-w-0 flex-1 gap-3 pt-1'
                            : 'min-w-0 flex-1'
                    "
                >
                    <span
                        v-if="steps && finalLabel"
                        :class="[
                            'text-lg font-semibold',
                            progress.complete
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        ]"
                        data-checklist-label
                        >{{ finalLabel }}</span
                    >
                    <slot
                        name="actions"
                        :complete="progress.complete"
                        :progress="progress"
                    />
                </div>
            </li>
        </ol>
    </div>
</template>
