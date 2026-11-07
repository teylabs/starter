<script setup lang="ts">
import { computed, ref, useAttrs, useId } from 'vue';
import { usePage } from '@inertiajs/vue3';
import InputSelect from './InputSelect.vue';
import {
    addTags,
    rejectionMessage,
    splitTags,
    tagsMessages,
    type TagRules,
    type TagsMessages,
} from './tags';

// Free-text tags as chips, on the kit's InputSelect (multiple and creatable), so the combobox, the chips, their "Remove …" buttons, Backspace on an empty field
// (the first selects the last chip, the second removes it) and the live announcements are InputSelect's own. Typing a separator (`,` or `;` by default) or Enter adds the typed tag; pasting text with separators or line
// breaks adds each piece. Tags are tidied (spaces collapsed, then `normalize`), a duplicate is the same tag ignoring case in the locale, and `maxLength`, `pattern`
// (a regular expression the whole tag must match) and `max` refuse a tag rather than trim it, with the reason under the field (polite, and on the input's
// description). `suggestions` are offered as options and filtered locally; the value is the list of tags, submitted as `<name>[]`. Strings come from the kit's
// `ui` lang file (`tags.*`, shared as `kitUi`) and InputSelect's (`select.*`); `messages` overrides the tags ones. Caller attributes land on InputSelect's input, so `required` becomes `aria-required` there and a caller's `aria-invalid` wins over `invalid`.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        name?: string;
        suggestions?: string[];
        separators?: string[];
        max?: number | null;
        maxLength?: number | null;
        pattern?: string | null;
        normalize?: ((tag: string) => string) | null;
        locale?: string;
        placeholder?: string;
        disabled?: boolean;
        readonly?: boolean;
        invalid?: boolean;
        messages?: Partial<TagsMessages>;
    }>(),
    {
        name: undefined,
        suggestions: () => [],
        separators: () => [',', ';'],
        max: null,
        maxLength: null,
        pattern: null,
        normalize: null,
        locale: undefined,
        placeholder: undefined,
        messages: () => ({}),
    },
);
const model = defineModel<string[]>({ default: () => [] });
const attrs = useAttrs();
const page = usePage();
const locale = computed(
    () =>
        props.locale ??
        (page.props as { kitNumber?: { locale?: string } }).kitNumber?.locale ??
        (typeof navigator === 'undefined' ? 'en-CA' : navigator.language),
);
const rules = computed<TagRules>(() => ({
    separators: props.separators,
    max: props.max,
    maxLength: props.maxLength,
    pattern: props.pattern,
    normalize: props.normalize,
    locale: locale.value,
}));
const text = computed(() => {
    let shared: unknown;
    try {
        shared = (page.props as { kitUi?: { tags?: unknown } }).kitUi?.tags;
    } catch {
        shared = undefined;
    }
    return tagsMessages(shared, props.messages);
});
// The chosen tags first, so each chip has its option, then the suggestions not already chosen.
const options = computed(() => {
    const chosen = new Set(model.value);
    return [
        ...model.value,
        ...props.suggestions.filter((tag) => !chosen.has(tag)),
    ].map((tag) => ({ value: tag, label: tag }));
});
const problem = ref('');
const problemId = `${useId()}-tags-problem`;
function add(incoming: string[]) {
    const result = addTags(model.value, incoming, rules.value);
    if (result.added.length) model.value = result.values;
    problem.value = rejectionMessage(
        result.rejected[0],
        text.value,
        rules.value,
    );
}
function clearTyped(input: HTMLInputElement) {
    navigated = false;
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
}
// Enter adds what was typed, not the suggestion the list happens to highlight first: only after the person moves through the list with the arrow keys does
// Enter take the highlighted suggestion (InputSelect's own handling). Caught in the capture phase on the input, before the combobox's handler.
let navigated = false;
function onKeydownCapture(event: KeyboardEvent) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') navigated = true;
    if (event.key !== 'Enter' || event.isComposing || navigated) return;
    const input = event.target as HTMLInputElement;
    if (input.value.trim() === '') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    add([input.value]);
    clearTyped(input);
}
function onKeydown(event: KeyboardEvent) {
    if (!props.separators.includes(event.key) || event.isComposing) return;
    const input = event.target as HTMLInputElement;
    event.preventDefault();
    if (input.value.trim() === '') return;
    add([input.value]);
    clearTyped(input);
}
function onPaste(event: ClipboardEvent) {
    const pasted = event.clipboardData?.getData('text') ?? '';
    if (
        !/[\r\n\t]/.test(pasted) &&
        !props.separators.some((separator) => pasted.includes(separator))
    )
        return;
    event.preventDefault();
    const input = event.target as HTMLInputElement;
    add(splitTags(input.value + pasted, rules.value));
    clearTyped(input);
}
const inputBindings = computed(() => ({
    ...attrs,
    onKeydown: [onKeydown, attrs.onKeydown].flat().filter(Boolean),
    onKeydownCapture: [onKeydownCapture, attrs.onKeydownCapture]
        .flat()
        .filter(Boolean),
    onInput: [
        () => {
            navigated = false;
        },
        attrs.onInput,
    ]
        .flat()
        .filter(Boolean),
    onPaste: [onPaste, attrs.onPaste].flat().filter(Boolean),
    'aria-describedby':
        [attrs['aria-describedby'], problem.value ? problemId : null]
            .filter(Boolean)
            .join(' ') || undefined,
    'data-input-tags': '',
}));
</script>
<template>
    <div class="grid gap-1">
        <InputSelect
            v-bind="inputBindings"
            :model-value="model"
            :options="options"
            :name="name"
            multiple
            creatable
            :searchable="true"
            filter="local"
            :clearable="false"
            :disabled="disabled"
            :readonly="readonly"
            :invalid="invalid || Boolean(problem)"
            :placeholder="placeholder"
            @update:model-value="
                (value) => {
                    model = (Array.isArray(value) ? value : []).map(String);
                    problem = '';
                }
            "
            @create="(tag) => add([tag])"
        />
        <p
            :id="problemId"
            aria-live="polite"
            :class="['text-destructive-text text-sm', !problem && 'sr-only']"
            data-tags-problem
        >
            {{ problem }}
        </p>
    </div>
</template>
