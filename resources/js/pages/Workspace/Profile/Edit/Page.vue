<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Avatar, Button, Field, FileInput, FormActions, Page, Section, SettingsFrame } from '@kit/ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// Your photo: choose one (it is cropped to a square), replace it, or remove it. Initials show wherever there is none.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.ProfileViewModel>();
const form = useForm<{ avatar: File | null }>({ avatar: null });
const chooser = ref<InstanceType<typeof FileInput> | null>(null);
const preview = ref<string | null>(null);
watch(() => form.avatar, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));
const shown = computed(() => preview.value ?? props.avatar?.url ?? null);
const shown2x = computed(() => (preview.value ? null : props.avatar?.url_2x ?? null));
function save() { form.post(props.store_url, { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); chooser.value?.clear(); } }); }
const removal = useForm({});
function remove() { removal.delete(props.store_url, { preserveScroll: true }); }
</script>
<template>
    <Page sm>
        <SettingsFrame>
            <Section title="Photo" :description="`A square photo, JPEG, PNG or WebP, up to ${max_kb} KB. Your initials show until you add one.`">
                <div class="flex items-center gap-4">
                    <Avatar :initials="initials" :src="shown" :src2x="shown2x" large />
                    <p class="text-sm font-medium">{{ name }}</p>
                </div>
                <form class="grid gap-4" @submit.prevent="save">
                    <Field id="avatar" label="Choose a photo" :error="form.errors.avatar">
                        <FileInput id="avatar" ref="chooser" v-model="form.avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-error" />
                    </Field>
                    <FormActions>
                        <Button :disabled="!form.avatar || form.processing">{{ form.processing ? 'Uploading…' : avatar ? 'Replace photo' : 'Upload photo' }}</Button>
                        <Button v-if="avatar" variant="destructive" type="button" :disabled="removal.processing" @click="remove">Remove photo</Button>
                    </FormActions>
                </form>
            </Section>
        </SettingsFrame>
    </Page>
</template>
