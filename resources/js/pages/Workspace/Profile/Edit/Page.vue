<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Avatar, ConfirmDialog, Button, Field, FileInput, FormCard, Page, Section, SettingsFrame } from '@kit/ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// Your photo: choose one (it is cropped to a square), replace it, or remove it (in the danger zone, after a confirmation). Initials show wherever there is none.
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
        <SettingsFrame>
            <div class="grid gap-6">
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
        </SettingsFrame>
    </Page>
</template>
