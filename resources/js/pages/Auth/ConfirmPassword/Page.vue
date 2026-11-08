<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { Alert, Button, Field, Page, PasswordInput } from '@kit/ui';

// Asks for the password again before a sensitive step (Fortify's password.confirm), then returns to that step. Cancel goes back on the app only.
// A guest card: one full-width button.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ConfirmPasswordViewModel>();

// A wrong password is cleared and refocused. A 429 stays on the page: Inertia's error modal is refused (the visit's `options.onHttpException` returns false; `<Form>` before Inertia 3.8 has no such prop, so it is never an `@http-exception` listener), stale field errors go, and an in-card alert says
// when to try again (the kit's `ui` lang file, `form.*`). The server decides; this only presents it.
const form = ref<{ clearErrors: () => void; reset: (...fields: string[]) => void } | null>(null);
const throttled = ref<string | null>(null);
const page = usePage();
// The password fields are the page's (v-model): Inertia's reset-on-error resets the DOM only, so a failed or refused submit clears them here too.
const password = ref('');
function clearPasswords() {
    password.value = '';
}
function refocus(errors: Record<string, string>) {
    clearPasswords();
    const first = ['password'].find((name) => errors[name]);
    if (first) nextTick(() => document.getElementById(first)?.focus());
}
function tooMany(response: { status: number; headers?: Record<string, string> }) {
    if (response.status !== 429) return;
    const copy = (page.props as { kitUi?: { form?: { throttled?: string; throttled_retry?: string } } }).kitUi?.form;
    const seconds = Number.parseInt(response.headers?.['retry-after'] ?? '', 10);
    throttled.value = seconds > 0 && copy?.throttled_retry ? copy.throttled_retry.replace(':seconds', String(seconds)) : (copy?.throttled ?? 'Too many requests. Try again in a moment.');
    form.value?.clearErrors();
    form.value?.reset('password');
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
            <h1 class="text-xl font-medium">Confirm your password</h1>
            <p class="text-sm text-muted-foreground">For your security, confirm your password to continue.</p>
        </div>
        <Form ref="form" novalidate v-slot="{ errors, processing }" action="/user/confirm-password" method="post" :reset-on-error="['password']" class="grid gap-4" @start="throttled = null" @error="refocus" :options="formOptions">
            <Alert v-if="throttled" variant="danger" live="alert" data-auth-throttled>{{ throttled }}</Alert>
            <Field id="password" name="password" label="Password"><PasswordInput id="password" v-model="password" name="password" required autofocus autocomplete="current-password" :invalid="Boolean(errors.password)" /></Field>
            <Button type="submit" class="w-full" :disabled="processing">{{ processing ? 'Confirming…' : 'Confirm' }}</Button>
            <Link :href="cancel" class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">Cancel</Link>
        </Form>
    </Page>
</template>
