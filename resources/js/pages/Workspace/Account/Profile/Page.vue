<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Avatar, Button, ConfirmDialog, Field, FileInput, FormActions, FormCard, Input, Page, Section } from '@kit/ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// Your profile: your name and email address (Fortify's update; a changed address stays unconfirmed until its link is followed) and your photo. The photo
// can be removed in the danger zone, after a confirmation. Initials show wherever there is no photo.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.ProfileViewModel>();
const chosen = ref<File | null>(null);
const preview = ref<string | null>(null);
watch(chosen, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));
const shown = computed(() => preview.value ?? props.avatar?.url ?? null);
const shown2x = computed(() => (preview.value ? null : props.avatar?.url_2x ?? null));
function remove() { router.delete(props.store_url, { preserveScroll: true }); }
</script>
<template>
    <Page sm>
            <div class="grid gap-6">
                <p v-if="notice" class="rounded-md border border-border bg-muted px-4 py-3 text-sm" role="status" data-account-notice>{{ notice }}</p>
                <Form :action="profile_url" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
                    <FormCard title="Profile" description="Your name and email address." submit-label="Save">
                        <Field id="profile-name" name="name" label="Name" :span="3"><Input id="profile-name" name="name" :model-value="name" required autocomplete="name" aria-describedby="profile-name-error" /></Field>
                        <Field id="profile-email" name="email" label="Email address" :span="3" :description="email_verified ? undefined : 'This address is not confirmed yet. Follow the link we sent to confirm it.'"><Input id="profile-email" name="email" type="email" :model-value="email" required autocomplete="email" aria-describedby="profile-email-error profile-email-description" /></Field>
                    </FormCard>
                </Form>
                <Form v-if="!email_verified" :action="verify_url" method="post" :options="{ preserveScroll: true }">
                    <FormActions submit-label="Send the confirmation link again" busy-label="Sending…" :cancel="false" :sticky="false" :warn-on-leave="false" />
                </Form>
                <Form :action="store_url" method="post" :options="{ preserveScroll: true }" reset-on-success @success="chosen = null">
                    <FormCard title="Photo" :description="`A square photo, JPEG, PNG or WebP, up to ${max_kb} KB. Your initials show until you add one.`" :submit-label="avatar ? 'Replace photo' : 'Upload photo'" busy-label="Uploading…">
                        <div class="col-span-6 flex items-center gap-4">
                            <Avatar :initials="initials" :src="shown" :src2x="shown2x" large />
                            <p class="text-sm font-medium">{{ name }}</p>
                        </div>
                        <Field id="avatar" name="avatar" label="Choose a photo" :span="6">
                            <FileInput id="avatar" v-model="chosen" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-error" />
                        </Field>
                    </FormCard>
                </Form>
                <Section v-if="avatar" title="Remove photo" description="Your photo is deleted and your initials show instead.">
                    <ConfirmDialog title="Remove your photo?" description="Your photo is deleted. You can upload another any time." confirm-label="Remove photo" @confirm="remove">
                        <Button variant="destructive" type="button">Remove photo</Button>
                    </ConfirmDialog>
                </Section>
            </div>
    </Page>
</template>
