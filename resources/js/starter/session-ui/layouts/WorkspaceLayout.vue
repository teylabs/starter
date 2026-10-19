<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
const props = defineProps<{ title: string }>();
const page = usePage<{ shell: App.Starter.SessionUi.Data.ShellData }>();
</script>
<template>
    <Head :title="props.title" />
    <a class="skip" href="#content">Skip to content</a>
    <header>
        <Link href="/workspaces" class="brand">{{ page.props.shell.brand }}</Link>
        <span v-if="page.props.shell.workspace">{{ page.props.shell.workspace.name }}</span>
        <div v-if="page.props.shell.user" class="account"><span>{{ page.props.shell.user.name }}</span><Link v-if="page.props.shell.logout_url" :href="page.props.shell.logout_url" method="post" as="button">Sign out</Link></div>
    </header>
    <div class="frame">
        <aside v-if="page.props.shell.user">
            <nav aria-label="Workspaces"><h2>Workspaces</h2><ul><li v-for="workspace in page.props.shell.workspaces" :key="workspace.id"><Link v-if="workspace.href" :href="workspace.href" :aria-current="workspace.id === page.props.shell.workspace?.id ? 'true' : undefined">{{ workspace.name }}</Link><span v-else>{{ workspace.name }} · unavailable</span></li></ul><p v-if="!page.props.shell.workspaces.length">No active workspaces.</p></nav>
            <nav v-if="page.props.shell.navigation.length" aria-label="Workspace navigation"><ul><li v-for="item in page.props.shell.navigation" :key="item.href"><Link :href="item.href" :aria-current="item.current ? 'page' : undefined">{{ item.label }}</Link></li></ul></nav>
        </aside>
        <main id="content" tabindex="-1"><h1>{{ title }}</h1><slot /></main>
    </div>
</template>
