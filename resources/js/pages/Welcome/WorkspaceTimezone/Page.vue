<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { browserTimezone, Button, Field, Page, TimezoneSelect } from '@kit/ui';
import { ref, watch } from 'vue';

// The welcome step that confirms a timezone (the person's own, or the workspace's for its owner): it shows the current value, prefilled from the browser when nothing is
// saved yet, and a change is saved as it is made, with no Save button. Continue is the confirmation. A timezone is not optional, so there is no skip.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.WelcomeStepViewModel>();
const zone = ref<string>(props.value ?? browserTimezone() ?? 'UTC');
const saved = ref(props.value === zone.value);
watch(zone, (value, before) => {
    if (!props.save || value === before) return;
    saved.value = false;
    router.put(props.save.url, { timezone: value }, { preserveState: true, preserveScroll: true, onSuccess: () => (saved.value = true) });
});
</script>
<template>
    <Page xs :heading="false">
    <div class="grid gap-6" data-welcome-step="workspace-timezone">
        <div class="space-y-2">
            <h1 class="text-xl font-medium" data-welcome-heading>{{ heading }}</h1>
            <p class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <Form :action="submit.url" :method="submit.method as 'post'" v-slot="{ processing, errors }" class="grid gap-6">
            <Field id="timezone" name="timezone" label="Timezone" :error="errors.timezone">
                <TimezoneSelect id="timezone" name="timezone" v-model="zone" autofocus />
            </Field>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="sr-only" role="status" aria-live="polite" data-welcome-saved>{{ saved ? 'Saved' : '' }}</span>
                <Button :disabled="processing" data-welcome-submit>{{ submit.label }}</Button>
            </div>
        </Form>
    </div>
    </Page>
</template>
