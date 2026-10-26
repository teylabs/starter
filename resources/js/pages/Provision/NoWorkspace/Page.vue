<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Page } from '@kit/ui';

// A signed-in person with no workspace: a new account, or someone who left or was removed. Open invitations to their email come first, then what to do next. The shell
// around it is the FocusShell (the product, Switch account and Sign out), and there is no create action unless the server sends one.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.NoWorkspaceViewModel>();
</script>
<template>
    <Page xs :heading="false">
        <div class="grid gap-6" data-no-workspace>
            <h1 class="text-xl font-medium">{{ title }}</h1>
            <section v-if="invitations.length" aria-labelledby="open-invitations" class="grid gap-3" data-no-workspace-invitations>
                <h2 id="open-invitations" class="text-sm font-medium">You have {{ invitations.length === 1 ? 'an invitation' : 'invitations' }}</h2>
                <ul class="grid gap-2">
                    <li v-for="invitation in invitations" :key="invitation.workspace + invitation.expires" class="rounded-lg border p-3 text-sm">
                        <p class="font-medium">{{ invitation.workspace }}</p>
                        <p class="text-muted-foreground">{{ invitation.inviter ?? 'Someone' }} invited you. Open the link in the email they sent to {{ email }} to join.</p>
                        <p v-if="invitation.message" class="mt-1 text-muted-foreground">“{{ invitation.message }}”</p>
                    </li>
                </ul>
            </section>
            <p class="text-sm text-muted-foreground" data-no-workspace-explanation>{{ explanation }}</p>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <Link v-if="create" :href="create.url" class="inline-flex min-h-11 items-center gap-2 rounded-md px-1 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="create-workspace">{{ create.label }}<ArrowRight class="size-4" aria-hidden="true" /></Link>
                <Link :href="account.url" class="inline-flex min-h-11 items-center gap-2 rounded-md px-1 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-test="account-settings">{{ account.label }}<ArrowRight class="size-4" aria-hidden="true" /></Link>
            </div>
        </div>
    </Page>
</template>
