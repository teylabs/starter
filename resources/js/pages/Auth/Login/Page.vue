<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FocusPage } from '@kit/ui';
defineProps<Tey.Kit.Features.SessionUi.ViewModels.LoginViewModel>();
const form = useForm({ email: '', password: '', remember: false });
function submit() { form.post('/login', { onFinish: () => form.reset('password') }); }
</script>
<template>
    <Head :title="title" />
    <main>
        <FocusPage size="xs">
        <h1>{{ title }}</h1>
        <p>Use your invited account. Registration is closed.</p>
        <form @submit.prevent="submit">
            <label for="email">Email</label><input id="email" v-model="form.email" type="email" autocomplete="username" required :aria-invalid="Boolean(form.errors.email)" aria-describedby="email-error" /><p id="email-error" role="alert">{{ form.errors.email }}</p>
            <label for="password">Password</label><input id="password" v-model="form.password" type="password" autocomplete="current-password" required :aria-invalid="Boolean(form.errors.password)" aria-describedby="password-error" /><p id="password-error" role="alert">{{ form.errors.password }}</p>
            <label><input v-model="form.remember" type="checkbox" /> Remember me</label>
            <button type="submit" :disabled="form.processing">{{ form.processing ? 'Signing in…' : 'Sign in' }}</button>
        </form>
        </FocusPage>
    </main>
</template>
