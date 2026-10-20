<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

// Laravel's Vue starter kit sign-in page (MIT): registration is closed and password reset waits on mail (#24).
// localLogin is Concierge's one-click local sign-in offer, null wherever it isn't available.
defineProps<{
    title: string;
    status?: string;
    localLogin?: { url: string; email: string } | null;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="space-y-2 text-center">
        <h1 class="font-serif text-4xl tracking-tight text-foreground">Sign in to your account</h1>
        <p class="text-sm text-muted-foreground">Enter your email and password below to sign in</p>
    </div>

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-if="localLogin"
        :action="localLogin.url"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-2"
        data-local-login
    >
        <Button
            type="submit"
            variant="outline"
            class="w-full"
            :disabled="processing"
        >
            <Spinner v-if="processing" />
            Sign in as {{ localLogin.email }}
        </Button>
        <InputError :message="errors.local_login" />
        <p class="text-center text-xs text-muted-foreground">
            One-click sign-in, offered only locally
        </p>
    </Form>

    <Form
        action="/login"
        method="post"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Sign in
            </Button>
        </div>
    </Form>
</template>
