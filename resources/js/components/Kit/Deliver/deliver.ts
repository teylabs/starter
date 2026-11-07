/*
 * The headless half of the kit deliver pieces: the draft a person builds, when "tomorrow morning" is, and the preview request. The server decides who may be
 * sent to, whether sending is allowed and what the email says; nothing here is trusted by it. Types mirror DeliverState and its views on the server.
 */
import { http } from '@inertiajs/vue3';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import { responseJson } from '@/components/Kit/http';

export type DeliverRecipientOption = {
    address: string;
    name: string | null;
    role: 'to' | 'cc';
};
export type DeliveryRecipient = {
    role: 'to' | 'cc' | 'bcc';
    address: string;
    name: string | null;
    status: 'pending' | 'sent' | 'delivered' | 'bounced' | 'complained';
};
export type DeliveryEntry = {
    id: number;
    state:
        'scheduled' | 'sending' | 'sent' | 'failed' | 'unknown' | 'cancelled';
    test: boolean;
    by: string;
    channel: string;
    createdAt: string;
    sendAt: string | null;
    sentAt: string | null;
    subject: string;
    attachment: 'attached' | 'linked' | 'none';
    recipients: DeliveryRecipient[];
    stillFinal: boolean | null;
    linkActive: boolean;
    firstOpenedAt: string | null;
    note: string | null;
    resentFromId: number | null;
    actions: ActionDescriptor[];
};
export type DeliverStateView = {
    definition: string;
    subject: string;
    sendable: boolean;
    reason: string | null;
    defaultSubject: string;
    defaultMessage: string;
    recipientOptions: DeliverRecipientOption[];
    internalCopies: string[];
    deliveries: DeliveryEntry[];
    revision: string;
    preview: ActionDescriptor | null;
    send: ActionDescriptor | null;
    testSend: ActionDescriptor | null;
    attachmentUrl: string | null;
};
export type DeliverPreviewView = {
    subject: string;
    html: string;
    attachment: 'attached' | 'linked' | 'none';
    attachmentName: string | null;
    attachmentBytes: number | null;
};
export type DeliverDraft = {
    to: string[];
    cc: string[];
    /** Typed To addresses, not among the contacts: any valid email. */
    to_other: string[];
    bcc: string[];
    subject: string;
    message: string;
    send_at?: string | null;
};

/** Each option's starting choice: its own role (To or Cc). "none" leaves that contact off this send. */
export type RecipientChoice = 'to' | 'cc' | 'none';

export function initialChoices(
    options: DeliverRecipientOption[],
): Record<string, RecipientChoice> {
    return Object.fromEntries(
        options.map((option) => [option.address, option.role]),
    );
}

/** Typed addresses, split on commas, semicolons or spaces, lowercased and de-duplicated. */
export function typedAddresses(value: string): string[] {
    return [
        ...new Set(
            value
                .split(/[\s,;]+/)
                .map((address) => address.trim().toLowerCase())
                .filter((address) => address !== ''),
        ),
    ];
}

/**
 * The draft from the person's choices: picked contacts as To and Cc, typed To addresses (not among the contacts) as to_other, and Bcc from the ticked
 * internal copies plus typed ones.
 */
export function buildDraft(
    choices: Record<string, RecipientChoice>,
    copies: string[],
    extraBcc: string,
    subject: string,
    message: string,
    sendAt: string | null,
    extraTo = '',
): DeliverDraft {
    const pick = (role: RecipientChoice) =>
        Object.entries(choices)
            .filter(([, chosen]) => chosen === role)
            .map(([address]) => address);
    const to = pick('to');
    const draft: DeliverDraft = {
        to,
        cc: pick('cc'),
        to_other: typedAddresses(extraTo).filter(
            (address) => !to.includes(address),
        ),
        bcc: [...new Set([...copies, ...typedAddresses(extraBcc)])],
        subject,
        message,
    };
    if (sendAt) draft.send_at = sendAt;
    return draft;
}

/** 8:00 tomorrow in the person's own time zone, as an ISO instant. */
export function tomorrowMorning(now: Date = new Date()): Date {
    const morning = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate() + 1,
        8,
        0,
        0,
        0,
    );
    return morning;
}

/** A `datetime-local` value (local wall time, no zone) as an ISO instant; null when empty or unreadable. */
export function localToIso(value: string): string | null {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

/** A fresh claim key: one per intended send, so a double click or a retry is the same send on the server. */
export function claimKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`;
}

/** Asks the server to render the email for this draft. Nothing is written or sent; a refusal throws with the server's body. */
export async function requestPreview(
    action: ActionDescriptor,
    draft: DeliverDraft,
): Promise<DeliverPreviewView> {
    const response = await http.getClient().request({
        method: 'post',
        url: action.url,
        data: draft,
        headers: { Accept: 'application/json' },
    });
    return responseJson(response) as DeliverPreviewView;
}
