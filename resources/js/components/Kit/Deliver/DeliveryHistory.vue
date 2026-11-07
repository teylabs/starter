<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import Button from '@/components/Kit/Form/Button.vue';
import type { DeliveryEntry } from './deliver';

/*
 * What was sent for a subject, newest first (kit DeliveryHistory): state, who sent it and when, each address with what the provider said, and the note a
 * person needs (why it was cancelled, that a send was never confirmed). Cancel and Revoke link post straight to their actions; Resend is handed to the
 * caller (`resend`), which fills the form, because a resend is a new send someone reviews.
 */
defineProps<{ deliveries: DeliveryEntry[] }>();
const emit = defineEmits<{
    resend: [entry: DeliveryEntry, action: ActionDescriptor];
}>();
const busy = ref<string | null>(null);
// Neutral labels at full contrast: the muted badge is too faint at this size.
const plain =
    'border-border text-foreground rounded-md border px-1.5 text-xs leading-5';

const tones: Record<
    DeliveryEntry['state'],
    'neutral' | 'info' | 'success' | 'warning' | 'danger'
> = {
    scheduled: 'info',
    sending: 'info',
    sent: 'success',
    failed: 'danger',
    unknown: 'warning',
    cancelled: 'neutral',
};
const labels: Record<DeliveryEntry['state'], string> = {
    scheduled: 'Scheduled',
    sending: 'Sending',
    sent: 'Sent',
    failed: 'Failed',
    unknown: 'Not confirmed',
    cancelled: 'Cancelled',
};
const statusTone = (status: string) =>
    status === 'bounced' || status === 'complained'
        ? 'text-destructive-text'
        : status === 'delivered'
          ? 'text-foreground'
          : 'text-muted-foreground';
const when = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '';

function act(entry: DeliveryEntry, action: ActionDescriptor): void {
    if (action.disabled || busy.value) return;
    if (action.key?.startsWith('resend-')) {
        emit('resend', entry, action);
        return;
    }
    busy.value = action.key ?? action.label;
    router.visit(action.url, {
        method: 'post',
        data: {},
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
}
</script>
<template>
    <ol
        v-if="deliveries.length"
        class="divide-border text-foreground grid divide-y"
        data-deliver-history
    >
        <li
            v-for="entry in deliveries"
            :key="entry.id"
            class="grid gap-2 py-3"
            :data-deliver-entry="entry.id"
            :data-deliver-state="entry.state"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Badge
                    v-if="tones[entry.state] !== 'neutral'"
                    :tone="tones[entry.state]"
                    size="sm"
                    >{{ labels[entry.state] }}</Badge
                >
                <span v-else :class="plain">{{ labels[entry.state] }}</span>
                <span v-if="entry.test" :class="plain">Test</span>
                <span class="text-foreground text-sm font-medium">{{
                    entry.subject
                }}</span>
            </div>
            <p class="text-muted-foreground text-xs">
                {{ entry.by }}
                <template v-if="entry.state === 'scheduled' && entry.sendAt">
                    · sends {{ when(entry.sendAt) }}</template
                >
                <template v-else-if="entry.sentAt">
                    · sent {{ when(entry.sentAt) }}</template
                >
                <template v-else> · {{ when(entry.createdAt) }}</template>
                <template v-if="entry.channel === 'mcp'">
                    · through an assistant</template
                >
                <template v-if="entry.resentFromId"> · resend</template>
                <template v-if="entry.firstOpenedAt">
                    · link first opened
                    {{ when(entry.firstOpenedAt) }}</template
                >
                <template v-if="entry.stillFinal === false">
                    · no longer final</template
                >
            </p>
            <ul class="grid gap-0.5 text-xs" data-deliver-recipients>
                <li
                    v-for="recipient in entry.recipients"
                    :key="recipient.address"
                    :data-deliver-recipient-status="recipient.status"
                >
                    <span class="text-muted-foreground uppercase">{{
                        recipient.role
                    }}</span>
                    {{
                        recipient.name
                            ? `${recipient.name} <${recipient.address}>`
                            : recipient.address
                    }}
                    <span :class="statusTone(recipient.status)"
                        >· {{ recipient.status }}</span
                    >
                </li>
            </ul>
            <p
                v-if="entry.note"
                class="text-warning-text text-sm"
                data-deliver-note
            >
                {{ entry.note }}
            </p>
            <div v-if="entry.actions.length" class="flex flex-wrap gap-2">
                <Button
                    v-for="action in entry.actions"
                    :key="action.key ?? action.label"
                    variant="secondary"
                    size="sm"
                    :disabled="action.disabled || busy !== null"
                    :title="action.reason ?? undefined"
                    :data-deliver-action="action.key"
                    @click="act(entry, action)"
                    >{{ action.label }}</Button
                >
            </div>
        </li>
    </ol>
    <p v-else class="text-muted-foreground text-sm" data-deliver-history-empty>
        Nothing has been sent yet.
    </p>
</template>
