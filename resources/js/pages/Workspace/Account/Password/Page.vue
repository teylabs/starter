<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Alert, BlockStack, Field, FormCard, Page, PasswordInput } from '@kit/ui';

// Change your password (Fortify's update). Your other sessions are signed out afterwards.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.PasswordViewModel>();
</script>
<template>
    <Page md>
            <BlockStack class="gap-6">
                <Alert v-if="notice" variant="info" live="status" data-account-notice>{{ notice }}</Alert>
                <Form :action="update_url" method="put" :options="{ preserveScroll: true }" :reset-on-success="['current_password', 'password', 'password_confirmation']">
                    <FormCard title="Change password" description="Choose a new password. Your other sessions are signed out." submit-label="Update password" busy-label="Updating…">
                        <Field id="current-password" name="current_password" label="Current password"><PasswordInput id="current-password" name="current_password" required autocomplete="current-password" /></Field>
                        <Field id="new-password" name="password" label="New password"><PasswordInput id="new-password" name="password" required autocomplete="new-password" rules /></Field>
                        <Field id="confirm-password" name="password_confirmation" label="Confirm new password"><PasswordInput id="confirm-password" name="password_confirmation" required autocomplete="new-password" /></Field>
                    </FormCard>
                </Form>
            </BlockStack>
    </Page>
</template>
