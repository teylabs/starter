<script setup lang="ts">
import { Field, Modal, Input, InputSelect, Textarea } from '@kit/ui';
import { ref } from 'vue';

// Invite member, a server-driven modal over People: the invitation goes to the server from Modal's footer; it closes when sent and People refreshes.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.InviteMemberViewModel>();
// InputSelect is controlled: the chosen role lives here, and its hidden input submits it.
const choice = ref(props.roles[0]?.value ?? null);
</script>
<template>
    <Modal :title="title" size="md" description="They get a link to join this workspace with the role you choose." :submit="submit" busy-label="Sending…">
        <Field id="invite-email" name="email" label="Email address"><Input id="invite-email" name="email" type="email" required /></Field>
        <Field id="invite-role" name="role" label="Role"><InputSelect id="invite-role" name="role" v-model="choice" :options="roles" /></Field>
        <Field id="invite-message" name="message" label="Personal message (optional)" description="It sits at the top of their invitation email and on the invitation page."><Textarea id="invite-message" name="message" maxlength="500" rows="3" /></Field>
    </Modal>
</template>
