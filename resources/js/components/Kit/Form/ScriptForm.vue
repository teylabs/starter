<script setup lang="ts">
// A form posted by script, not by a visit (docs/script-form.md): a ceremony such as Add a passkey (confirm the password, then WebAuthn), where the page's own
// handler makes the requests. It renders a <form> marked `data-script-form`, so Enter and the submit button work as in any form and form guards can tell it
// from a hand-rolled frame; the browser never posts it. `submit` is emitted with the event, and not at all while `busy` (no second ceremony on a double
// press); `busy` also sets aria-busy. Its slot gets `busy`. Field errors and the failure line stay the page's own: the handler knows what went wrong.
const props = withDefaults(defineProps<{ busy?: boolean }>(), { busy: false });
const emit = defineEmits<{ submit: [event: SubmitEvent] }>();

function onSubmit(event: Event): void {
    if (props.busy) return;
    emit('submit', event as SubmitEvent);
}
</script>
<template>
    <form
        data-script-form
        :aria-busy="busy ? 'true' : undefined"
        @submit.prevent="onSubmit"
    >
        <slot :busy="busy" />
    </form>
</template>
