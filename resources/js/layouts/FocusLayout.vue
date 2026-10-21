<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AppearanceToggle, Avatar } from '@kit/ui';
import { Toaster } from '@/components/ui/sonner';

/*
 * The FocusShell: a signed-in page outside any workspace (accept an invitation, switch account, the welcome steps, the no-workspace page). The product's mark and name
 * sit at the left, the account cluster at the right, a centred column below with an optional "Step n of m". No switcher, navigation, breadcrumbs or footer.
 * Everything comes from the server's `shell` (FocusShellData); this decides nothing.
 */
defineOptions({ inheritAttrs: false });

const page = usePage<{ shell: Tey.Kit.Features.SessionUi.Data.FocusShellData; layout?: { documentTitle: string } }>();
const shell = computed(() => page.props.shell);
</script>

<template>
    <Head :title="page.props.layout?.documentTitle" />
    <div class="flex min-h-svh flex-col bg-muted/40 text-foreground" data-focus-shell>
        <header class="flex h-14 shrink-0 items-center justify-between gap-3 px-4 sm:px-6" data-focus-header>
            <Link :href="shell.product.url" class="flex min-w-0 items-center gap-2 rounded-md px-1 text-base font-semibold tracking-tight focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-focus-product>
                <img v-if="shell.product.logomark" :src="shell.product.logomark" alt="" class="h-6 w-auto shrink-0 dark:invert" />
                <span class="truncate">{{ shell.product.name }}</span>
            </Link>
            <div class="flex shrink-0 items-center gap-2 sm:gap-3" data-focus-account>
                <Avatar :initials="shell.user.initials" :src="shell.user.avatar_url" :src2x="shell.user.avatar_url_2x" />
                <span class="hidden max-w-48 truncate text-sm text-muted-foreground md:inline" data-focus-email>{{ shell.user.email }}</span>
                <Link :href="shell.links.switch_account" method="post" as="button" class="rounded-md px-1 text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:text-sm" data-focus-switch>Switch account</Link>
                <Link :href="shell.links.sign_out" method="post" as="button" class="rounded-md px-1 text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:text-sm" data-focus-sign-out>Sign out</Link>
                <AppearanceToggle />
            </div>
        </header>
        <main class="flex flex-1 flex-col items-center gap-4 px-4 pt-6 pb-10 sm:px-6 sm:pt-12" data-focus-body>
            <p v-if="shell.step" class="text-sm text-muted-foreground" data-focus-step :aria-label="`Step ${shell.step.current} of ${shell.step.total}: ${shell.step.label}`">
                Step {{ shell.step.current }} of {{ shell.step.total }} · {{ shell.step.label }}
            </p>
            <div class="w-full max-w-xl min-w-0 rounded-lg bg-card p-6 text-card-foreground shadow-xs ring-1 ring-border sm:p-8" data-focus-card>
                <slot />
            </div>
        </main>
    </div>
    <Toaster />
</template>
