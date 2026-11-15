<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Field, FormCard, Input, Page, RadioCardGroup, TimezoneSelect } from '@kit/ui';
import { ref, watch } from 'vue';

// General settings: Inertia's <Form> (uncontrolled; every control submits a named value) in a FormCard. `save` is the server's action: absent for a viewer whose role can only read, who sees the form read-only with no button to press. Transferring ownership and leaving are not here: they
// are offered where they apply (Make owner on a member's row in People, Leave workspace in the workspace menu), and only to someone who can use them.
// Date & time groups the workspace's regional settings: its time zone, then its time format, shown only when the app keeps one (`settings.clock` is null
// otherwise; Registry::workspaceClockUsing) and saved through the app's setter.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditSettingsViewModel>();
defineExpose({ props });
const clock = ref<string | null>(props.settings.clock);
watch(() => props.settings.clock, (value) => (clock.value = value));
const clocks = [
    { value: '12h', label: '12-hour', description: '3:30 PM' },
    { value: '24h', label: '24-hour', description: '15:30' },
    { value: 'auto', label: 'Automatic', description: 'Each person’s language decides.' },
];
</script>
<template>
    <Page md>
        <Form :action="save?.url ?? ''" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
            <fieldset :disabled="!save" class="contents">
                <FormCard title="General" description="How this workspace is named, and the time zone and time format it keeps." busy-label="Saving…" :submit="save ?? undefined">
                    <Input type="hidden" name="expected_version" :model-value="String(settings.version)" />
                    <Input type="hidden" name="intent_key" :model-value="intent_key" />
                    <Field id="display-name" name="display_name" label="Display name" :span="4">
                        <Input id="display-name" name="display_name" :model-value="settings.display_name" required maxlength="120" />
                    </Field>
                    <section aria-labelledby="date-time" class="grid grid-cols-6 gap-x-4 gap-y-6 border-t pt-6 *:col-span-6" data-date-time>
                        <h3 id="date-time" class="text-base font-semibold">Date &amp; time</h3>
                        <Field id="timezone" name="timezone" label="Time zone" :span="4">
                            <TimezoneSelect id="timezone" name="timezone" :model-value="settings.timezone" />
                        </Field>
                        <Field v-if="settings.clock != null" id="clock" name="clock" label="Time format" :span="6">
                            <RadioCardGroup id="clock" v-model="clock" name="clock" :options="clocks" :columns="3" />
                        </Field>
                    </section>
                    <template v-if="!save" #actions><p class="px-6 pb-6 text-sm text-muted-foreground" data-readonly>You can view these settings but not change them.</p></template>
                </FormCard>
            </fieldset>
        </Form>
    </Page>
</template>
