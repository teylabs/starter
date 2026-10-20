<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Field, FormCard, Page, PasswordInput } from '@kit/ui';

// Change your password (Fortify's update). Your other sessions are signed out afterwards.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.PasswordViewModel>();
</script>
<template>
    <Page sm>
            <div class="grid gap-6">
                <p v-if="notice" class="rounded-md border border-border bg-muted px-4 py-3 text-sm" role="status" data-account-notice>{{ notice }}</p>
                <Form :action="update_url" method="put" :options="{ preserveScroll: true }" :reset-on-success="['current_password', 'password', 'password_confirmation']">
                    <FormCard title="Change password" description="Choose a new password. Your other sessions are signed out." submit-label="Update password" busy-label="Updating…">
                        <Field id="current-password" name="current_password" label="Current password"><PasswordInput id="current-password" name="current_password" required autocomplete="current-password" aria-describedby="current-password-error" /></Field>
                        <Field id="new-password" name="password" label="New password"><PasswordInput id="new-password" name="password" required autocomplete="new-password" aria-describedby="new-password-error" /></Field>
                        <Field id="confirm-password" name="password_confirmation" label="Confirm new password"><PasswordInput id="confirm-password" name="password_confirmation" required autocomplete="new-password" /></Field>
                    </FormCard>
                </Form>
            </div>
    </Page>
</template>
