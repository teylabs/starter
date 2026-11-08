<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { Alert, Field, FormCard, Page, PasswordInput } from '@kit/ui';
import { computed } from 'vue';

// Delete your account with your current password. While you own a workspace the server refuses, and this page names them: transfer or delete them first.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.DeleteAccountViewModel>();
// A workspace handed to you since this page loaded: the server's refusal names it.
const refusal = computed(() => (usePage().props.errors as Record<string, string>).account);
</script>
<template>
    <Page md>
        <Alert v-if="refusal" variant="danger" live="alert" data-account-refusal>{{ refusal }}</Alert>
        <Alert v-if="owned.length" variant="warning" data-owned-workspaces>
            You own {{ owned.join(', ') }}. Transfer ownership or delete {{ owned.length === 1 ? 'that workspace' : 'those workspaces' }} before you delete your account.
        </Alert>
        <Form v-else :action="submit.url" method="delete">
            <FormCard title="Delete your account" description="You leave every workspace and are signed out. This cannot be undone." :submit-label="submit.label" busy-label="Deleting…" :cancel="false" :warn-on-leave="false">
                <Field v-if="confirms_password" id="delete-account-password" name="password" label="Your password"><PasswordInput id="delete-account-password" name="password" required autocomplete="current-password" /></Field>
            </FormCard>
        </Form>
    </Page>
</template>
