<script setup lang="ts">
import {
    submitHiddenForm,
    type HiddenFormMethod,
    type HiddenFormValue,
} from './hiddenForm';

// A control that submits a native hidden form (docs/form-link.md): a download (`target="_self"`, the default) or a new tab (`target="_blank"`), by GET or a
// POST the browser makes itself. It submits during the click, so a new tab is not caught by a popup blocker, and the form lives on <body>, so closing the
// modal the control sits in does not cancel it. `as` is the element or component drawn (a button by default); its slot is the label.
const props = withDefaults(
    defineProps<{
        href: string;
        method?: HiddenFormMethod;
        data?: Record<string, HiddenFormValue>;
        target?: string;
        as?: string | object;
        disabled?: boolean;
    }>(),
    {
        method: 'get',
        data: () => ({}),
        target: '_self',
        as: 'button',
        disabled: false,
    },
);
const emit = defineEmits<{ submitted: [] }>();

function submit(): void {
    if (props.disabled) return;
    submitHiddenForm({
        url: props.href,
        method: props.method,
        data: props.data,
        target: props.target,
    });
    emit('submitted');
}

defineExpose({ submit });
</script>
<template>
    <component
        :is="as"
        :type="as === 'button' ? 'button' : undefined"
        :disabled="as === 'button' ? disabled : undefined"
        :aria-disabled="disabled || undefined"
        data-slot="form-link"
        @click.prevent="submit"
        ><slot
    /></component>
</template>
