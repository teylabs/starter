<script setup lang="ts">
import { Button, Page } from '@kit/ui';

// A signed-in person with no workspace: a new account, or someone who left or was removed. Open invitations to their email come first, then what to do next. The shell
// around it is the FocusShell (the product, Switch account and Sign out), and there is no create action unless the server sends one.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.NoWorkspaceViewModel>();
</script>
<template>
    <Page xs :heading="false">
        <div class="grid gap-6" data-no-workspace>
            <h1 class="font-display text-2xl font-semibold tracking-tight">{{ title }}</h1>
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
                <!-- One primary step: creating a workspace when the server offers it, otherwise the account; the other is a quiet button beside it. -->
                <Button v-if="create" :href="account.url" variant="ghost" data-test="account-settings">{{ account.label }}</Button>
                <Button :href="create ? create.url : account.url" :data-test="create ? 'create-workspace' : 'account-settings'">{{ create ? create.label : account.label }}</Button>
            </div>
        </div>
    </Page>
</template>
