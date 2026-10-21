<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Button, Field, Input, Page, PasswordInput } from '@kit/ui';

// A workspace invitation link: the workspace, who invited you, then the next step for this state, or a clear reason the link no longer works.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.InvitationViewModel>();
// The one thing to do next takes the focus on arrival: the name field of the signup form (the email is locked), or the sign-in link.
const signIn = ref<{ $el?: HTMLElement } | null>(null);
onMounted(() => signIn.value?.$el?.focus());

// Every state must make sense to someone who walked away and came back: it says what this link is (an invitation, from whom, to what, on which product), which
// account is signed in, and what, if anything, is left to do. The server's `message` is only the fallback for the rarer states.
const product = computed(() => (usePage().props.shell as { product?: { name?: string } } | undefined)?.product?.name ?? '');
const who = computed(() => props.invitation?.inviter ?? 'Someone');
const where = computed(() => (props.invitation ? `${props.invitation.workspace}${product.value ? ` on ${product.value}` : ''}` : ''));
const context = computed(() => (props.invitation ? `${who.value} invited you to join ${where.value}.` : ''));
const body = computed(() => {
    const ask = props.invitation?.inviter ? props.invitation.inviter : 'the person who invited you';
    switch (props.state) {
        case 'ready':
            return `Accept to join. Signed in as ${props.signed_in_as}.`;
        case 'sign_in_required':
            return `You already have an account for ${props.invitation?.email}. Sign in with it and you will come straight back here to join.`;
        case 'register_required':
            return `Create your account for ${props.invitation?.email} to join. It takes one step.`;
        case 'already_member':
            return `You accepted this invitation as ${props.signed_in_as}, so there is nothing more to do: this link has done its job. Use it, or the workspace, any time.`;
        case 'accepted':
            return 'It has already been accepted, so it cannot be used again. If that was you, sign in to open the workspace.';
        case 'expired':
            return `This invitation has expired and can no longer be used. Ask ${ask} to send a new one.`;
        case 'revoked':
            return `This invitation was withdrawn, so it can no longer be used. If you still need access, ask ${ask} to invite you again.`;
        case 'invalid':
            return 'This link does not match an active invitation. A newer link may have replaced it: use the latest invitation you were sent, or ask the person who invited you for a new one.';
        case 'wrong_account':
            return `This invitation is for ${props.invitation?.email}, and you are signed in as ${props.signed_in_as}. Switch to the right account to join; the link stays valid.`;
        default:
            return props.message;
    }
});
</script>
<template>
    <Page xs :heading="false">
        <div class="space-y-2">
            <h1 class="text-xl font-medium" data-invitation-title>{{ title }}</h1>
            <p v-if="context" class="text-sm font-medium" data-invitation-summary>{{ context }}</p>
            <p class="text-sm text-muted-foreground" data-invitation-message :data-state="state">{{ body }}</p>
        </div>
        <Form v-if="state === 'ready'" v-slot="{ processing }" :action="accept_url" method="post" class="mt-6 grid gap-4">
            <Button class="w-full" :disabled="processing" data-test="accept-invitation">{{ processing ? 'Joining…' : 'Accept invitation' }}</Button>
            <Link href="/switch-account" method="post" as="button" class="text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" data-test="invitation-not-you">Not {{ signed_in_as }}? Switch account</Link>
        </Form>
        <div v-else-if="state === 'sign_in_required'" class="mt-6 grid gap-3">
            <Link ref="signIn" :href="login_url" class="inline-flex min-h-11 items-center gap-2 justify-self-end rounded-md px-1 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="invitation-sign-in">Sign in to join<ArrowRight class="size-4" aria-hidden="true" /></Link>
        </div>
        <Form v-else-if="state === 'register_required'" v-slot="{ processing }" :action="register_url" method="post" :reset-on-success="['password', 'password_confirmation']" class="mt-6 grid gap-4">
            <Field id="invite-email" label="Email address"><Input id="invite-email" :model-value="invitation?.email" type="email" readonly /></Field>
            <Field id="name" name="name" label="Your name"><Input id="name" name="name" required autofocus autocomplete="name" aria-describedby="name-error" /></Field>
            <Field id="password" name="password" label="Password"><PasswordInput id="password" name="password" required autocomplete="new-password" aria-describedby="password-error" /></Field>
            <Field id="password_confirmation" name="password_confirmation" label="Confirm password"><PasswordInput id="password_confirmation" name="password_confirmation" required autocomplete="new-password" /></Field>
            <Button class="w-full" :disabled="processing" data-test="invitation-register">{{ processing ? 'Creating…' : 'Create account' }}</Button>
        </Form>
        <div v-else-if="state === 'wrong_account'" class="mt-6 grid gap-3" data-invitation-wrong-account>
            <Link href="/switch-account" method="post" as="button" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="invitation-switch-account">Switch account</Link>
            <Link href="/" class="text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" data-test="invitation-cancel">Cancel</Link>
        </div>
        <div v-else-if="state === 'already_member'" class="mt-6 grid gap-3">
            <Link v-if="workspace_url" :href="workspace_url" class="inline-flex min-h-11 items-center gap-2 justify-self-end rounded-md px-1 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="invitation-open-workspace">Go to {{ invitation?.workspace ?? 'the workspace' }}<ArrowRight class="size-4" aria-hidden="true" /></Link>
            <Link href="/switch-account" method="post" as="button" class="text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" data-test="invitation-not-you">Not {{ signed_in_as }}? Switch account</Link>
        </div>
        <div v-else-if="['expired', 'revoked', 'invalid', 'accepted'].includes(state)" class="mt-6 grid" data-invitation-ended>
            <Link :href="signed_in_as ? '/' : login_url" class="inline-flex min-h-11 items-center gap-2 justify-self-end rounded-md px-1 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="invitation-continue">{{ signed_in_as ? 'Go to your workspaces' : 'Sign in' }}<ArrowRight class="size-4" aria-hidden="true" /></Link>
        </div>
    </Page>
</template>
