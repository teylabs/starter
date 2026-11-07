<script setup lang="ts">
import { computed } from 'vue';
import type { DeliverPreviewView } from './deliver';

/*
 * The email exactly as it will be sent (kit DeliveryPreview), drawn in a sandboxed iframe: no scripts, no same-origin access, no forms, links do not
 * navigate. Above it, the subject and what happens to the document (attached, or linked because it is too large).
 */
const props = defineProps<{
    preview: DeliverPreviewView;
    attachmentUrl?: string | null;
}>();

const size = computed(() => {
    const bytes = props.preview.attachmentBytes;
    if (bytes === null) return null;
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
});
</script>
<template>
    <section class="grid gap-3" data-deliver-preview>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt class="text-muted-foreground">Subject</dt>
            <dd
                class="text-foreground font-medium"
                data-deliver-preview-subject
            >
                {{ preview.subject }}
            </dd>
            <template v-if="preview.attachment !== 'none'">
                <dt class="text-muted-foreground">Document</dt>
                <dd class="text-foreground" data-deliver-preview-attachment>
                    <a
                        v-if="attachmentUrl"
                        :href="attachmentUrl"
                        target="_blank"
                        rel="noopener"
                        class="underline underline-offset-4"
                        >{{ preview.attachmentName ?? 'Document' }}</a
                    ><template v-else>{{
                        preview.attachmentName ?? 'Document'
                    }}</template>
                    <span v-if="size" class="text-muted-foreground">
                        · {{ size }}</span
                    >
                    <span class="text-muted-foreground">
                        ·
                        {{
                            preview.attachment === 'attached'
                                ? 'attached'
                                : 'too large to attach: sent as a link'
                        }}</span
                    >
                </dd>
            </template>
        </dl>
        <iframe
            :srcdoc="preview.html"
            sandbox=""
            referrerpolicy="no-referrer"
            title="Email preview"
            class="border-border h-[32rem] w-full rounded-md border bg-white"
            data-deliver-preview-frame
        />
    </section>
</template>
