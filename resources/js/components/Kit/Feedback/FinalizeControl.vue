<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, useId } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Button from '@/components/Kit/Form/Button.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';

/*
 * The certify step of a finalize gate (kit FinalizeControl): the statement the person agrees to, a checkbox to agree, and the Finalize button from the
 * server's `finalize` action. The button stays disabled until the box is ticked, and, while the server sends the action disabled, shows its reason. It
 * posts `certified` to the action's URL, which carries the revision the person saw: a changed checklist is refused there, never trusted here. Absent when
 * the server sends no action (the viewer may not certify).
 *
 * A flow that declares extras (`Flow::extras()`, an optional "what changed" note, say) passes their values as `extra` and puts their inputs in the
 * `extra` slot, which renders between the checkbox and the button with the page's errors for those fields and whether the form is disabled. The values
 * ride on the same post; `certified` is always the control's own. The server reads only the fields the flow declares and validates them, so an error
 * comes back inline on its field.
 */
const props = withDefaults(
    defineProps<{
        statement: string;
        action: ActionDescriptor | null | undefined;
        agreeLabel?: string;
        extra?: Record<string, unknown>;
    }>(),
    { agreeLabel: 'I agree to the statement above', extra: () => ({}) },
);
defineSlots<{
    extra?: (props: {
        errors: Record<string, string>;
        disabled: boolean;
    }) => unknown;
}>();
const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const id = `finalize-certified-${useId()}`;
const agreed = ref(false);
const busy = ref(false);
const blocked = computed(() => !props.action || props.action.disabled === true);

function submit(): void {
    if (!props.action || blocked.value || !agreed.value || busy.value) return;
    busy.value = true;
    router.visit(props.action.url, {
        method: 'post',
        data: { ...props.extra, certified: true },
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
    });
}
</script>
<template>
    <form
        v-if="action"
        class="grid gap-3"
        data-finalize-control
        @submit.prevent="submit"
    >
        <blockquote
            class="border-border text-foreground border-l-2 pl-3 text-sm whitespace-pre-line"
            data-finalize-statement
        >
            {{ statement }}
        </blockquote>
        <InputCheckbox :id="id" v-model="agreed" :disabled="blocked">{{
            agreeLabel
        }}</InputCheckbox>
        <slot name="extra" :errors="errors" :disabled="blocked || busy" />
        <p
            v-if="blocked && action.reason"
            class="text-muted-foreground text-sm"
            data-finalize-reason
        >
            {{ action.reason }}
        </p>
        <Button
            type="submit"
            class="justify-self-start"
            :disabled="blocked || !agreed || busy"
            >{{ action.label }}</Button
        >
    </form>
</template>
