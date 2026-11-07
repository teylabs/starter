import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { DeliveryEntry, DeliveryRecipient } from './deliver';

/**
 * One line of a delivery feed, for an app that renders the history itself (an aside or activity feed beside DeliverPanel with `history: false`). Headless:
 * it renders nothing and knows no feed library. One event per delivery, newest first. `kind` is its state, except that a delivery any address bounced or
 * complained about is `bounced` (`bounced` lists those addresses). `at` is when it was sent; for one not sent, when it is due (scheduled) or was created.
 * The kit stores no separate cancel or bounce time, so none is invented. `entry` is the delivery itself and `actions` its Cancel, Revoke and Resend;
 * hand a Resend to DeliverPanel's exposed `resend(entry, action)`.
 */
export type DeliveryEvent = {
    key: string;
    kind:
        | 'scheduled'
        | 'sending'
        | 'sent'
        | 'bounced'
        | 'failed'
        | 'unconfirmed'
        | 'cancelled';
    at: string;
    test: boolean;
    by: string;
    subject: string;
    recipients: DeliveryRecipient[];
    bounced: DeliveryRecipient[];
    note: string | null;
    actions: ActionDescriptor[];
    entry: DeliveryEntry;
};

export function deliveryEvents(
    deliveries: readonly DeliveryEntry[],
): DeliveryEvent[] {
    return deliveries
        .map((entry): DeliveryEvent => {
            const bounced = entry.recipients.filter(
                (recipient) =>
                    recipient.status === 'bounced' ||
                    recipient.status === 'complained',
            );

            return {
                key: `delivery-${entry.id}`,
                kind:
                    bounced.length > 0
                        ? 'bounced'
                        : entry.state === 'unknown'
                          ? 'unconfirmed'
                          : entry.state,
                at: entry.sentAt ?? entry.sendAt ?? entry.createdAt,
                test: entry.test,
                by: entry.by,
                subject: entry.subject,
                recipients: entry.recipients,
                bounced,
                note: entry.note,
                actions: entry.actions,
                entry,
            };
        })
        .sort((a, b) => Date.parse(b.at) - Date.parse(a.at));
}
