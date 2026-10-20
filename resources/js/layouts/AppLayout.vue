<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppearanceChoice from '@/components/Shell/AppearanceChoice.vue';
import ShellBreadcrumbs from '@/components/Shell/ShellBreadcrumbs.vue';
import ShellFooter from '@/components/Shell/ShellFooter.vue';
import { Toaster } from '@/components/ui/sonner';
import WorkspaceShell from '@/pages/Workspace/Layout.vue';

/*
 * The signed-in shell is tey/kit's (pages/Workspace/Layout.vue), built on the server's `layout` prop (ShellData).
 * This wrapper is the app's part: its mark, the appearance choice in the account menu, the content width, the
 * breadcrumbs and title, and a quiet footer. The shell owns the gutters and the measure, so pages add no outer padding.
 */
defineOptions({ inheritAttrs: false });

const page = usePage<{ layout?: Tey.Kit.Features.SessionUi.Data.ShellData; breadcrumbs?: { title: string; url: string }[]; title?: string }>();
const layout = computed(() => page.props.layout);
const crumbs = computed(() =>
    (page.props.breadcrumbs ?? []).map((crumb) => ({
        label: crumb.title,
        url: crumb.url,
    })),
);
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
        <ShellBreadcrumbs :breadcrumbs="crumbs" />
        <h1 v-if="page.props.title" class="mb-6 font-serif text-4xl tracking-tight [font-weight:var(--font-display-weight)]">{{ page.props.title }}</h1>
        <slot />
        <template #footer>
            <ShellFooter :product="layout.product.name" />
        </template>
    </WorkspaceShell>
    <Toaster />
</template>
