<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Alert, Field, FormCard, Input, Page, PasswordInput } from '@kit/ui';

// Settings › Delete workspace, the owner only. The workspace's name is typed to confirm, with the current password unless the host turned that off; the server checks both.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.DeleteWorkspaceViewModel>();
// "settings and invitations", or "settings, notes and invitations" where the notes feature is installed: the server lists what goes.
const removes = props.removes.length > 1 ? `${props.removes.slice(0, -1).join(', ')} and ${props.removes.at(-1)}` : (props.removes[0] ?? '');
</script>
<template>
    <Page md>
        <Alert variant="danger" data-delete-warning>Deleting {{ workspace_name }} removes it for all {{ members }} {{ members === 1 ? 'member' : 'members' }}, with its {{ removes }}. This cannot be undone.</Alert>
        <Form :action="submit.url" method="delete">
            <FormCard title="Delete this workspace" :submit-label="submit.label" busy-label="Deleting…" :cancel="false" :warn-on-leave="false">
                <Field id="delete-confirmation" name="confirmation" :label="`Type ${workspace_name} to confirm`"><Input id="delete-confirmation" name="confirmation" required autocomplete="off" /></Field>
                <Field v-if="confirms_password" id="delete-password" name="password" label="Your password"><PasswordInput id="delete-password" name="password" required autocomplete="current-password" /></Field>
            </FormCard>
        </Form>
    </Page>
</template>
