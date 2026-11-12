<script setup lang="ts">
import { computed, ref, useAttrs, useId, useSlots } from 'vue';
import { useFieldAria } from './fieldAria';
import { Check } from '@lucide/vue';
import { createCommitter } from './radioCommit';

type RadioCardBase = {
    value: string | number;
    description?: string | null;
    disabled?: boolean;
    result?: number | null;
};
/** An option is named by its `label`, or by its `image`'s `alt` when it has no label (an image tile): one of the two is required. */
export type RadioCardOption =
    | (RadioCardBase & { label: string; image?: { src: string; alt: string } })
    | (RadioCardBase & { label?: string; image: { src: string; alt: string } });

/*
 * Large tappable choices (a poll's options on a phone): each option is a card around a native radio, so the browser gives the group one tab stop and the arrow keys
 * move and select within it (the radio group's roving tabindex), and the value submits with a plain or Inertia <Form> under `name`. The group is a
 * `role="radiogroup"` named by its Field's label (`<id>-label`) or `label`, and described by the Field's description and error. `v-model` is the selected value.
 * A selected card takes the primary ring; a disabled option (or a `disabled` group) is dimmed and skipped. `result` (0 to 100) draws a result bar and percentage
 * under the label once results are shown (`showResults`), or the `result` slot replaces it. Utilities and tokens only.
 *
 * `commit="click"` (a live poll, where a vote is sent at once) deviates from the APG radio group on purpose: the arrow keys, Home and End only move focus (a
 * roving tabindex over `role="radio"` buttons), and nothing is chosen until Space, Enter, a click or a tap. That press sets `v-model` and emits `commit`; pressing
 * the option already chosen emits nothing. The choice is announced politely, and a hidden input still submits it under `name`. The default, `change`, is the
 * native group above, where arrowing selects. Each card carries `data-value` (the option's value): a hook for browser tests and styling.
 *
 * The `option` slot (`{ option, checked, disabled }`) replaces a card's body; the native radio, the label around it, the focus ring and the checked ring stay the
 * kit's. An option with an `image` and no slot is an image tile: the image in a square box (lazy, async, `object-cover`, so ~100 tiles load without layout shift),
 * with the label under it if there is one. An option is named by its `label`, or by its image's `alt` when it has none; one of the two is required (a dev warning
 * otherwise). A checked image or slot tile also shows a check mark (aria-hidden: the radio carries the state), so the state isn't colour alone. A disabled option
 * is a native disabled radio: it can't be chosen and the arrow keys skip it, as the browser does. `columns` (2, 3, 4, 6 or `auto`, which fills the row with
 * tiles at least 7rem wide) lays the cards out in a grid; one column by default.
 *
 * Caller attributes land on the radiogroup; its `aria-describedby` joins the Field's ids. `invalid` sets `aria-invalid`, and `required` sets `aria-required` (and,
 * in the default `change` mode, the native radios' own `required`).
 *
 * Without `name` (a `v-model`-only group) nothing is submitted: the radios are grouped under a private name and detached from any enclosing form.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        id: string;
        name?: string;
        options: RadioCardOption[];
        label?: string;
        disabled?: boolean;
        showResults?: boolean;
        commit?: 'change' | 'click';
        invalid?: boolean;
        columns?: 2 | 3 | 4 | 6 | 'auto';
    }>(),
    {
        name: undefined,
        label: undefined,
        disabled: false,
        showResults: false,
        commit: 'change',
        columns: undefined,
    },
);
const emit = defineEmits<{ commit: [value: string | number] }>();
const model = defineModel<string | number | null>({ default: null });
const attrs = useAttrs();
const slots = useSlots();
// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
const grids = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-2 sm:grid-cols-3',
    4: 'grid-cols-2 sm:grid-cols-4',
    6: 'grid-cols-3 sm:grid-cols-6',
    auto: 'grid-cols-[repeat(auto-fill,minmax(7rem,1fr))]',
} as const;
const tile = (option: RadioCardOption) => Boolean(slots.option || option.image);
const optionDisabled = (option: RadioCardOption) =>
    props.disabled || Boolean(option.disabled);
if ((import.meta as { env?: { DEV?: boolean } }).env?.DEV) {
    for (const option of props.options)
        if (!option.label && !option.image?.alt)
            console.warn(
                `RadioCardGroup "${props.id}": option ${String(option.value)} has neither a label nor an image alt, so it has no accessible name.`,
            );
}
const required = computed(
    () => attrs.required !== undefined && attrs.required !== false,
);
const groupAttrs = computed(() => {
    const rest: Record<string, unknown> = { ...attrs };
    delete rest.required;
    return rest;
});
// Inside a Field: the group is described only by what the field renders (plus any ids the caller gives), and invalid while it has an error.
const aria = useFieldAria(attrs, () => props.invalid);
// Without a `name` the group submits nothing: the native radios still share a private name (so the browser groups them: one tab stop, arrows select), and a
// `form` attribute naming no element detaches them from any enclosing form, so they never reach its FormData (and its validation skips them).
const privateName = `kit-radio-${useId()}`;
const groupName = computed(() => props.name ?? privateName);
const detachedForm = computed(() =>
    props.name == null ? `${privateName}-unsubmitted` : undefined,
);
const optionId = (index: number) => `${props.id}-option-${index}`;
const clickMode = computed(() => props.commit === 'click');
// commit="click": the focused card (the one tab stop), the buttons to move focus between, and the polite announcement of the committed choice.
const focused = ref(
    Math.max(
        0,
        props.options.findIndex((option) => option.value === model.value),
    ),
);
const cards = ref<HTMLElement[]>([]);
const announced = ref('');
const committer = createCommitter({
    options: () => props.options,
    disabled: () => props.disabled,
    value: () => model.value,
    commit: (value, label) => {
        model.value = value;
        emit('commit', value);
        announced.value = `${label} chosen`;
    },
    focus: (index) => {
        focused.value = index;
        cards.value[index]?.focus();
    },
});
function move(event: KeyboardEvent, index: number): void {
    if (committer.key(event.key, index)) event.preventDefault();
}
const percent = (option: RadioCardOption) =>
    Math.max(0, Math.min(100, Math.round(option.result ?? 0)));
</script>
<template>
    <div
        :id="id"
        role="radiogroup"
        :aria-label="label"
        :aria-labelledby="label ? undefined : `${id}-label`"
        v-bind="groupAttrs"
        :aria-describedby="aria['aria-describedby']"
        :aria-invalid="aria['aria-invalid']"
        :aria-required="
            required
                ? 'true'
                : (attrs['aria-required'] as 'true' | 'false' | undefined)
        "
        :aria-disabled="disabled || undefined"
        :class="['grid gap-3', columns && grids[columns]]"
        data-radio-card-group
    >
        <component
            :is="clickMode ? 'button' : 'label'"
            v-for="(option, index) in options"
            :key="option.value"
            :ref="
                (el: unknown) => {
                    if (clickMode && el) cards[index] = el as HTMLElement;
                }
            "
            :for="clickMode ? undefined : optionId(index)"
            :type="clickMode ? 'button' : undefined"
            :role="clickMode ? 'radio' : undefined"
            :aria-checked="
                clickMode
                    ? model === option.value
                        ? 'true'
                        : 'false'
                    : undefined
            "
            :aria-disabled="
                clickMode && (disabled || option.disabled) ? 'true' : undefined
            "
            :tabindex="clickMode ? (index === focused ? 0 : -1) : undefined"
            :aria-describedby="
                clickMode && option.description
                    ? `${optionId(index)}-description`
                    : undefined
            "
            :class="[
                'bg-card text-card-foreground relative grid min-h-14 content-start gap-1 rounded-xl border text-left transition-colors motion-reduce:transition-none',
                tile(option) ? 'p-2' : 'p-4',
                'has-[:focus-visible]:outline-ring focus-visible:outline-ring focus-visible:outline-2 focus-visible:outline-offset-2 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2',
                model === option.value
                    ? 'border-primary ring-primary ring-2'
                    : 'border-border',
                disabled || option.disabled
                    ? 'cursor-not-allowed opacity-50'
                    : 'hover:bg-accent/50 cursor-pointer',
            ]"
            :data-selected="model === option.value || undefined"
            :data-disabled="disabled || option.disabled || undefined"
            :data-value="String(option.value)"
            data-radio-card
            @click="clickMode && committer.press(index)"
            @keydown="clickMode && move($event, index)"
        >
            <input
                v-if="!clickMode"
                :id="optionId(index)"
                v-model="model"
                type="radio"
                class="sr-only"
                :name="groupName"
                :form="detachedForm"
                :value="option.value"
                :disabled="disabled || option.disabled"
                :required="required || undefined"
                :aria-describedby="
                    option.description
                        ? `${optionId(index)}-description`
                        : undefined
                "
            />
            <slot
                v-if="$slots.option"
                name="option"
                :option="option"
                :checked="model === option.value"
                :disabled="optionDisabled(option)"
            />
            <template v-else-if="option.image">
                <span
                    class="bg-muted block aspect-square overflow-hidden rounded-lg"
                >
                    <img
                        :src="option.image.src"
                        :alt="option.label ? '' : option.image.alt"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                        data-radio-card-image
                    />
                </span>
                <span
                    v-if="option.label"
                    class="min-w-0 px-1 text-sm font-medium break-words"
                    >{{ option.label }}</span
                >
            </template>
            <span v-else class="flex items-center gap-3">
                <span
                    :class="[
                        'flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                        model === option.value
                            ? 'border-primary'
                            : 'border-muted-foreground',
                    ]"
                    aria-hidden="true"
                >
                    <span
                        v-if="model === option.value"
                        class="bg-primary size-2.5 rounded-full"
                    />
                </span>
                <span class="min-w-0 text-base font-medium break-words">{{
                    option.label
                }}</span>
            </span>
            <span
                v-if="tile(option) && model === option.value"
                class="bg-primary text-primary-foreground ring-background absolute top-3 right-3 flex size-6 items-center justify-center rounded-full shadow-sm ring-2"
                aria-hidden="true"
                data-radio-card-check
                ><Check class="size-4"
            /></span>
            <span
                v-if="option.description"
                :id="`${optionId(index)}-description`"
                :class="[
                    'text-muted-foreground text-sm',
                    tile(option) ? 'px-1' : 'pl-8',
                ]"
                >{{ option.description }}</span
            >
            <slot
                v-if="showResults"
                name="result"
                :option="option"
                :selected="model === option.value"
                :percent="percent(option)"
            >
                <span
                    class="flex items-center gap-3 pl-8"
                    data-radio-card-result
                >
                    <span
                        class="bg-muted h-2 min-w-0 flex-1 overflow-hidden rounded-full"
                        aria-hidden="true"
                    >
                        <span
                            class="bg-primary block h-full w-(--kit-radio-result) rounded-full transition-[width] duration-500 motion-reduce:transition-none"
                            :style="{
                                '--kit-radio-result': `${percent(option)}%`,
                            }"
                        />
                    </span>
                    <span
                        class="text-muted-foreground w-12 shrink-0 text-right text-sm tabular-nums"
                        >{{ percent(option) }}%</span
                    >
                </span>
            </slot>
        </component>
        <template v-if="clickMode">
            <input
                v-if="name != null"
                type="hidden"
                :name="name"
                :value="model ?? ''"
                data-radio-card-value
            />
            <p role="status" class="sr-only" data-radio-card-announce>
                {{ announced }}
            </p>
        </template>
    </div>
</template>
