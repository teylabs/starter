<script setup lang="ts">
import {
    computed,
    inject,
    nextTick,
    onMounted,
    onBeforeUnmount,
    ref,
    useId,
    watch,
} from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import { usePage } from '@inertiajs/vue3';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import { numberDefaults, numberFormat, type NumberOptions } from './number';
import { rowErrorsKey } from './repeaterState';
import { useLocked } from './useLocked';
// Inside a locked LockedForm the visible field is read-only through the context (the LockedForm spec): native `readonly`, the `muted` fill and the value at full contrast; the hidden canonical control stays. A caller's own `disabled` still wins.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<
        NumberOptions & {
            name: string;
            label: string;
            id?: string;
            defaultValue?: string;
            unit?: string;
            suffix?: string;
            placeholder?: string;
            error?: string;
            disabled?: boolean;
            required?: boolean;
        }
    >(),
    { defaultValue: '', kind: 'decimal', grouping: true },
);
const emit = defineEmits<{ canonical: [value: string] }>();
const id = props.id ?? useId();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
// Locale and currency come from the props, else the host's shared `kitNumber` (config kit.currency, the app locale), else the browser; money never guesses a currency.
const page = usePage();
const defaults = computed(() =>
    numberDefaults(
        page.props.kitNumber as
            { locale?: string; currency?: string } | undefined,
        typeof navigator === 'undefined' ? null : navigator.language,
    ),
);
const options = computed(() => ({
    ...props,
    locale: props.locale ?? defaults.value.locale,
    currency: props.currency ?? defaults.value.currency,
}));
if (props.kind === 'money' && !options.value.currency && import.meta.env?.DEV)
    console.warn(
        `<InputNumber name="${props.name}" kind="money"> has no currency: pass \`currency\` or set config kit.currency. It shows no symbol rather than guess one.`,
    );
const codec = computed(() => numberFormat(options.value));
// The visible unit: the caller's `unit`/`suffix`, else the currency code or "percent"; it names the field for assistive technology, the symbol is decoration.
const spoken = computed(
    () =>
        props.unit ||
        props.suffix ||
        (props.kind === 'money'
            ? options.value.currency
            : props.kind === 'percent'
              ? 'percent'
              : undefined),
);
// A defaultValue that is not in canonical form (money in integer minor units, a decimal at its scale) is read as if typed,
// so the field still renders and either holds the parsed value or shows why it can't; dev builds name the field.
function start(raw: string) {
    try {
        return { value: raw, text: codec.value.format(raw), hint: '' };
    } catch {
        if (import.meta.env?.DEV)
            console.warn(
                `<InputNumber name="${props.name}" kind="${props.kind}"> defaultValue "${raw}" is not canonical; read as typed text.`,
            );
        const parsed = codec.value.parse(raw);

        return { value: parsed.value, text: raw, hint: parsed.error };
    }
}
const first = start(props.defaultValue);
const value = ref(first.value);
const text = ref(first.text);
const hint = ref(first.hint);
const input = ref<HTMLInputElement>();
const hidden = ref<HTMLInputElement>();
const form = useOptionalFormContext();
const rowErrors = inject(rowErrorsKey, undefined);
const message = computed(() => props.error ?? hint.value);
const serverError = computed(
    () =>
        rowErrors?.value[props.name] ??
        form?.errors?.[props.name.replace(/\[([^\]]+)\]/g, '.$1')],
);
const error = computed(() => message.value || serverError.value);
async function update(event: Event) {
    text.value = (event.target as HTMLInputElement).value;
    const parsed = codec.value.parse(text.value);
    value.value = parsed.value;
    hint.value = parsed.error;
    input.value?.setCustomValidity(parsed.error);
    emit('canonical', value.value);
    await nextTick();
    hidden.value?.dispatchEvent(new Event('input', { bubbles: true }));
}
function blur() {
    if (!hint.value) {
        hint.value = codec.value.validate(value.value);
        text.value = codec.value.format(value.value);
    }
    input.value?.setCustomValidity(hint.value);
}
const reset = () =>
    nextTick(() => {
        const next = start(props.defaultValue);
        value.value = next.value;
        text.value = next.text;
        hint.value = next.hint;
        if (input.value) {
            input.value.value = text.value;
            input.value.setCustomValidity(next.hint);
        }
        if (hidden.value) hidden.value.value = value.value;
    });
onMounted(() => input.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    input.value?.closest('form')?.removeEventListener('reset', reset),
);
watch(
    () => props.defaultValue,
    (v) => {
        const next = start(v);
        value.value = next.value;
        text.value = next.text;
        hint.value = next.hint;
        input.value?.setCustomValidity(next.hint);
    },
);
</script>
<template>
    <Field
        :id="id"
        :name="name"
        :label="spoken ? `${label}, ${spoken}` : label"
        :error="error || undefined"
        class="min-w-0"
    >
        <div
            :class="[
                'focus-within:border-ring focus-within:ring-ring/50 has-[input[aria-invalid=true]]:border-destructive has-[input[aria-invalid=true]]:ring-destructive/20 flex h-9 w-full min-w-0 items-center gap-2 rounded-md border px-3 transition-[color,box-shadow] focus-within:ring-[3px] has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50',
                locked
                    ? 'border-border bg-muted shadow-none'
                    : 'border-input dark:bg-input/30 bg-transparent shadow-xs',
            ]"
            :data-locked="locked || undefined"
        >
            <span
                v-if="codec.affix.prefix.trim() && !(unit || suffix)"
                aria-hidden="true"
                class="text-muted-foreground shrink-0 text-sm"
                data-number-prefix
                >{{ codec.affix.prefix.trim() }}</span
            >
            <input
                :id="id"
                ref="input"
                v-bind="$attrs"
                :value="text"
                type="text"
                :inputmode="kind === 'integer' ? 'numeric' : 'decimal'"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="locked || undefined"
                :required="required"
                :aria-invalid="
                    Boolean(error) ||
                    ($attrs['aria-invalid'] as 'true' | 'false' | undefined)
                "
                :aria-describedby="
                    [
                        ...new Set(
                            [
                                ...String(
                                    $attrs['aria-describedby'] ?? '',
                                ).split(/\s+/),
                                error ? `${id}-error` : '',
                            ].filter(Boolean),
                        ),
                    ].join(' ') || undefined
                "
                :class="[
                    'text-foreground placeholder:text-muted-foreground h-full w-full min-w-0 bg-transparent py-1 text-base outline-none md:text-sm',
                    locked && 'cursor-default',
                ]"
                @input="update"
                @blur="blur"
            />
            <span
                v-if="unit || suffix"
                aria-hidden="true"
                class="text-muted-foreground shrink-0 text-sm"
                >{{ suffix || unit }}</span
            >
            <span
                v-else-if="codec.affix.suffix.trim()"
                aria-hidden="true"
                class="text-muted-foreground shrink-0 text-sm"
                data-number-suffix
                >{{ codec.affix.suffix.trim() }}</span
            >
        </div>
        <input
            ref="hidden"
            type="hidden"
            :name="name"
            :value="value"
            :disabled="disabled"
        />
    </Field>
</template>
