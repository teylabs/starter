<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Toaster } from '@kit/ui';
import type { FocusShellShape } from './focusShell';
import Wordmark from './Wordmark.vue';

/*
 * The focus shell: a signed-in page outside any workspace (the welcome steps, no workspace yet), from the server's FocusShellData. The product on the left, an
 * optional "Step n of m", and the account cluster (who is signed in, Switch account, Sign out) on the right: no switcher, navigation, breadcrumbs or footer.
 * The Workspace Layout renders it for a `kind: 'focus'` shell, so a page keeps one layout; it can also be used on its own. `toaster` false leaves the toasts to
 * the host's own root shell.
 */
const props = withDefaults(defineProps<{ shell: FocusShellShape; toaster?: boolean; widthClass?: string }>(), { toaster: true, widthClass: 'max-w-3xl' });
</script>
<template>
    <div class="flex min-h-svh flex-col bg-[var(--shell-ground,var(--color-background))] text-foreground" data-shell-focus>
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <header class="border-b border-shell-bar-border bg-shell-bar text-shell-bar-foreground" data-shell-bar>
            <div :class="['mx-auto flex h-16 min-w-0 items-center gap-3 px-4 sm:px-6', props.widthClass]">
                <Wordmark :product="props.shell.product" />
                <p v-if="props.shell.step" class="min-w-0 truncate text-sm opacity-80" data-focus-step>Step {{ props.shell.step.current }} of {{ props.shell.step.total }}<span class="max-sm:hidden"> · {{ props.shell.step.label }}</span></p>
                <div class="ml-auto flex shrink-0 items-center gap-1 text-sm">
                    <span class="mr-2 max-w-48 truncate max-sm:hidden" :title="props.shell.user.email">{{ props.shell.user.name }}</span>
                    <Link :href="props.shell.links.switch_account" class="inline-flex min-h-11 items-center rounded-md px-2 font-medium hover:underline focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none" data-focus-switch>Switch account</Link>
                    <Link :href="props.shell.links.sign_out" method="post" as="button" class="inline-flex min-h-11 items-center rounded-md px-2 font-medium hover:underline focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none" data-focus-sign-out>Sign out</Link>
                </div>
            </div>
        </header>
        <main id="main" class="flex-1">
            <div :class="['mx-auto w-full px-4 pt-6 pb-10 sm:px-6', props.widthClass]" data-shell-content><slot /></div>
        </main>
        <Toaster v-if="props.toaster" />
    </div>
</template>
