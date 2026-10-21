<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Button, ConfirmDialog, Field, FormCard, Input, Page, PasswordInput, Section, Select, TimezoneSelect } from '@kit/ui';

// General settings: Inertia's <Form> (uncontrolled; every control submits a named value) in a FormCard. The save is always there: a user who may not use it
// sees the form read-only with the reason, never a form with nothing to press. The danger zone below holds Transfer ownership (owner only, password asked
// again) and Leave workspace (an owner has to hand the workspace over first, so the button is not there); both ask first, and the server refuses both anyway.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditSettingsViewModel>();
</script>
<template>
    <Page sm>
            <Form :action="save.url" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
                <fieldset :disabled="save.disabled" class="contents">
                    <FormCard title="General" description="How this workspace is named and which timezone it keeps." submit-label="Save" busy-label="Saving…">
                        <Input type="hidden" name="expected_version" :model-value="String(settings.version)" />
                        <Input type="hidden" name="intent_key" :model-value="intent_key" />
                        <Field id="display-name" name="display_name" label="Display name" :span="4">
                            <Input id="display-name" name="display_name" :model-value="settings.display_name" required maxlength="120" aria-describedby="display-name-error" />
                        </Field>
                        <Field id="timezone" name="timezone" label="Timezone" :span="4">
                            <TimezoneSelect id="timezone" name="timezone" :model-value="settings.timezone" />
                        </Field>
                        <template v-if="save.reason" #status><span class="min-h-5 flex-1 text-right text-sm text-muted-foreground" data-form-status data-state="readonly">{{ save.reason }}</span></template>
                    </FormCard>
                </fieldset>
            </Form>
            <Form v-if="transfer" v-slot="{ submit }" :action="transfer.url" method="put" :options="{ preserveScroll: true }" reset-on-success>
                <FormCard title="Transfer ownership" description="Pass ownership to another member. They become the owner and you become an admin. Confirm with your password." :cancel="false" :sticky="false">
                    <p v-if="transfer.reason" class="text-sm text-muted-foreground">{{ transfer.reason }}</p>
                    <template v-else>
                        <Field id="transfer-member" name="membership" label="New owner" :span="6"><Select id="transfer-member" name="membership" :options="heirs" placeholder="Choose a member" /></Field>
                        <Field id="transfer-password" name="password" label="Your password" :span="6"><PasswordInput id="transfer-password" name="password" required autocomplete="current-password" aria-describedby="transfer-password-error" /></Field>
                    </template>
                    <template v-if="transfer.confirm && !transfer.disabled" #submit>
                        <ConfirmDialog :title="transfer.confirm.title" :description="transfer.confirm.description" :confirm-label="transfer.confirm.label" @confirm="submit()">
                            <Button variant="destructive" type="button" data-test="transfer-ownership">{{ transfer.label }}</Button>
                        </ConfirmDialog>
                    </template>
                </FormCard>
            </Form>
            <Section v-if="leave" title="Leave workspace" class="mt-6">
                <p v-if="leave.disabled" class="text-sm text-muted-foreground" data-test="leave-owner">{{ leave.reason }}</p>
                <template v-else-if="leave.confirm">
                    <p class="mb-3 text-sm text-muted-foreground">You lose access to this workspace straight away. An admin can invite you again.</p>
                    <p v-if="($page.props.errors as Record<string, string>).leave" class="mb-3 text-sm text-destructive" role="alert">{{ ($page.props.errors as Record<string, string>).leave }}</p>
                    <ConfirmDialog :title="leave.confirm.title" :description="leave.confirm.description" :confirm-label="leave.confirm.label" @confirm="router.delete(leave.url)">
                        <Button variant="destructive" type="button" data-test="leave-workspace">{{ leave.label }}</Button>
                    </ConfirmDialog>
                </template>
            </Section>
    </Page>
</template>
