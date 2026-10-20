<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
// The folder layout for pages under pages/Workspace. The host's Inertia layout callback applies it with the
// backend's `layout` props; pages declare no layout.
const layout = defineProps<Tey.Kit.Features.SessionUi.Data.ShellData>();
const page = usePage<{ title: string }>();
</script>
<template>
    <Head :title="page.props.title" />
    <a href="#content">Skip to content</a>
    <header>
        <Link href="/workspaces">{{ layout.brand }}</Link>
        <span v-if="layout.workspace">{{ layout.workspace.name }}</span>
        <div v-if="layout.user"><span>{{ layout.user.name }}</span><Link v-if="layout.logout_url" :href="layout.logout_url" method="post" as="button">Sign out</Link></div>
    </header>
    <div>
        <aside v-if="layout.user">
            <nav aria-label="Workspaces"><h2>Workspaces</h2><ul><li v-for="workspace in layout.workspaces" :key="workspace.id"><Link v-if="workspace.href" :href="workspace.href" :aria-current="workspace.id === layout.workspace?.id ? 'true' : undefined">{{ workspace.name }}</Link><span v-else>{{ workspace.name }} · unavailable</span></li></ul><p v-if="!layout.workspaces.length">No active workspaces.</p></nav>
            <nav v-if="layout.navigation.length" aria-label="Workspace navigation"><ul><li v-for="item in layout.navigation" :key="item.href"><Link :href="item.href" :aria-current="item.current ? 'page' : undefined">{{ item.label }}</Link></li></ul></nav>
        </aside>
        <main id="content" tabindex="-1"><h1>{{ page.props.title }}</h1><slot /></main>
    </div>
</template>
