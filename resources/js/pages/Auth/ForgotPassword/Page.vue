<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { Alert, Button, Field, FormStatus, Input, Page } from '@kit/ui';

// Asks for a password reset link (Fortify's resetPasswords). The answer is the same whether or not the address has an account, so it reveals nothing.
// The confirmation is the flashed `status` in FormStatus, kept until the next submit. A guest card: one full-width button.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChallengeViewModel>();

// An invalid address shows its field error and takes focus. A 429 (the limiter) stays on the page: Inertia's error modal is refused (the visit's `options.onHttpException` returns false; `<Form>` before Inertia 3.8 has no such prop, so it is never an `@http-exception` listener), stale field errors go,
// and an in-card alert says when to try again (the kit's `ui` lang file, `form.*`). The server decides; this only presents it.
const form = ref<{ clearErrors: () => void; reset: (...fields: string[]) => void } | null>(null);
const throttled = ref<string | null>(null);
const page = usePage();
function refocus(errors: Record<string, string>) {
    const first = ['email'].find((name) => errors[name]);
    if (first) nextTick(() => document.getElementById(first)?.focus());
}
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
            <h1 class="text-xl font-medium">Reset your password</h1>
            <p class="text-sm text-muted-foreground">Enter your email address and we'll send you a link to choose a new password.</p>
        </div>
        <Form ref="form" novalidate v-slot="{ errors, processing }" action="/forgot-password" method="post" class="grid gap-4" @start="throttled = null" @error="refocus" :options="formOptions">
            <FormStatus data-reset-link-sent />
            <Alert v-if="throttled" variant="danger" live="alert" data-auth-throttled>{{ throttled }}</Alert>
            <Field id="email" name="email" label="Email address">
                <Input id="email" name="email" type="email" required autocomplete="email" placeholder="email@example.com" :invalid="Boolean(errors.email)" />
            </Field>
            <Button type="submit" class="w-full" :disabled="processing">{{ processing ? 'Sending…' : 'Email me a reset link' }}</Button>
            <Link href="/login" class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">Back to sign in</Link>
        </Form>
    </Page>
</template>
