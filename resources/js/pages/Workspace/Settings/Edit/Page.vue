<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditSettingsViewModel>();
const form = useForm({ display_name: props.settings.display_name, timezone: props.settings.timezone, expected_version: props.settings.version, intent_key: props.intent_key });
function submit() { form.put(props.update_url, { preserveScroll: true, onSuccess: () => { form.expected_version = props.settings.version; form.intent_key = props.intent_key; form.defaults(); } }); }
function cancel() { form.reset(); form.clearErrors(); }
</script>
<template>
    <p v-if="!can_write">You can view these settings. An owner can update them.</p>
    <form @submit.prevent="submit">
        <label for="display-name">Display name</label><input id="display-name" v-model="form.display_name" :readonly="!can_write" required maxlength="120" :aria-invalid="Boolean(form.errors.display_name)" aria-describedby="display-name-error" /><p id="display-name-error" role="alert">{{ form.errors.display_name }}</p>
        <label for="timezone">Timezone</label><input id="timezone" v-model="form.timezone" :readonly="!can_write" required :aria-invalid="Boolean(form.errors.timezone)" aria-describedby="timezone-error" /><p id="timezone-error" role="alert">{{ form.errors.timezone }}</p>
        <div v-if="can_write"><button type="submit" :disabled="form.processing || !form.isDirty">{{ form.processing ? 'Saving…' : 'Save settings' }}</button><button type="button" :disabled="form.processing || !form.isDirty" @click="cancel">Cancel changes</button></div>
        <p aria-live="polite">{{ form.recentlySuccessful ? 'Settings saved.' : form.isDirty ? 'Unsaved changes' : '' }}</p>
    </form>
</template>
