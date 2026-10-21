<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AppearanceToggle } from '@kit/ui';
import ShellFooter from '@/components/Shell/ShellFooter.vue';
import { Toaster } from '@/components/ui/sonner';
import WorkspaceShell from '@/pages/Workspace/Layout.vue';

/*
 * The signed-in shell is tey/kit's (pages/Workspace/Layout.vue), built on the server's `shell` prop (ShellData); its mark is the platform's logomark and APP_NAME.
 * This wrapper is the app's part: the appearance choice in the account menu, the content width and a quiet footer.
 * The page title is the page's: <Page> renders it, with the page's actions, in the content column. The breadcrumbs are the shell's, shared by gretel from each route's declaration. The shell owns the gutters and the measure, so pages add no outer padding.
 */
defineOptions({ inheritAttrs: false });

const page = usePage<{ shell?: Tey.Kit.Features.SessionUi.Data.ShellData | Tey.Kit.Features.SessionUi.Data.GuestShellData }>();
// A guest (a sign-out in another tab, an error page) gets the GuestShell, which has no navigation: the app shell needs the full ShellData.
const shell = computed(() => (page.props.shell && 'navigation' in page.props.shell ? page.props.shell : undefined));
</script>

<template>
    <WorkspaceShell v-if="shell" :shell="shell" width-class="max-w-2k">
        <template #actions><AppearanceToggle /></template>
        <slot />
        <template #footer>
            <ShellFooter :footer="shell.footer" />
        </template>
    </WorkspaceShell>
    <Toaster />
</template>
