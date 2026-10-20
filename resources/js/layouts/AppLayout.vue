<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AppearanceChoice, AppearanceToggle } from '@kit/ui';
import ShellFooter from '@/components/Shell/ShellFooter.vue';
import { Toaster } from '@/components/ui/sonner';
import WorkspaceShell from '@/pages/Workspace/Layout.vue';

/*
 * The signed-in shell is tey/kit's (pages/Workspace/Layout.vue), built on the server's `layout` prop (ShellData); its mark is the platform's logomark and APP_NAME.
 * This wrapper is the app's part: the appearance choice in the account menu, the content width and a quiet footer.
 * The page title is the page's: <Page> renders it, with the page's actions, in the content column. The breadcrumbs are the shell's, shared by gretel from each route's declaration. The shell owns the gutters and the measure, so pages add no outer padding.
 */
defineOptions({ inheritAttrs: false });

const page = usePage<{ layout?: Tey.Kit.Features.SessionUi.Data.ShellData }>();
const layout = computed(() => page.props.layout);
</script>

<template>
    <WorkspaceShell v-if="layout" :layout="layout" width-class="max-w-2k">
        <template #actions><AppearanceToggle /></template>
        <template #extra>
            <div class="flex min-h-11 items-center justify-between gap-2 px-3 text-sm font-medium text-muted-foreground">
                Appearance
                <AppearanceChoice />
            </div>
        </template>
        <slot />
        <template #footer>
            <ShellFooter :footer="layout.footer" />
        </template>
    </WorkspaceShell>
    <Toaster />
</template>
