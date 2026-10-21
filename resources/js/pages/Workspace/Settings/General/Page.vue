<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Field, FormCard, Input, Page, TimezoneSelect } from '@kit/ui';

// General settings: Inertia's <Form> (uncontrolled; every control submits a named value) in a FormCard. `save` is the server's action: absent for a viewer whose role can only read, who sees the form read-only with no button to press. Transferring ownership and leaving are not here: they
// are offered where they apply (Make owner on a member's row in People, Leave workspace in the workspace menu), and only to someone who can use them.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditSettingsViewModel>();
defineExpose({ props });
</script>
<template>
    <Page md>
        <Form :action="save?.url ?? ''" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
            <fieldset :disabled="!save" class="contents">
                <FormCard title="General" description="How this workspace is named and which timezone it keeps." busy-label="Saving…" :submit="save ?? undefined">
                    <Input type="hidden" name="expected_version" :model-value="String(settings.version)" />
                    <Input type="hidden" name="intent_key" :model-value="intent_key" />
                    <Field id="display-name" name="display_name" label="Display name" :span="4">
                        <Input id="display-name" name="display_name" :model-value="settings.display_name" required maxlength="120" aria-describedby="display-name-error" />
                    </Field>
                    <Field id="timezone" name="timezone" label="Timezone" :span="4">
                        <TimezoneSelect id="timezone" name="timezone" :model-value="settings.timezone" />
                    </Field>
                    <template v-if="!save" #actions><p class="px-6 pb-6 text-sm text-muted-foreground" data-readonly>You can view these settings but not change them.</p></template>
                </FormCard>
            </fieldset>
        </Form>
    </Page>
</template>
