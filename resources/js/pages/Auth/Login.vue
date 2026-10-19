<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import WorkspaceLayout from '../../layouts/WorkspaceLayout.vue';
const form = useForm({ email: '', password: '', remember: false });
function submit() { form.post('/login', { onFinish: () => form.reset('password') }); }
</script>
<template><WorkspaceLayout title="Sign in">
    <p>Use your invited account. Registration is closed.</p>
    <form @submit.prevent="submit">
        <label for="email">Email</label><input id="email" v-model="form.email" type="email" autocomplete="username" required :aria-invalid="Boolean(form.errors.email)" aria-describedby="email-error" /><p id="email-error" class="error" role="alert">{{ form.errors.email }}</p>
        <label for="password">Password</label><input id="password" v-model="form.password" type="password" autocomplete="current-password" required :aria-invalid="Boolean(form.errors.password)" aria-describedby="password-error" /><p id="password-error" class="error" role="alert">{{ form.errors.password }}</p>
        <label class="check"><input v-model="form.remember" type="checkbox" /> Remember me</label>
        <button type="submit" :disabled="form.processing">{{ form.processing ? 'Signing in…' : 'Sign in' }}</button>
    </form>
</WorkspaceLayout></template>
