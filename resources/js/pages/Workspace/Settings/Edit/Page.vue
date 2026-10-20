<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button, Field, FormActions, Input, Page, Section, SettingsFrame, TimezoneSelect } from '@kit/ui';
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditSettingsViewModel>();
const form = useForm({ display_name: props.settings.display_name, timezone: props.settings.timezone, expected_version: props.settings.version, intent_key: props.intent_key });
function submit() { form.put(props.update_url, { preserveScroll: true, onSuccess: () => { form.expected_version = props.settings.version; form.intent_key = props.intent_key; form.defaults(); } }); }
function cancel() { form.reset(); form.clearErrors(); }
</script>
<template>
    <Page lg>
    <SettingsFrame>
    <form @submit.prevent="submit">
        <Section title="General" description="How this workspace is named and which timezone it keeps.">
            <Field id="display-name" label="Display name" :error="form.errors.display_name">
                <Input id="display-name" v-model="form.display_name" :readonly="!can_write" required maxlength="120" :invalid="Boolean(form.errors.display_name)" aria-describedby="display-name-error" />
            </Field>
            <Field id="timezone" label="Timezone" :error="form.errors.timezone">
                <TimezoneSelect id="timezone" v-model="form.timezone" :disabled="!can_write" :invalid="Boolean(form.errors.timezone)" />
            </Field>
            <!-- The save is always there: a user who may not use it sees it disabled, with the reason, never a form with nothing to press. -->
            <FormActions>
                <Button :disabled="!can_write || form.processing || !form.isDirty">{{ form.processing ? 'Saving…' : 'Save settings' }}</Button>
                <Button variant="secondary" type="button" :disabled="!can_write || form.processing || !form.isDirty" @click="cancel">Cancel changes</Button>
                <span v-if="!can_write" class="text-sm text-muted-foreground">You don't have permission to change these settings.</span>
                <span v-else class="text-sm text-muted-foreground" aria-live="polite">{{ form.recentlySuccessful ? 'Settings saved.' : form.isDirty ? 'Unsaved changes' : '' }}</span>
            </FormActions>
        </Section>
    </form>
    </SettingsFrame>
    </Page>
</template>
