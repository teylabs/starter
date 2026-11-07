<script setup lang="ts">
import { http, router, usePage } from '@inertiajs/vue3';
import { KeyRound, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import {
    passkeyName,
    passkeysSupported,
    registerPasskey,
} from './passkeyOffer';

/*
 * "Add a passkey for next time": shown once after an invited sign-up (the shared `passkeyOffer`, session-ui's PasskeyOffer), where the app has Fortify passkeys
 * and the browser can create one. Add runs Fortify's passkey registration; Not now closes the offer for good. Either way it doesn't come back. A failed or
 * cancelled attempt says so and leaves the offer open.
 */
type Offer = { options_url: string; store_url: string; dismiss_url: string };
const page = usePage();
const offer = computed(
    () => (page.props as { passkeyOffer?: Offer | null }).passkeyOffer ?? null,
);
const supported = ref(false);
onMounted(() => {
    supported.value = passkeysSupported();
});
const busy = ref(false);
const failed = ref(false);
const shown = computed(() => offer.value !== null && supported.value);

function close(): void {
    if (offer.value)
        router.delete(offer.value.dismiss_url, {
            preserveScroll: true,
            preserveState: true,
        });
}
async function add(): Promise<void> {
    if (!offer.value || busy.value) return;
    busy.value = true;
    failed.value = false;
    try {
        await registerPasskey(
            http.getClient() as never,
            offer.value,
            passkeyName(
                (navigator as { userAgentData?: { platform?: string } })
                    .userAgentData?.platform,
            ),
        );
        window.dispatchEvent(
            new CustomEvent('kit:toast:show', {
                detail: {
                    id: `passkey-${Date.now().toString(36)}`,
                    type: 'success',
                    message: 'Passkey added. Use it to sign in next time.',
                    style: 'plain',
                    duration: 4000,
                },
            }),
        );
        close();
    } catch {
        failed.value = true;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <section
        v-if="shown"
        aria-labelledby="passkey-offer-title"
        class="border-border bg-card relative mb-4 flex flex-wrap items-start gap-3 rounded-lg border p-4 pr-12 shadow-xs"
        data-passkey-offer
    >
        <KeyRound
            class="text-primary mt-0.5 size-5 shrink-0"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
            <h2 id="passkey-offer-title" class="text-sm font-semibold">
                Add a passkey for next time
            </h2>
            <p class="text-muted-foreground mt-1 text-sm">
                Sign in with your fingerprint, face or device PIN instead of
                your password.
            </p>
            <p
                v-if="failed"
                role="alert"
                class="text-destructive-text mt-2 text-sm"
                data-passkey-failed
            >
                No passkey was added. You can try again, or skip it for now.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <Button
                    type="button"
                    :disabled="busy"
                    data-passkey-add
                    @click="add"
                    >{{ busy ? 'Adding…' : 'Add a passkey' }}</Button
                >
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="busy"
                    data-passkey-dismiss
                    @click="close"
                    >Not now</Button
                >
            </div>
        </div>
        <button
            type="button"
            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-3 right-3 rounded-md p-1 focus-visible:ring-2 focus-visible:outline-none"
            aria-label="Dismiss the passkey offer"
            data-passkey-close
            @click="close"
        >
            <X class="size-4" aria-hidden="true" />
        </button>
    </section>
</template>
