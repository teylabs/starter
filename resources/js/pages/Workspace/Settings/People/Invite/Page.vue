<script setup lang="ts">
import { Field, Modal, Input, Select, Textarea } from '@kit/ui';

// Invite member, a server-driven modal over People: the invitation goes to the server from Modal's footer; it closes when sent and People refreshes.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.InviteMemberViewModel>();
</script>
<template>
    <Modal :title="title" size="md" description="They get a link to join this workspace with the role you choose." :submit="submit" busy-label="Sending…">
        <Field id="invite-email" name="email" label="Email address"><Input id="invite-email" name="email" type="email" required /></Field>
        <Field id="invite-role" name="role" label="Role"><Select id="invite-role" name="role" :model-value="roles[0]?.value" :options="roles" /></Field>
        <Field id="invite-message" name="message" label="Personal message (optional)" description="It sits at the top of their invitation email and on the invitation page."><Textarea id="invite-message" name="message" maxlength="500" rows="3" /></Field>
    </Modal>
</template>
