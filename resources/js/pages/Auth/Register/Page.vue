<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { Alert, Button, Field, FormStatus, Input, Page, PasswordInput, Stack } from '@kit/ui';

// Creates an account. By default it is Fortify's open registration (/register, while the app turns the `registration` feature on): the account is the app's
// CreatesNewUsers action's. An app's own sign-up (an invited account, say) renders this page from its own route with its own view model values: where it posts
// (`action`), the heading, lead and button, an address shown as text in place of the email input (`email`, with `emailHint`), values posted that are not inputs
// (`submit`, added in the transform) and its own 429 copy (`throttleMessage`). A guest card: one full-width button, everything aligned to the card's left edge.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.RegisterViewModel>();

// A failed attempt returns focus to the first invalid field; an error on a key with no input here (`invitation`, or `email` shown as text) is one alert at the top.
// A 429, an expired session (419) and a network failure stay on the page: Inertia's error modal is refused (the visit's `options.onHttpException` and
// `onNetworkError` return false; `<Form>` before Inertia 3.8 has no such props, so they are never listeners), stale field errors go, and an in-card alert says
// what happened (a 429 in the view model's copy, else the kit's `ui` lang file, `form.*`). Any other HTTP error keeps Inertia's modal. The server decides; this only presents it.
const form = ref<{ clearErrors: () => void; reset: (...fields: string[]) => void } | null>(null);
const failure = ref<string | null>(null);
const page = usePage();
const fields = computed(() => (props.email === null ? ['name', 'email', 'password', 'password_confirmation'] : ['name', 'password', 'password_confirmation']));
function otherError(errors: Record<string, string | undefined>): string | null {
    return Object.entries(errors).find(([key, message]) => message && !fields.value.includes(key))?.[1] ?? null;
}
// The password fields are the page's (v-model): Inertia's reset-on-error resets the DOM only, so they are cleared here after every attempt.
const password = ref('');
const confirmation = ref('');
function clearPasswords() {
    password.value = '';
    confirmation.value = '';
}
function refocus(errors: Record<string, string>) {
    const first = fields.value.find((name) => errors[name]);
    if (first) nextTick(() => document.getElementById(first)?.focus());
}
function refused(message: string) {
    failure.value = message;
    form.value?.clearErrors();
    form.value?.reset('password', 'password_confirmation');
    clearPasswords();
    return false;
}
function httpFailure(response: { status: number; headers?: Record<string, string> }) {
    if (response.status === 419) return refused('Your session expired. Reload the page and try again.');
    if (response.status !== 429) return;
    const copy = (page.props as { kitUi?: { form?: { throttled?: string; throttled_retry?: string } } }).kitUi?.form;
    const seconds = Number.parseInt(response.headers?.['retry-after'] ?? '', 10);
    return refused(props.throttleMessage ?? (seconds > 0 && copy?.throttled_retry ? copy.throttled_retry.replace(':seconds', String(seconds)) : (copy?.throttled ?? 'Too many requests. Try again in a moment.')));
}
// <Form> spreads `options` into the visit, but its type (FormComponentOptions) leaves out onHttpException, and an inline literal fails vue-tsc's excess-property
// check: so the handlers are typed as the visit options, then widened. Both types are read off @inertiajs/vue3's own <Form> and router (no stub imports @inertiajs/core).
type FormOptions = NonNullable<InstanceType<typeof Form>['$props']['options']>;
type VisitOptions = NonNullable<Parameters<typeof router.visit>[1]>;
const failureHandlers: Pick<VisitOptions, 'onHttpException' | 'onNetworkError'> = {
    onHttpException: httpFailure,
    onNetworkError: () => refused('We could not reach the server. Check your connection and try again.'),
};
const formOptions = failureHandlers as FormOptions;
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <!-- A compact auth card: the heading and its flashed status sit closer (gap-4) than the page's blocks (docs/page-rhythm.md). -->
        <Stack>
            <div class="space-y-2">
                <h1 class="text-xl font-medium">{{ heading }}</h1>
                <p v-if="lead" class="text-sm text-muted-foreground" data-register-lead>{{ lead }}</p>
            </div>
            <FormStatus class="self-stretch" data-register-status />
        </Stack>
        <Form ref="form" novalidate v-slot="{ errors, processing }" :action="action" method="post" :transform="(data) => ({ ...data, ...submit })" :reset-on-success="['password', 'password_confirmation']" :reset-on-error="['password', 'password_confirmation']" class="grid gap-4" @start="failure = null" @error="refocus" @finish="clearPasswords" :options="formOptions">
            <Alert v-if="otherError(errors)" variant="danger" live="alert" data-register-error>{{ otherError(errors) }}</Alert>
            <Alert v-else-if="failure" variant="danger" live="alert" data-auth-throttled>{{ failure }}</Alert>
            <div v-if="email !== null" class="grid gap-2">
                <p id="register-email-label" class="text-sm font-medium">Email address</p>
                <p aria-labelledby="register-email-label" :aria-describedby="emailHint ? 'register-email-hint' : undefined" class="rounded-md bg-muted px-3 py-2 text-sm break-all" data-register-email>{{ email }}</p>
                <p v-if="emailHint" id="register-email-hint" class="text-sm text-muted-foreground">{{ emailHint }}</p>
            </div>
            <Field id="name" name="name" label="Name">
                <Input id="name" name="name" required autofocus autocomplete="name" :invalid="Boolean(errors.name)" />
            </Field>
            <Field v-if="email === null" id="email" name="email" label="Email address">
                <Input id="email" name="email" type="email" required autocomplete="email" placeholder="email@example.com" :invalid="Boolean(errors.email)" />
            </Field>
            <Field id="password" name="password" label="Password"><PasswordInput id="password" v-model="password" name="password" required autocomplete="new-password" :invalid="Boolean(errors.password)" rules /></Field>
            <Field id="password_confirmation" name="password_confirmation" label="Confirm password"><PasswordInput id="password_confirmation" v-model="confirmation" name="password_confirmation" required autocomplete="new-password" :invalid="Boolean(errors.password_confirmation)" /></Field>
            <Button type="submit" class="w-full" :disabled="processing" data-test="register-button">{{ processing ? 'Creating account…' : submitLabel }}</Button>
            <p v-if="login" class="text-sm text-muted-foreground">Already have an account? <Link :href="login" class="underline underline-offset-4 hover:text-foreground" data-sign-in>Sign in</Link></p>
        </Form>
    </Page>
</template>
