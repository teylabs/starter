<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { Alert, Button, Field, FormStatus, Input, Page, PasswordInput } from '@kit/ui';

// Chooses the new password from the emailed link (Fortify's resetPasswords): the token and address come back with it, and the rules show before typing.
// Completing it signs nothing in; it returns to sign in with a note. A guest card: one full-width button.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ResetPasswordViewModel>();

// `status` is the view model's (FormStatus reads it). A failed reset clears both passwords. Its `email` error (a bad or expired token: the address is a hidden
// field) shows as an in-card alert with a way to ask for a new link. A 429 stays on the page: Inertia's error modal is refused (the visit's `options.onHttpException` returns false; `<Form>` before Inertia 3.8 has no such prop, so it is never an `@http-exception` listener), stale field errors go,
// and an in-card alert says when to try again (the kit's `ui` lang file, `form.*`). The server decides; this only presents it.
const form = ref<{ clearErrors: () => void; reset: (...fields: string[]) => void } | null>(null);
const throttled = ref<string | null>(null);
const page = usePage();
// The password fields are the page's (v-model): Inertia's reset-on-error resets the DOM only, so a failed or refused submit clears them here too.
const password = ref('');
const confirmation = ref('');
function clearPasswords() {
    password.value = '';
    confirmation.value = '';
}
function refocus(errors: Record<string, string>) {
    clearPasswords();
    const first = ['password', 'password_confirmation'].find((name) => errors[name]);
    if (first) nextTick(() => document.getElementById(first)?.focus());
}
function tooMany(response: { status: number; headers?: Record<string, string> }) {
    if (response.status !== 429) return;
    const copy = (page.props as { kitUi?: { form?: { throttled?: string; throttled_retry?: string } } }).kitUi?.form;
    const seconds = Number.parseInt(response.headers?.['retry-after'] ?? '', 10);
    throttled.value = seconds > 0 && copy?.throttled_retry ? copy.throttled_retry.replace(':seconds', String(seconds)) : (copy?.throttled ?? 'Too many requests. Try again in a moment.');
    form.value?.clearErrors();
    form.value?.reset('password', 'password_confirmation');
    clearPasswords();
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
            <h1 class="text-xl font-medium">Choose a new password</h1>
            <p class="text-sm text-muted-foreground">For {{ email }}</p>
        </div>
        <Form ref="form" novalidate v-slot="{ errors, processing }" action="/reset-password" method="post" :reset-on-success="['password', 'password_confirmation']" :reset-on-error="['password', 'password_confirmation']" class="mt-6 grid gap-4" @start="throttled = null" @error="refocus" :options="formOptions">
            <FormStatus data-reset-password-status />
            <Alert v-if="throttled" variant="danger" live="alert" data-auth-throttled>{{ throttled }}</Alert>
            <Alert v-if="errors.email" variant="danger" live="alert" data-reset-email-error>
                {{ errors.email }}
                <template #actions><Link href="/forgot-password" class="underline underline-offset-4">Request a new link</Link></template>
            </Alert>
            <Input type="hidden" name="token" :model-value="token" />
            <Input type="hidden" name="email" :model-value="email" />
            <Field id="password" name="password" label="New password"><PasswordInput id="password" v-model="password" name="password" required autocomplete="new-password" :invalid="Boolean(errors.password)" rules /></Field>
            <Field id="password_confirmation" name="password_confirmation" label="Confirm new password"><PasswordInput id="password_confirmation" v-model="confirmation" name="password_confirmation" required autocomplete="new-password" :invalid="Boolean(errors.password_confirmation)" /></Field>
            <Button type="submit" class="w-full" :disabled="processing">{{ processing ? 'Saving…' : 'Save new password' }}</Button>
        </Form>
    </Page>
</template>
