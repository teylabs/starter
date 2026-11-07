<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Avatar, Button, Field, FileInput, Page } from '@kit/ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// The optional welcome step that adds a photo: the same upload as the profile page, with a preview. Skipping is as good as uploading; initials show until there is one.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.WelcomeStepViewModel>();
const chosen = ref<File | null>(null);
const preview = ref<string | null>(null);
watch(chosen, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));
const shown = computed(() => preview.value);
</script>
<template>
    <Page xs :heading="false">
    <div class="grid gap-6" data-welcome-step="photo">
        <div class="space-y-2">
            <h1 class="text-xl font-medium" data-welcome-heading>{{ heading }}</h1>
            <p class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <Form :action="submit.url" :method="submit.method as 'post'" v-slot="{ processing, errors }" class="grid gap-6">
            <div class="flex items-center gap-4">
                <Avatar :initials="initials" :src="shown" large />
                <Field id="avatar" name="avatar" label="Choose a photo" :description="`JPEG, PNG or WebP, up to ${max_kb} KB.`" :error="errors.avatar" class="min-w-0 flex-1">
                    <FileInput id="avatar" v-model="chosen" name="avatar" autofocus accept="image/jpeg,image/png,image/webp" />
                </Field>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link v-if="skip" :href="skip.url" :method="skip.method as 'post'" as="button" class="rounded-md px-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-welcome-skip>{{ skip.label }}</Link>
                <Button type="submit" :disabled="processing || !chosen" data-welcome-submit>{{ submit.label }}</Button>
            </div>
        </Form>
    </div>
    </Page>
</template>
