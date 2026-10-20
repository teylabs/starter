<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Button, Field, Input, Page, PasswordInput } from '@kit/ui';

// A workspace invitation link: the workspace, who invited you and the role, then the next step for this state, or a clear reason the link no longer works.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.InvitationViewModel>();
const accept = useForm({});
const account = useForm({ name: '', password: '', password_confirmation: '' });
function join() { accept.post(props.accept_url); }
function register() { account.post(props.register_url, { onFinish: () => account.reset('password', 'password_confirmation') }); }
</script>
<template>
    <Page xs :heading="false">
        <div class="space-y-2 text-center">
            <h1 class="text-xl font-medium" data-invitation-title>{{ title }}</h1>
            <p v-if="invitation && ['ready', 'sign_in_required', 'register_required'].includes(state)" class="text-sm text-muted-foreground" data-invitation-summary>
                <template v-if="invitation.inviter">{{ invitation.inviter }} invited you to </template><template v-else>You are invited to </template>{{ invitation.workspace }} as {{ invitation.role }}.
            </p>
            <p class="text-sm text-muted-foreground" data-invitation-message :data-state="state">{{ message }}</p>
        </div>
        <form v-if="state === 'ready'" class="mt-6 grid gap-4" @submit.prevent="join">
            <p v-if="signed_in_as" class="text-center text-sm text-muted-foreground">Signed in as {{ signed_in_as }}</p>
            <Button :disabled="accept.processing" data-test="accept-invitation">{{ accept.processing ? 'Joining…' : 'Accept invitation' }}</Button>
        </form>
        <div v-else-if="state === 'sign_in_required'" class="mt-6 grid gap-3 text-center">
            <Link :href="login_url" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="invitation-sign-in">Sign in to join</Link>
            <p v-if="invitation" class="text-xs text-muted-foreground">Use {{ invitation.email }}.</p>
        </div>
        <form v-else-if="state === 'register_required'" class="mt-6 grid gap-4" @submit.prevent="register">
            <Field id="invite-email" label="Email address"><Input id="invite-email" :model-value="invitation?.email" type="email" readonly /></Field>
            <Field id="name" label="Your name" :error="account.errors.name"><Input id="name" v-model="account.name" required autocomplete="name" :invalid="Boolean(account.errors.name)" aria-describedby="name-error" /></Field>
            <Field id="password" label="Password" :error="account.errors.password"><PasswordInput id="password" v-model="account.password" required autocomplete="new-password" :invalid="Boolean(account.errors.password)" aria-describedby="password-error" /></Field>
            <Field id="password_confirmation" label="Confirm password"><PasswordInput id="password_confirmation" v-model="account.password_confirmation" required autocomplete="new-password" /></Field>
            <Button :disabled="account.processing" data-test="invitation-register">{{ account.processing ? 'Creating…' : 'Create account' }}</Button>
        </form>
        <div v-else-if="state === 'wrong_account'" class="mt-6 grid gap-3 text-center">
            <p v-if="signed_in_as" class="text-sm text-muted-foreground">Signed in as {{ signed_in_as }}.</p>
            <Link href="/logout" method="post" as="button" class="inline-flex h-9 items-center justify-center rounded-md bg-secondary px-4 text-sm font-medium text-secondary-foreground hover:bg-secondary/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">Sign out</Link>
        </div>
        <div v-else-if="state === 'already_member' && workspace_url" class="mt-6 text-center">
            <Link :href="workspace_url" class="text-sm font-medium underline-offset-4 hover:underline">Open the workspace</Link>
        </div>
    </Page>
</template>
