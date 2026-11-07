<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Alert, BlockStack, Field, FormCard, Input, Page, PasswordInput } from '@kit/ui';

// Settings › Delete workspace, the owner only. The workspace's name is typed to confirm, with the current password unless the host turned that off; the server checks both.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.DeleteWorkspaceViewModel>();
</script>
<template>
    <Page md>
        <BlockStack class="gap-6">
            <Alert variant="danger" data-delete-warning>Deleting {{ workspace_name }} removes it for all {{ members }} {{ members === 1 ? 'member' : 'members' }}, with its settings, notes and invitations. This cannot be undone.</Alert>
            <Form :action="submit.url" method="delete">
                <FormCard title="Delete this workspace" :submit-label="submit.label" busy-label="Deleting…" :cancel="false" :warn-on-leave="false">
                    <Field id="delete-confirmation" name="confirmation" :label="`Type ${workspace_name} to confirm`"><Input id="delete-confirmation" name="confirmation" required autocomplete="off" /></Field>
                    <Field v-if="confirms_password" id="delete-password" name="password" label="Your password"><PasswordInput id="delete-password" name="password" required autocomplete="current-password" /></Field>
                </FormCard>
            </Form>
        </BlockStack>
    </Page>
</template>
