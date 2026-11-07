<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, useId, watch } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Card from '@/components/Kit/Card/Card.vue';
import CardBody from '@/components/Kit/Card/CardBody.vue';
import Alert from '@/components/Kit/Feedback/Alert.vue';
import Button from '@/components/Kit/Form/Button.vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputCheckbox from '@/components/Kit/Form/InputCheckbox.vue';
import Select from '@/components/Kit/Form/Select.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import { errorJson } from '@/components/Kit/http';
import {
    buildDraft,
    claimKey,
    initialChoices,
    localToIso,
    requestPreview,
    tomorrowMorning,
    type DeliverPreviewView,
    type DeliverStateView,
    type DeliveryEntry,
    type RecipientChoice,
} from './deliver';
import DeliveryHistory from './DeliveryHistory.vue';
import DeliveryPreview from './DeliveryPreview.vue';

/*
 * Sending a subject's document to its customer (kit DeliverPanel), from the server's DeliverState: who it goes to (only the subject's live contacts, as To or
 * Cc; the workspace's internal copies as Bcc), the subject line and message (the workspace defaults, editable for this send), when (now, tomorrow at 8:00, or
 * a chosen time), a preview of the exact email, a test to yourself, and the history. Every write posts the revision the person saw and one claim key per
 * intended send: the server refuses a stale page and never sends the same claim twice. While the server sends Send disabled, its reason shows instead.
 */
// `history: false` leaves the history out, for an app that shows it beside the form instead: it reads the same `state.deliveries` (deliveryEvents() flattens
// (Kit/Deliver/deliveryEvents) them into a feed) and calls the exposed `resend(entry, action)` to fill this form, as the built-in history's Resend does.
const props = withDefaults(
    defineProps<{ state: DeliverStateView; history?: boolean }>(),
    { history: true },
);
const uid = useId();

const choices = ref<Record<string, RecipientChoice>>(
    initialChoices(props.state.recipientOptions),
);
const copies = ref<string[]>([...props.state.internalCopies]);
const extraBcc = ref('');
const extraTo = ref('');
const subject = ref(props.state.defaultSubject);
const message = ref(props.state.defaultMessage);
const when = ref<'now' | 'morning' | 'pick'>('now');
const pickedAt = ref('');
const key = ref(claimKey());
const resending = ref<{
    entry: DeliveryEntry;
    action: ActionDescriptor;
} | null>(null);
const revokePrevious = ref(true);
const preview = ref<DeliverPreviewView | null>(null);
const previewError = ref<string | null>(null);
const busy = ref<'preview' | 'send' | 'test' | null>(null);

// A new state from the server (after a send, or a reload) keeps the person's edits but drops contacts that are no longer offered.
watch(
    () => props.state.recipientOptions,
    (options) => {
        const next = initialChoices(options);
        for (const address of Object.keys(next))
            if (address in choices.value)
                next[address] = choices.value[address];
        choices.value = next;
    },
);

const sendAt = computed(() =>
    when.value === 'morning'
        ? tomorrowMorning().toISOString()
        : when.value === 'pick'
          ? localToIso(pickedAt.value)
          : null,
);
const draft = computed(() =>
    buildDraft(
        choices.value,
        copies.value,
        extraBcc.value,
        subject.value,
        message.value,
        sendAt.value,
        extraTo.value,
    ),
);
const hasTo = computed(
    () => draft.value.to.length + draft.value.to_other.length > 0,
);
const pickedInvalid = computed(
    () =>
        when.value === 'pick' &&
        (sendAt.value === null ||
            new Date(sendAt.value).getTime() <= Date.now()),
);
const sendAction = computed(() =>
    resending.value ? resending.value.action : props.state.send,
);
const sendLabel = computed(() => {
    if (resending.value)
        return when.value === 'now' ? 'Send again' : 'Schedule resend';
    return when.value === 'now'
        ? (props.state.send?.label ?? 'Send')
        : 'Schedule';
});
const blockedReason = computed(() =>
    sendAction.value?.disabled ? sendAction.value.reason : null,
);
const canSend = computed(
    () =>
        !!sendAction.value &&
        !sendAction.value.disabled &&
        hasTo.value &&
        subject.value.trim() !== '' &&
        !pickedInvalid.value &&
        busy.value === null,
);

const roleOptions = [
    { value: 'to', label: 'To' },
    { value: 'cc', label: 'Cc' },
    { value: 'none', label: 'Not on this send' },
];
const whenOptions = [
    { value: 'now', label: 'Send now' },
    { value: 'morning', label: 'Tomorrow morning (8:00)' },
    { value: 'pick', label: 'Pick a date and time' },
];

async function showPreview(): Promise<void> {
    if (!props.state.preview || busy.value) return;
    busy.value = 'preview';
    previewError.value = null;
    try {
        preview.value = await requestPreview(props.state.preview, draft.value);
    } catch (error) {
        const body = errorJson(error).body as { message?: string } | null;
        previewError.value = body?.message ?? 'The preview could not be made.';
        preview.value = null;
    } finally {
        busy.value = null;
    }
}

function post(
    action: ActionDescriptor,
    data: Record<string, unknown>,
    kind: 'send' | 'test',
): void {
    busy.value = kind;
    router.visit(action.url, {
        method: 'post',
        data: { ...data, claim_key: key.value },
        preserveScroll: true,
        onSuccess: () => {
            key.value = claimKey();
            if (kind === 'send') {
                resending.value = null;
                when.value = 'now';
                pickedAt.value = '';
                preview.value = null;
            }
        },
        onFinish: () => (busy.value = null),
    });
}

// A real send asks first, naming who it goes to and when; the test and the preview do not.
const confirming = ref(false);
const nameOf = (address: string) =>
    props.state.recipientOptions.find((option) => option.address === address)
        ?.name ?? address;
const people = computed(() =>
    [...draft.value.to, ...draft.value.to_other, ...draft.value.cc].map(nameOf),
);
const confirmTitle = computed(() => {
    const names = people.value;
    const list =
        names.length <= 1
            ? (names[0] ?? '')
            : `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
    return `${resending.value ? 'Send again' : 'Send'} to ${list}?`;
});
const confirmDescription = computed(() => {
    const count =
        draft.value.to.length +
        draft.value.to_other.length +
        draft.value.cc.length +
        draft.value.bcc.length;
    const parts = [`${count} ${count === 1 ? 'recipient' : 'recipients'}`];
    if (draft.value.bcc.length)
        parts.push(`including ${draft.value.bcc.length} Bcc`);
    const at = sendAt.value
        ? `Scheduled for ${new Date(sendAt.value).toLocaleString(undefined, { dateStyle: 'full', timeStyle: 'short' })}.`
        : 'It sends now.';
    return `${parts.join(', ')}. ${at}`;
});

function send(): void {
    if (!canSend.value || !sendAction.value) return;
    confirming.value = true;
}

function confirmSend(): void {
    confirming.value = false;
    if (!canSend.value || !sendAction.value) return;
    post(
        sendAction.value,
        resending.value
            ? { ...draft.value, revoke_previous: revokePrevious.value }
            : draft.value,
        'send',
    );
}

function sendTest(): void {
    if (!props.state.testSend || props.state.testSend.disabled || busy.value)
        return;
    post(
        props.state.testSend,
        { subject: subject.value, message: message.value },
        'test',
    );
}

function startResend(entry: DeliveryEntry, action: ActionDescriptor): void {
    resending.value = { entry, action };
    key.value = claimKey();
    const next: Record<string, RecipientChoice> = Object.fromEntries(
        Object.keys(choices.value).map((address) => [
            address,
            'none' as RecipientChoice,
        ]),
    );
    for (const recipient of entry.recipients) {
        if (
            (recipient.role === 'to' || recipient.role === 'cc') &&
            recipient.address in next
        )
            next[recipient.address] = recipient.role;
    }
    choices.value = next;
    extraTo.value = entry.recipients
        .filter(
            (recipient) =>
                recipient.role === 'to' && !(recipient.address in next),
        )
        .map((recipient) => recipient.address)
        .join(', ');
    subject.value = entry.subject;
    revokePrevious.value = entry.linkActive;
    preview.value = null;
}

function stopResend(): void {
    resending.value = null;
    key.value = claimKey();
    choices.value = initialChoices(props.state.recipientOptions);
    extraTo.value = '';
    subject.value = props.state.defaultSubject;
}

const optionLabel = (option: { address: string; name: string | null }) =>
    option.name ? `${option.name} <${option.address}>` : option.address;
defineExpose({ resend: startResend });
</script>
<template>
    <div class="text-foreground grid gap-6" data-deliver-panel>
        <Card data-deliver-card>
            <CardBody>
                <form
                    class="grid gap-4"
                    data-deliver-form
                    @submit.prevent="send"
                >
                    <Alert
                        v-if="resending"
                        variant="info"
                        data-deliver-resending
                    >
                        Sending again: “{{ resending.entry.subject }}”.
                        <Button
                            variant="link"
                            size="sm"
                            type="button"
                            @click="stopResend"
                            >Start a new send instead</Button
                        >
                    </Alert>

                    <fieldset class="grid gap-2" data-deliver-recipients-form>
                        <legend
                            class="text-foreground mb-1 text-sm font-medium"
                        >
                            Recipients
                        </legend>
                        <p
                            v-if="!state.recipientOptions.length"
                            class="text-muted-foreground text-sm"
                            data-deliver-no-contacts
                        >
                            No contacts with an email address: type the
                            customer's address under Other recipients.
                        </p>
                        <div
                            v-for="option in state.recipientOptions"
                            :key="option.address"
                            class="grid grid-cols-[1fr_auto] items-center gap-3"
                            :data-deliver-option="option.address"
                            :data-deliver-choice="choices[option.address]"
                        >
                            <label
                                :for="`${uid}-role-${option.address}`"
                                class="text-foreground truncate text-sm"
                                >{{ optionLabel(option) }}</label
                            >
                            <Select
                                :id="`${uid}-role-${option.address}`"
                                v-model="choices[option.address]"
                                :options="roleOptions"
                                class="w-44"
                            />
                        </div>
                        <p
                            v-if="state.recipientOptions.length && !hasTo"
                            class="text-muted-foreground text-sm"
                            data-deliver-needs-to
                        >
                            Choose at least one To.
                        </p>
                    </fieldset>

                    <Field
                        :id="`${uid}-to`"
                        label="Other recipients (To)"
                        description="Addresses not among the contacts. Separate them with commas."
                    >
                        <Input
                            :id="`${uid}-to`"
                            v-model="extraTo"
                            type="text"
                            autocomplete="off"
                            data-deliver-extra-to
                        />
                    </Field>
                    <fieldset
                        v-if="state.internalCopies.length"
                        class="grid gap-1"
                        data-deliver-copies
                    >
                        <legend
                            class="text-foreground mb-1 text-sm font-medium"
                        >
                            Internal copies (Bcc)
                        </legend>
                        <InputCheckbox
                            v-for="address in state.internalCopies"
                            :id="`${uid}-copy-${address}`"
                            :key="address"
                            :model-value="copies.includes(address)"
                            @update:model-value="
                                (on: boolean | undefined) =>
                                    (copies = on
                                        ? [...copies, address]
                                        : copies.filter((a) => a !== address))
                            "
                            >{{ address }}</InputCheckbox
                        >
                    </fieldset>
                    <Field
                        :id="`${uid}-bcc`"
                        label="Other Bcc"
                        description="Separate addresses with commas."
                    >
                        <Input
                            :id="`${uid}-bcc`"
                            v-model="extraBcc"
                            type="text"
                            autocomplete="off"
                            data-deliver-extra-bcc
                        />
                    </Field>

                    <Field :id="`${uid}-subject`" label="Subject">
                        <Input
                            :id="`${uid}-subject`"
                            v-model="subject"
                            type="text"
                            required
                            data-deliver-subject
                        />
                    </Field>
                    <Field :id="`${uid}-message`" label="Message">
                        <Textarea
                            :id="`${uid}-message`"
                            v-model="message"
                            rows="5"
                            data-deliver-message
                        />
                    </Field>

                    <Field
                        :id="`${uid}-when`"
                        label="When"
                        :data-deliver-when-value="when"
                    >
                        <Select
                            :id="`${uid}-when`"
                            v-model="when"
                            :options="whenOptions"
                            data-deliver-when
                        />
                    </Field>
                    <Field
                        v-if="when === 'pick'"
                        :id="`${uid}-at`"
                        label="Send at"
                        :error="
                            pickedAt && pickedInvalid
                                ? 'Choose a time in the future.'
                                : undefined
                        "
                    >
                        <Input
                            :id="`${uid}-at`"
                            v-model="pickedAt"
                            type="datetime-local"
                            data-deliver-at
                        />
                    </Field>
                    <p
                        v-if="when === 'morning'"
                        class="text-muted-foreground text-sm"
                        data-deliver-morning
                    >
                        Sends
                        {{
                            tomorrowMorning().toLocaleString(undefined, {
                                dateStyle: 'full',
                                timeStyle: 'short',
                            })
                        }}. It checks again at that time and is cancelled if the
                        document changed.
                    </p>

                    <InputCheckbox
                        v-if="resending && resending.entry.linkActive"
                        :id="`${uid}-revoke`"
                        v-model="revokePrevious"
                        >Turn off the link in the earlier email</InputCheckbox
                    >

                    <p
                        v-if="blockedReason"
                        class="text-muted-foreground text-sm"
                        data-deliver-reason
                    >
                        {{ blockedReason }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="sendAction"
                            type="submit"
                            :disabled="!canSend"
                            data-deliver-send
                            >{{ sendLabel }}</Button
                        >
                        <Button
                            v-if="state.preview"
                            type="button"
                            variant="secondary"
                            :disabled="busy !== null"
                            data-deliver-preview-button
                            @click="showPreview"
                            >Preview</Button
                        >
                        <Button
                            v-if="state.testSend"
                            type="button"
                            variant="ghost"
                            :disabled="state.testSend.disabled || busy !== null"
                            data-deliver-test
                            @click="sendTest"
                            >{{ state.testSend.label }}</Button
                        >
                    </div>
                </form>
            </CardBody>
        </Card>

        <p
            v-if="previewError"
            class="text-destructive-text text-sm"
            data-deliver-preview-error
        >
            {{ previewError }}
        </p>
        <Dialog
            v-model:open="confirming"
            :title="confirmTitle"
            :description="confirmDescription"
            :confirm-label="sendLabel"
            :icon="false"
            @confirm="confirmSend"
        >
            <template #body>
                <ul
                    class="text-foreground grid gap-0.5 text-sm"
                    data-deliver-confirm-recipients
                >
                    <li v-for="address in draft.to" :key="`to-${address}`">
                        <span class="text-muted-foreground">To</span>
                        {{ nameOf(address) }} &lt;{{ address }}&gt;
                    </li>
                    <li
                        v-for="address in draft.to_other"
                        :key="`too-${address}`"
                    >
                        <span class="text-muted-foreground">To</span>
                        {{ address }}
                    </li>
                    <li v-for="address in draft.cc" :key="`cc-${address}`">
                        <span class="text-muted-foreground">Cc</span>
                        {{ nameOf(address) }} &lt;{{ address }}&gt;
                    </li>
                    <li v-for="address in draft.bcc" :key="`bcc-${address}`">
                        <span class="text-muted-foreground">Bcc</span>
                        {{ address }}
                    </li>
                </ul>
            </template>
        </Dialog>
        <DeliveryPreview
            v-if="preview"
            :preview="preview"
            :attachment-url="state.attachmentUrl"
        />

        <section v-if="history" class="grid gap-2" data-deliver-history>
            <h3 class="text-foreground text-sm font-medium">History</h3>
            <DeliveryHistory
                :deliveries="state.deliveries"
                @resend="startResend"
            />
        </section>
    </div>
</template>
