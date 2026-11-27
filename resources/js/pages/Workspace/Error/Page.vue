<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SearchX } from '@lucide/vue';
import { Button, EmptyState, Page } from '@kit/ui';
import { computed, inject, type Ref } from 'vue';
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.ErrorViewModel>();
// The title is the page's one h1. In the app shell <Page> draws it (the layout's title); in a GuestShell given the title it is the shell's. Anywhere else
// (a guest's error outside the app shell: the PublicShell, a bare GuestShell, a host's own layout) nothing draws it, so the page does.
const inShell = inject('kit.shell', false);
const shellTitled = inject<Ref<boolean> | null>('kit.shell.titled', null);
const ownHeading = computed(() => !inShell && !shellTitled?.value);
// A guest (no app shell) can't use "your workspaces" yet: they are offered signing in, and the home page, with copy that says so.
const shell = computed(() => (usePage().props.shell ?? {}) as { kind?: string; links?: { sign_in?: string; home?: string } });
const guest = computed(() => !inShell && shell.value.kind !== 'app');
const message = computed(() => (guest.value ? 'This page may not exist, or you may need to sign in to see it.' : props.message));
const primary = computed(() => (guest.value ? { href: shell.value.links?.sign_in ?? '/login', label: 'Sign in' } : { href: props.recovery_url, label: props.recovery_label }));
</script>
<template>
    <Page xs>
        <h1 v-if="ownHeading" class="font-display text-3xl font-(weight:--font-display-weight) tracking-tight" data-error-heading>{{ title }}</h1>
        <EmptyState :title="status === 404 ? 'We can’t show you this page' : 'This is unavailable'" :level="2" :icon="SearchX" data-error-state :data-error-guest="guest || undefined">
            <p role="alert">{{ message }}</p>
            <template #action>
                <Button :href="primary.href" data-error-recovery>{{ primary.label }}</Button>
                <Button v-if="guest" :href="shell.links?.home ?? '/'" variant="ghost" data-error-home>Go to the home page</Button>
            </template>
        </EmptyState>
    </Page>
</template>
