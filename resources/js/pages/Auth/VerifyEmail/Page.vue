<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Alert, Button, FormStatus, Page } from '@kit/ui';

// Shown until the email address is confirmed: a link was sent, and it can be sent again. A guest card: one full-width button.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChallengeViewModel>();

// A 429 (Fortify's verification limiter) stays on the page: Inertia's error modal is refused (the visit's `options.onHttpException` returns false; `<Form>` before Inertia 3.8 has no such prop, so it is never an `@http-exception` listener) and an in-card alert says when to try again (the kit's `ui`
// lang file, `form.*`). The server decides; this only presents it.
const form = ref<{ clearErrors: () => void; reset: (...fields: string[]) => void } | null>(null);
const throttled = ref<string | null>(null);
const page = usePage();
function tooMany(response: { status: number; headers?: Record<string, string> }) {
    if (response.status !== 429) return;
    const copy = (page.props as { kitUi?: { form?: { throttled?: string; throttled_retry?: string } } }).kitUi?.form;
    const seconds = Number.parseInt(response.headers?.['retry-after'] ?? '', 10);
    throttled.value = seconds > 0 && copy?.throttled_retry ? copy.throttled_retry.replace(':seconds', String(seconds)) : (copy?.throttled ?? 'Too many requests. Try again in a moment.');
    form.value?.clearErrors();
    return false;
}
// <Form> spreads `options` into the visit, but its type (FormComponentOptions) leaves out onHttpException, and an inline literal fails vue-tsc's excess-property
// check: so the handler is typed as the visit option, then widened. Both types are read off @inertiajs/vue3's own <Form> and router (no stub imports @inertiajs/core).
type FormOptions = NonNullable<InstanceType<typeof Form>['$props']['options']>;
type VisitOptions = NonNullable<Parameters<typeof router.visit>[1]>;
const failureHandlers: Pick<VisitOptions, 'onHttpException'> = { onHttpException: tooMany };
const formOptions = failureHandlers as FormOptions;
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <div class="space-y-2">
            <h1 class="text-xl font-medium">Verify your email address</h1>
            <p class="text-sm text-muted-foreground">We sent a link to <template v-if="email">{{ email }}</template><template v-else>your email address</template>. Follow it to confirm the address.</p>
        </div>
        <Form ref="form" v-slot="{ processing }" action="/email/verification-notification" method="post" class="mt-6 grid gap-3" @start="throttled = null" :options="formOptions">
            <Alert v-if="throttled" variant="danger" live="alert" data-auth-throttled>{{ throttled }}</Alert>
            <FormStatus :message="status === 'verification-link-sent' ? 'A new link is on its way.' : null" data-verification-sent />
            <Button type="submit" class="w-full" :disabled="processing">{{ processing ? 'Sending…' : 'Send the link again' }}</Button>
            <Link href="/logout" method="post" as="button" class="inline-flex h-9 w-full items-center justify-center rounded-md bg-secondary px-4 text-sm font-medium text-secondary-foreground hover:bg-secondary/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">Sign out</Link>
        </Form>
    </Page>
</template>
