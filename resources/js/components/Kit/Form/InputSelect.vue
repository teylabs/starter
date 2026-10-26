<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, LoaderCircle, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useAttrs, useId, watch } from 'vue';
import { ComboboxAnchor, ComboboxContent, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxLabel, ComboboxPortal, ComboboxRoot, ComboboxTrigger, ComboboxViewport } from 'reka-ui';
import { chipKey, chosenFirst as orderChosenFirst, filterGroups, flatOptions, fold, interpolate, moveHighlight, resultsMessage, searchEnabled, searchReady, selectAll, selectMessages, showCreate, toggleValue, valuesOf } from './inputSelect';
import type { InputSelectMessages, InputSelectOption, InputSelectOptions, InputSelectValue } from './inputSelect';
import { useLocked } from './useLocked';

// The kit's one select (InputSelect.md): one value or many, searchable, grouped, local or remote, on Reka's Combobox (never Reka's Select, which is modal).
// The list is non-modal. Reka's ComboboxContent always hides the rest of the page from assistive technology (useHideOthers) and offers no prop to stop it; its only
// exception, undocumented, is a root inside a closed popover, so the root is wrapped in one (`popover` never shown, `display: contents`). Verified on reka-ui 2.11.0:
// re-run the "nothing outside is aria-hidden or inert" render check on every Reka bump. Selection is the component's own (every item's select is prevented), so the model is fully controlled.
// Caller attributes land on the input; `id` and `aria-*` there win, as for every kit input. Strings come from the kit's `ui` lang file (`kitUi`), then `messages`.
// Read-only comes from the `readonly` prop or a locked LockedForm (the LockedForm spec): it stays a focusable combobox with `aria-readonly`, shows the chosen label (or "None"), and opens nothing; no chevron, no clear. A caller's own `disabled` still wins.
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{
    modelValue?: InputSelectValue | InputSelectValue[] | null; options?: InputSelectOptions; multiple?: boolean; name?: string; placeholder?: string; searchable?: boolean | null;
    clearable?: boolean | null; selectAll?: boolean; chosenFirst?: boolean; creatable?: boolean; disabled?: boolean; readonly?: boolean; invalid?: boolean; loading?: boolean;
    filter?: 'local' | 'remote'; debounce?: number; minSearchLength?: number; messages?: Partial<InputSelectMessages>; maxChips?: number | null; nativeRequired?: boolean;
}>(), { modelValue: undefined, options: () => [], name: undefined, placeholder: undefined, searchable: null, clearable: null, filter: 'local', debounce: 250, minSearchLength: 2, messages: () => ({}), maxChips: null });
const emit = defineEmits<{ 'update:modelValue': [value: InputSelectValue | InputSelectValue[] | null]; search: [query: string]; open: []; close: []; create: [query: string] }>();
defineSlots<{
    option?: (props: { option: InputSelectOption; selected: boolean; highlighted: boolean; disabled: boolean }) => unknown;
    chip?: (props: { option: InputSelectOption; remove: () => void }) => unknown;
    empty?: (props: { query: string }) => unknown;
    loading?: () => unknown;
}>();
const attrs = useAttrs();
const uid = useId();
const lock = useLocked();
const readOnly = computed(() => props.readonly || (lock.locked && !props.disabled));
const inputId = computed(() => (attrs.id as string | undefined) ?? `${uid}-input`);

const shared = computed(() => {
    try {
        return (usePage().props as { kitUi?: { select?: unknown } }).kitUi?.select;
    } catch {
        return undefined;
    }
});
const text = computed(() => selectMessages(shared.value, props.messages));

const values = computed(() => valuesOf(props.modelValue));
const all = computed(() => flatOptions(props.options));
const byValue = computed(() => new Map(all.value.map(option => [option.value, option])));
const chosen = computed(() => values.value.map(value => byValue.value.get(value)).filter((option): option is InputSelectOption => !!option));
// `creatable` needs typed text, so automatic search is on with it.
const searchOn = computed(() => (props.creatable && props.searchable === null) || searchEnabled(props.searchable, props.options, props.filter));
const canClear = computed(() => (props.clearable ?? props.multiple) && !readOnly.value && !props.disabled && values.value.length > 0);
const interactive = computed(() => !props.disabled && !readOnly.value);

// The text in the field: what was typed, else (single) the chosen label. `typed` turns on with the first keystroke and off on close or a choice.
const open = ref(false);
const query = ref('');
const typed = ref(false);
const singleLabel = computed(() => (props.multiple ? '' : (chosen.value[0]?.label ?? '')));
const fieldText = computed({
    get: () => (typed.value || props.multiple ? query.value : singleLabel.value),
    set: (value: string) => {
        if (!interactive.value || !searchOn.value) return;
        // Typing after the shown label (single) starts the search from the typed text, not the label.
        if (!typed.value && singleLabel.value && value.startsWith(singleLabel.value)) value = value.slice(singleLabel.value.length);
        query.value = value;
        typed.value = true;
    },
});
const activeQuery = computed(() => (typed.value && searchOn.value ? query.value : ''));

const chosenAtOpen = ref<InputSelectValue[]>([]);
const groups = computed(() => {
    const filtered = filterGroups(props.options, activeQuery.value, props.filter);
    return props.chosenFirst ? orderChosenFirst(filtered, chosenAtOpen.value) : filtered;
});
const shown = computed(() => groups.value.flatMap(group => group.options));
const grouped = computed(() => groups.value.some(group => group.label !== null));
const tooShort = computed(() => props.filter === 'remote' && props.minSearchLength > 0 && !searchReady(activeQuery.value, props.minSearchLength));

// Action rows are items too (so they highlight and announce as options), told apart by these values.
const SELECT_ALL = { action: 'select-all' } as const;
const CREATE = { action: 'create' } as const;
const selectAllShown = computed(() => props.multiple && props.selectAll && shown.value.some(option => !option.disabled));
const selectAllLabel = computed(() => (selectAll(values.value, shown.value).all ? text.value.deselect_all : text.value.select_all));
const createShown = computed(() => props.creatable && typed.value && showCreate(query.value, props.options));
const createLabel = computed(() => interpolate(text.value.create, { query: query.value.trim() }));
type NavRow = InputSelectOption | typeof SELECT_ALL | typeof CREATE;
const navRows = computed<(NavRow | null)[]>(() => [...(selectAllShown.value ? [SELECT_ALL] : []), ...shown.value.map(option => (option.disabled ? null : option)), ...(createShown.value ? [CREATE] : [])]);
const rowValue = (row: NavRow) => ('action' in row ? row : row.value);

const highlighted = ref<unknown>(undefined);
const root = ref<{ highlightItem?: (value: unknown) => void } | null>(null);
const input = ref<{ $el?: HTMLInputElement } | null>(null);
const inputEl = () => input.value?.$el as HTMLInputElement | undefined;
const focusInput = () => inputEl()?.focus();

// One polite live region for removals, clears, choices and the results count.
const live = ref('');
const announce = (message: string) => {
    live.value = '';
    void nextTick(() => (live.value = message));
};

function setOpen(next: boolean) {
    if (next === open.value || (next && !interactive.value)) return;
    open.value = next;
    chip.value = null;
    if (next) {
        chosenAtOpen.value = [...values.value];
        emit('open');
        return;
    }
    resetText();
    emit('search', '');
    emit('close');
}
function resetText() {
    query.value = '';
    typed.value = false;
    clearTimeout(searchTimer);
}

const update = (value: InputSelectValue | InputSelectValue[] | null) => emit('update:modelValue', value);
function choose(option: InputSelectOption) {
    if (option.disabled || !interactive.value) return;
    if (!props.multiple) {
        if (values.value[0] !== option.value || values.value.length !== 1) update(option.value);
        setOpen(false);
        return;
    }
    const added = !values.value.includes(option.value);
    update(toggleValue(values.value, option.value));
    announce(interpolate(added ? text.value['live.added'] : text.value['live.removed'], { label: option.label }));
    resetText();
}
function toggleAll() {
    const next = selectAll(values.value, shown.value);
    update(next.values);
    resetText();
}
function create() {
    const label = query.value.trim();
    emit('create', label);
    announce(interpolate(text.value['live.created'], { label }));
    resetText();
    if (!props.multiple) setOpen(false);
}
function onSelect(event: Event, row: NavRow) {
    event.preventDefault();
    if (row === SELECT_ALL) toggleAll();
    else if (row === CREATE) create();
    else choose(row as InputSelectOption);
    focusInput();
}
function remove(option: InputSelectOption) {
    if (!interactive.value) return;
    update(values.value.filter(value => value !== option.value));
    announce(interpolate(text.value['live.removed'], { label: option.label }));
    focusInput();
}
function clear() {
    update(props.multiple ? [] : null);
    resetText();
    chip.value = null;
    announce(text.value['live.cleared']);
    focusInput();
}

// Chips past `maxChips` become one count chip; the keyboard path moves through the shown chips only.
const countMode = computed(() => props.multiple && props.maxChips !== null && chosen.value.length > props.maxChips);
const chips = computed(() => (props.multiple && !countMode.value ? chosen.value : []));
const chip = ref<number | null>(null);
watch(chip, index => index !== null && chips.value[index] && announce(chips.value[index].label));

// Typed text: `search` after `debounce` ms of quiet (remote: only once long enough), the results count once filtering settles.
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let countTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, value => {
    if (!typed.value) return;
    clearTimeout(searchTimer);
    if (props.filter === 'remote' && !searchReady(value, props.minSearchLength)) return;
    searchTimer = setTimeout(() => emit('search', value), props.debounce);
});
watch([shown, () => props.loading], () => {
    clearTimeout(countTimer);
    if (!open.value || !typed.value || props.loading) return;
    countTimer = setTimeout(() => announce(resultsMessage(text.value, shown.value.length)), props.debounce + 250);
});
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(countTimer);
});

// Typeahead with search off: letters typed within half a second jump to the first enabled option starting with them.
let typeahead = '';
let typeaheadTimer: ReturnType<typeof setTimeout> | undefined;
async function jump(key: string) {
    clearTimeout(typeaheadTimer);
    typeahead += fold(key);
    typeaheadTimer = setTimeout(() => (typeahead = ''), 500);
    setOpen(true);
    await nextTick();
    const match = shown.value.find(option => !option.disabled && fold(option.label).startsWith(typeahead));
    if (match) root.value?.highlightItem?.(match.value);
}
async function moveTo(by: number | 'first' | 'last') {
    const rows = navRows.value;
    const current = rows.findIndex(row => row !== null && rowValue(row) === highlighted.value);
    const target = moveHighlight(rows, current, by);
    if (target >= 0) root.value?.highlightItem?.(rowValue(rows[target]!));
}
const highlightedElement = () => (document.getElementById(listId)?.querySelector('[data-highlighted]') as HTMLElement | null);

// The keys this component owns, caught before Reka's own handlers (InputSelect.md, Keyboard); the rest (arrows, Enter on a row) stay Reka's.
function onKeydown(event: KeyboardEvent) {
    const own = () => {
        event.stopPropagation();
        event.preventDefault();
    };
    if (readOnly.value) {
        if (!['Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Shift'].includes(event.key) && !event.metaKey && !event.ctrlKey) own();
        return;
    }
    if (props.disabled) return;
    const mac = /mac/i.test(navigator.platform);
    if (props.multiple && query.value === '' && chips.value.length > 0 && ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
        const next = chipKey(event.key, chip.value, chips.value.length);
        if (next.handled) {
            own();
            const removed = next.remove !== null ? chips.value[next.remove] : null;
            chip.value = next.chip;
            if (removed) remove(removed);
            return;
        }
    }
    if (event.key !== 'Shift') chip.value = null;
    switch (event.key) {
        case 'Enter':
            if (!open.value) event.stopPropagation(); // A closed field submits its form, as a text input does.
            return;
        case 'Escape':
            own();
            if (open.value) setOpen(false);
            else if (typed.value) resetText();
            else if (canClear.value) clear();
            return;
        case 'Tab':
            event.stopPropagation();
            setOpen(false);
            return;
        case 'Home':
        case 'End':
            if (open.value && (event.ctrlKey || event.metaKey)) {
                own();
                void moveTo(event.key === 'Home' ? 'first' : 'last');
            } else event.stopPropagation(); // the text cursor moves, as in any text box
            return;
        case 'PageUp':
        case 'PageDown':
            if (!open.value) return;
            own();
            void moveTo(event.key === 'PageUp' ? -10 : 10);
            return;
        case 'ArrowUp':
        case 'ArrowDown':
            if (open.value && mac && event.metaKey) {
                own();
                void moveTo(event.key === 'ArrowUp' ? 'first' : 'last');
            } else if (!open.value && event.altKey) {
                own();
                setOpen(true);
            }
            return;
        case ' ':
            if (searchOn.value) return;
            own();
            if (!open.value) setOpen(true);
            else highlightedElement()?.click();
            return;
    }
    if (!searchOn.value && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
        own();
        void jump(event.key);
    }
}
function onFieldPointerDown(event: PointerEvent) {
    // A press on the field's padding or chips (not a button, not the input) focuses the input.
    if (event.target instanceof HTMLElement && !event.target.closest('button,input')) {
        event.preventDefault();
        focusInput();
    }
}

// The list is named by the field's label, read when it opens.
const listId = `${uid}-list`;
const listName = ref<string | undefined>(undefined);
watch(open, async isOpen => {
    if (!isOpen) return;
    const label = document.querySelector(`label[for="${CSS.escape(inputId.value)}"]`);
    listName.value = (attrs['aria-label'] as string | undefined) ?? label?.textContent?.trim() ?? undefined;
    viewportHeight.value = window.visualViewport?.height ?? window.innerHeight;
});
// Phones: the panel stays above the on-screen keyboard (the visual viewport, less the edge margins).
const viewportHeight = ref(0);
const onViewport = () => (viewportHeight.value = window.visualViewport?.height ?? window.innerHeight);
if (typeof window !== 'undefined') window.visualViewport?.addEventListener('resize', onViewport);
onBeforeUnmount(() => window.visualViewport?.removeEventListener('resize', onViewport));

// The hidden native inputs tell a surrounding uncontrolled <Form> the value changed.
const natives = ref<HTMLElement | null>(null);
watch(values, async () => {
    await nextTick();
    natives.value?.dispatchEvent(new Event('input', { bubbles: true }));
});

const placeholderShown = computed(() => (props.multiple ? chosen.value.length === 0 : chosen.value.length === 0 && !typed.value));
const inputAttrs = computed(() => {
    const rest: Record<string, unknown> = { ...attrs };
    delete rest.class;
    delete rest.style;
    return rest;
});
const autocomplete = computed(() => (searchOn.value ? 'list' : 'none'));
const fieldClass = computed(() => [
    'relative flex w-full min-h-11 flex-wrap items-center gap-1 rounded-md border bg-background py-1 ps-3 pe-14 text-base shadow-xs transition-[color,box-shadow] sm:min-h-9 sm:text-sm',
    readOnly.value ? 'border-border bg-muted shadow-none focus-within:ring-2 focus-within:ring-ring' : props.invalid ? 'border-destructive focus-within:ring-2 focus-within:ring-destructive' : 'border-input hover:border-ring/60 focus-within:ring-2 focus-within:ring-ring',
    props.disabled && 'cursor-not-allowed opacity-60',
    attrs.class as string | undefined,
]);
</script>

<template>
    <div popover="manual" class="contents" :style="attrs.style as string | undefined">
        <ComboboxRoot
            ref="root"
            :model-value="multiple ? values : (values[0] ?? undefined)"
            :multiple="multiple"
            :open="open"
            :disabled="disabled"
            ignore-filter
            open-on-click
            :reset-search-term-on-blur="false"
            :reset-search-term-on-select="false"
            class="w-full"
            @update:open="setOpen"
            @highlight="highlighted = $event?.value"
        >
            <ComboboxAnchor :class="fieldClass" @keydown.capture="onKeydown" @pointerdown="onFieldPointerDown">
                <ul v-if="chips.length" :aria-label="text.chips" class="contents">
                    <li v-for="(option, index) in chips" :key="option.value" :class="['inline-flex max-w-full items-center gap-1 rounded-sm border border-border bg-muted px-2 py-0.5 text-xs text-foreground', chip === index && 'ring-2 ring-ring']" :data-chip-highlighted="chip === index || undefined">
                        <span class="truncate"><slot name="chip" :option="option" :remove="() => remove(option)">{{ option.label }}</slot></span>
                        <button v-if="interactive" type="button" tabindex="-1" :aria-label="interpolate(text.remove, { label: option.label })" class="-my-4 -me-3 inline-flex min-h-11 min-w-11 items-center justify-center rounded-sm text-muted-foreground hover:text-foreground sm:my-0 sm:me-0 sm:min-h-0 sm:min-w-0" @pointerdown.prevent @click.stop="remove(option)">
                            <X class="size-3" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <span v-else-if="countMode" class="inline-flex items-center rounded-sm border border-border bg-muted px-2 py-0.5 text-xs text-foreground">{{ interpolate(text.count, { count: chosen.length }) }}</span>
                <span v-if="readOnly && chosen.length === 0" class="text-muted-foreground">{{ text.none }}</span>
                <ComboboxInput
                    ref="input"
                    v-model="fieldText"
                    v-bind="inputAttrs"
                    :id="inputId"
                    :readonly="readOnly || undefined"
                    :aria-readonly="readOnly || undefined"
                    :aria-invalid="(attrs['aria-invalid'] as string | undefined) ?? (invalid || undefined)"
                    :aria-autocomplete="autocomplete"
                    :required="(nativeRequired && values.length === 0) || undefined"
                    :placeholder="placeholderShown && !readOnly ? placeholder : undefined"
                    autocomplete="off"
                    :class="['min-w-16 flex-1 bg-transparent py-1 text-base text-foreground outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed sm:py-0.5 sm:text-sm sm:leading-6', readOnly && 'cursor-default', readOnly && chosen.length === 0 && 'sr-only']"
                    @focus="chip = null"
                />
                <div v-if="interactive" class="absolute inset-y-0 end-0 flex items-center gap-0.5 pe-2.5">
                    <button v-if="canClear" type="button" tabindex="-1" :aria-label="multiple ? text.clear_all : text.clear" class="-my-2 inline-flex min-h-11 min-w-8 items-center justify-center text-muted-foreground hover:text-foreground sm:my-0 sm:min-h-0 sm:min-w-0" @pointerdown.prevent @click.stop="clear">
                        <X class="size-4" aria-hidden="true" />
                    </button>
                    <ComboboxTrigger tabindex="-1" class="-my-2 inline-flex min-h-11 min-w-8 items-center justify-center text-muted-foreground sm:my-0 sm:min-h-0 sm:min-w-0" @pointerdown.prevent>
                        <ChevronDown class="size-4" aria-hidden="true" />
                    </ComboboxTrigger>
                </div>
            </ComboboxAnchor>
            <span class="sr-only" role="status" aria-live="polite">{{ live }}</span>
            <div ref="natives" hidden>
                <template v-if="name && !disabled">
                    <template v-if="multiple"><input v-for="value in values" :key="value" type="hidden" :name="`${name}[]`" :value="value" /></template>
                    <input v-else type="hidden" :name="name" :value="values[0] ?? ''" />
                </template>
            </div>
            <ComboboxPortal>
                <ComboboxContent
                    :id="listId"
                    position="popper"
                    side="bottom"
                    align="start"
                    :side-offset="4"
                    :collision-padding="8"
                    :aria-label="listName"
                    :aria-busy="loading || undefined"
                    :style="viewportHeight ? { '--kit-select-viewport': `${viewportHeight}px` } : undefined"
                    class="z-[100] max-h-[min(18rem,var(--reka-combobox-content-available-height),calc(var(--kit-select-viewport,100dvh)-1rem))] w-max min-w-(--reka-combobox-trigger-width) max-w-[min(24rem,calc(100vw-1rem))] overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:duration-100 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=closed]:duration-75 motion-reduce:animate-none"
                    @escape-key-down.prevent
                >
                    <ComboboxViewport class="max-h-[inherit] overflow-y-auto">
                        <div v-if="loading" class="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground">
                            <slot name="loading"><LoaderCircle class="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />{{ text.loading }}</slot>
                        </div>
                        <div v-if="tooShort" class="py-4 text-center text-sm text-muted-foreground">{{ interpolate(text.too_short, { count: minSearchLength }) }}</div>
                        <ComboboxItem v-if="selectAllShown" :value="SELECT_ALL" :text-value="selectAllLabel" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground sm:min-h-0" @select="onSelect($event, SELECT_ALL)">
                            <span class="size-4 shrink-0" aria-hidden="true" />{{ selectAllLabel }}
                        </ComboboxItem>
                        <template v-for="(group, groupIndex) in groups" :key="group.label ?? groupIndex">
                            <ComboboxGroup :class="grouped && groupIndex > 0 && 'mt-1 border-t border-border pt-1'">
                                <ComboboxLabel v-if="group.label !== null" class="px-2 py-1.5 text-xs font-medium text-muted-foreground">{{ group.label }}</ComboboxLabel>
                                <ComboboxItem
                                    v-for="option in group.options"
                                    :key="option.value"
                                    v-slot="{ selected }"
                                    :value="option.value"
                                    :text-value="option.label"
                                    :disabled="option.disabled"
                                    :aria-disabled="option.disabled || undefined"
                                    class="flex min-h-11 cursor-pointer items-start gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground sm:min-h-0"
                                    @select="onSelect($event, option)"
                                >
                                    <Check :class="['mt-0.5 size-4 shrink-0 text-foreground', !selected && 'invisible']" aria-hidden="true" />
                                    <span class="min-w-0 flex-1 break-words">
                                        <slot name="option" :option="option" :selected="selected" :highlighted="highlighted === option.value" :disabled="!!option.disabled">
                                            {{ option.label }}
                                            <span v-if="option.description" class="block text-xs text-muted-foreground">{{ option.description }}</span>
                                        </slot>
                                    </span>
                                </ComboboxItem>
                            </ComboboxGroup>
                        </template>
                        <ComboboxItem v-if="createShown" :value="CREATE" :text-value="createLabel" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground sm:min-h-0" @select="onSelect($event, CREATE)">
                            <span class="size-4 shrink-0" aria-hidden="true" />{{ createLabel }}
                        </ComboboxItem>
                        <div v-if="shown.length === 0 && !loading && !tooShort && !createShown" class="py-4 text-center text-sm text-muted-foreground">
                            <slot name="empty" :query="activeQuery">{{ activeQuery.trim() ? interpolate(text.empty_query, { query: activeQuery.trim() }) : text.empty }}</slot>
                        </div>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </div>
</template>
