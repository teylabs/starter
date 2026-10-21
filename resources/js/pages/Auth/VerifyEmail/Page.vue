<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Alert, Button, Page } from '@kit/ui';

// Shown until the email address is confirmed: a link was sent, and it can be sent again. A guest card: one full-width button.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChallengeViewModel>();
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <div class="space-y-2">
            <h1 class="text-xl font-medium">Verify your email address</h1>
            <p class="text-sm text-muted-foreground">We sent a link to <template v-if="email">{{ email }}</template><template v-else>your email address</template>. Follow it to confirm the address.</p>
        </div>
        <Alert v-if="status === 'verification-link-sent'" variant="success" live="status" class="mt-4" data-verification-sent>A new link is on its way.</Alert>
        <Form v-slot="{ processing }" action="/email/verification-notification" method="post" class="mt-6 grid gap-3">
            <Button class="w-full" :disabled="processing">{{ processing ? 'Sending…' : 'Send the link again' }}</Button>
            <Link href="/logout" method="post" as="button" class="inline-flex h-9 w-full items-center justify-center rounded-md bg-secondary px-4 text-sm font-medium text-secondary-foreground hover:bg-secondary/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">Sign out</Link>
        </Form>
    </Page>
</template>
