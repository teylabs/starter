<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppearanceChoice from '@/components/Shell/AppearanceChoice.vue';
import ShellFooter from '@/components/Shell/ShellFooter.vue';
import { Toaster } from '@/components/ui/sonner';
import WorkspaceShell from '@/pages/Workspace/Layout.vue';

/*
 * The signed-in shell is tey/kit's (pages/Workspace/Layout.vue), built on the server's `layout` prop (ShellData).
 * This wrapper is the app's part: its mark, the appearance choice in the account menu, the content width, the
 * page title, and a quiet footer. The breadcrumbs are the shell's, shared by gretel from each route's declaration. The shell owns the gutters and the measure, so pages add no outer padding.
 */
defineOptions({ inheritAttrs: false });

const page = usePage<{ layout?: Tey.Kit.Features.SessionUi.Data.ShellData; title?: string }>();
const layout = computed(() => page.props.layout);
</script>

<template>
    <WorkspaceShell v-if="layout" :layout="layout" width-class="max-w-2k">
        <template #mark>{{ layout.product.name }}</template>
        <template #extra>
            <div class="flex min-h-11 items-center justify-between gap-2 px-3 text-sm font-medium text-muted-foreground">
                Appearance
                <AppearanceChoice />
            </div>
        </template>
        <h1 v-if="page.props.title" class="mb-6 font-serif text-4xl tracking-tight [font-weight:var(--font-display-weight)]">{{ page.props.title }}</h1>
        <slot />
        <template #footer>
            <ShellFooter :product="layout.product.name" />
        </template>
    </WorkspaceShell>
    <Toaster />
</template>
