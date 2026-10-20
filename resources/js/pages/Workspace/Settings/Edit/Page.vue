<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Field, FormCard, Input, Page, SettingsFrame, TimezoneSelect } from '@kit/ui';

// General settings: Inertia's <Form> (uncontrolled; every control submits a named value) in a FormCard. The save is always there: a user who may not use it
// sees the form read-only with the reason, never a form with nothing to press.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditSettingsViewModel>();
</script>
<template>
    <Page sm>
        <SettingsFrame>
            <Form :action="props.update_url" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
                <fieldset :disabled="!can_write" class="contents">
                    <FormCard title="General" description="How this workspace is named and which timezone it keeps." submit-label="Save" busy-label="Saving…">
                        <Input type="hidden" name="expected_version" :model-value="String(settings.version)" />
                        <Input type="hidden" name="intent_key" :model-value="intent_key" />
                        <Field id="display-name" name="display_name" label="Display name" :span="4">
                            <Input id="display-name" name="display_name" :model-value="settings.display_name" required maxlength="120" aria-describedby="display-name-error" />
                        </Field>
                        <Field id="timezone" name="timezone" label="Timezone" :span="4">
                            <TimezoneSelect id="timezone" name="timezone" :model-value="settings.timezone" />
                        </Field>
                        <template v-if="!can_write" #status><span class="min-h-5 flex-1 text-right text-sm text-muted-foreground" data-form-status data-state="readonly">You don't have permission to change these settings.</span></template>
                    </FormCard>
                </fieldset>
            </Form>
        </SettingsFrame>
    </Page>
</template>
