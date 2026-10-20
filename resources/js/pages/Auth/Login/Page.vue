<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Checkbox, Field, FormActions, Input, Page, PasswordInput } from '@kit/ui';

// Registration is closed. `status` and `localLogin` are optional: an app that offers a one-click local sign-in passes the offer (null elsewhere).
defineProps<Tey.Kit.Features.SessionUi.ViewModels.LoginViewModel & { status?: string; localLogin?: { url: string; email: string } | null }>();
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <div class="space-y-2 text-center">
            <h1 class="text-xl font-medium">Sign in to your account</h1>
            <p class="text-sm text-muted-foreground">Enter your email and password below to sign in</p>
        </div>
        <p v-if="status" class="mt-4 text-center text-sm font-medium text-green-600">{{ status }}</p>
        <Form v-if="localLogin" v-slot="{ errors }" :action="localLogin.url" method="post" class="mt-6" data-local-login>
            <FormActions :submit-label="`Sign in as ${localLogin.email}`" busy-label="Signing in…" :cancel="false" :sticky="false" :warn-on-leave="false" />
            <p v-if="errors.local_login" role="alert" class="text-center text-sm text-destructive">{{ errors.local_login }}</p>
            <p class="text-center text-xs text-muted-foreground">One-click sign-in, offered only locally</p>
        </Form>
        <Form action="/login" method="post" :reset-on-success="['password']" class="mt-6 grid gap-6">
            <Field id="email" name="email" label="Email address">
                <Input id="email" name="email" type="email" required autocomplete="email" placeholder="email@example.com" aria-describedby="email-error" />
            </Field>
            <Field id="password" name="password" label="Password">
                <PasswordInput id="password" name="password" required autocomplete="current-password" placeholder="Password" aria-describedby="password-error" />
            </Field>
            <Checkbox id="remember" name="remember" value="1">Remember me</Checkbox>
            <FormActions submit-label="Sign in" busy-label="Signing in…" :cancel="false" :sticky="false" :warn-on-leave="false" data-test="login-button" />
        </Form>
    </Page>
</template>
