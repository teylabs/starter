<script setup lang="ts">
import { Alert, Field, Input, Modal, PasswordInput } from '@kit/ui';

// Transfer ownership, a server-driven modal over People (the owner only; the server picked a member who may receive it). The workspace's name is typed to confirm
// and the password asked for again; the server checks both and refuses a mismatch.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.TransferOwnershipViewModel>();
</script>
<template>
    <Modal :title="title" size="sm" :submit="submit" busy-label="Transferring…">
        <input type="hidden" name="membership" :value="membership" />
        <Alert variant="warning" data-transfer-warning>{{ recipient }} becomes the owner and you become {{ demoted_to }}. Only the new owner can transfer it back.</Alert>
        <Field id="transfer-confirmation" name="confirmation" :label="`Type ${workspace_name} to confirm`"><Input id="transfer-confirmation" name="confirmation" required autocomplete="off" /></Field>
        <Field id="transfer-password" name="password" label="Your password"><PasswordInput id="transfer-password" name="password" required autocomplete="current-password" /></Field>
    </Modal>
</template>
