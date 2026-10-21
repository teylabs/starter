<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Button, Field, Page, TimezoneSelect } from '@kit/ui';
import { ref } from 'vue';

// The welcome step that confirms the timezone, prefilled from the browser (Intl) unless one is already saved. The shell around it is the FocusShell.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.WelcomeStepViewModel>();
const zone = ref<string>(props.value ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC');
</script>
<template>
    <Page xs :heading="false">
    <div class="grid gap-6" data-welcome-step="timezone">
        <div class="space-y-2">
            <h1 class="text-xl font-medium" data-welcome-heading>{{ heading }}</h1>
            <p class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <Form :action="submit.url" :method="submit.method as 'post'" v-slot="{ processing, errors }" class="grid gap-6">
            <Field id="timezone" name="timezone" label="Timezone" :error="errors.timezone">
                <TimezoneSelect id="timezone" name="timezone" v-model="zone" autofocus />
            </Field>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="skip.url" :method="skip.method as 'post'" as="button" class="rounded-md px-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-welcome-skip>{{ skip.label }}</Link>
                <Button :disabled="processing" data-welcome-submit>{{ submit.label }}</Button>
            </div>
        </Form>
    </div>
    </Page>
</template>
