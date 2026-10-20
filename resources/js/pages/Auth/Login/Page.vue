<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Field, Input, Page, PasswordInput } from '@kit/ui';

// Registration is closed. `status` and `localLogin` are optional: an app that offers a one-click local sign-in passes the offer (null elsewhere).
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.LoginViewModel & { status?: string; localLogin?: { url: string; email: string } | null }>();
const form = useForm({ email: '', password: '', remember: false });
const local = useForm<{ local_login?: string }>({});
function submit() { form.post('/login', { onFinish: () => form.reset('password') }); }
function signInLocally() { local.post(props.localLogin?.url ?? '/login'); }
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <div class="space-y-2 text-center">
            <h1 class="text-xl font-medium">Sign in to your account</h1>
            <p class="text-sm text-muted-foreground">Enter your email and password below to sign in</p>
        </div>
        <p v-if="status" class="mt-4 text-center text-sm font-medium text-green-600">{{ status }}</p>
        <form v-if="localLogin" class="mt-6 flex flex-col gap-2" data-local-login @submit.prevent="signInLocally">
            <Button variant="secondary" :disabled="local.processing">Sign in as {{ localLogin.email }}</Button>
            <p v-if="local.errors.local_login" role="alert" class="text-sm text-destructive">{{ local.errors.local_login }}</p>
            <p class="text-center text-xs text-muted-foreground">One-click sign-in, offered only locally</p>
        </form>
        <form class="mt-6 grid gap-6" @submit.prevent="submit">
            <Field id="email" label="Email address" :error="form.errors.email">
                <Input id="email" v-model="form.email" type="email" name="email" required autocomplete="email" placeholder="email@example.com" :invalid="Boolean(form.errors.email)" aria-describedby="email-error" />
            </Field>
            <Field id="password" label="Password" :error="form.errors.password">
                <PasswordInput id="password" v-model="form.password" name="password" required autocomplete="current-password" placeholder="Password" :invalid="Boolean(form.errors.password)" aria-describedby="password-error" />
            </Field>
            <Checkbox id="remember" v-model="form.remember">Remember me</Checkbox>
            <Button :disabled="form.processing" data-test="login-button">{{ form.processing ? 'Signing in…' : 'Sign in' }}</Button>
        </form>
    </Page>
</template>
