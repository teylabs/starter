<script setup lang="ts">
import { computed, inject, nextTick, onMounted, onBeforeUnmount, ref, useId, watch } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import { useFormContext } from '@inertiajs/vue3';
import { numberFormat, type NumberOptions } from './number';
import { rowErrorsKey } from './repeaterState';
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<NumberOptions & { name: string; label: string; id?: string; defaultValue?: string; unit?: string; suffix?: string; placeholder?: string; error?: string; disabled?: boolean; required?: boolean }>(), { defaultValue: '', kind: 'decimal', grouping: true });
const emit = defineEmits<{ canonical: [value: string] }>();
const id = props.id ?? useId();
const codec = computed(() => numberFormat(props));
const value = ref(props.defaultValue);
const text = ref(codec.value.format(value.value));
const hint = ref('');
const input = ref<HTMLInputElement>();
const hidden = ref<HTMLInputElement>();
const form = useFormContext();
const rowErrors = inject(rowErrorsKey, undefined);
const message = computed(() => props.error ?? hint.value);
const serverError = computed(() => rowErrors?.value[props.name] ?? form?.errors?.[props.name.replace(/\[([^\]]+)\]/g, '.$1')]);
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
const reset = () => nextTick(() => {
    value.value = props.defaultValue;
    text.value = codec.value.format(value.value);
    hint.value = '';
    if (input.value) { input.value.value = text.value; input.value.setCustomValidity(''); }
    if (hidden.value) hidden.value.value = value.value;
});
onMounted(() => input.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() => input.value?.closest('form')?.removeEventListener('reset', reset));
watch(() => props.defaultValue, v => { value.value = v; text.value = codec.value.format(v); hint.value = ''; input.value?.setCustomValidity(''); });
</script>
<template>
    <Field :id="id" :name="name" :label="unit || suffix ? `${label}, ${unit || suffix}` : label" :error="error || undefined" class="min-w-0">
        <div class="flex h-9 w-full min-w-0 items-center gap-2 rounded-md border border-input bg-transparent px-3 shadow-xs transition-[color,box-shadow] dark:bg-input/30 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 has-[input[aria-invalid=true]]:border-destructive has-[input[aria-invalid=true]]:ring-destructive/20 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50">
            <input :id="id" ref="input" v-bind="$attrs" :value="text" type="text" :inputmode="kind === 'integer' ? 'numeric' : 'decimal'" :placeholder="placeholder" :disabled="disabled" :required="required" :aria-invalid="Boolean(error) || undefined" :aria-describedby="error ? `${id}-error` : undefined" class="h-full w-full min-w-0 bg-transparent py-1 text-base text-foreground outline-none placeholder:text-muted-foreground md:text-sm" @input="update" @blur="blur" />
            <span v-if="unit || suffix" aria-hidden="true" class="shrink-0 text-sm text-muted-foreground">{{ suffix || unit }}</span>
        </div>
        <input ref="hidden" type="hidden" :name="name" :value="value" :disabled="disabled" />
    </Field>
</template>
