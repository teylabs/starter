<script setup lang="ts">
import PublicMeta from '@/pages/Workspace/Partials/PublicMeta.vue';

/*
 * A legal or help document (PublicMarkdown::page): the title, an optional "Last updated", and the body, which is HTML the server rendered from markdown and
 * sanitized (raw HTML stripped, unsafe links refused) before it got here. Like every kit page it declares no layout: the host's layout callback puts `Public/`
 * pages in the PublicShell (docs/public-shell.md), which gives it the reading measure.
 */
defineProps<Tey.Kit.Features.Shell.ViewModels.PublicMarkdownViewModel>();
</script>
<template>
    <PublicMeta :title="title" :description="description" type="article" />
    <article class="mx-auto grid max-w-3xl gap-6" data-public-markdown>
        <header class="grid gap-2">
            <h1 class="font-display text-3xl font-(weight:--font-display-weight) tracking-tight">{{ title }}</h1>
            <p v-if="updated" class="text-sm text-muted-foreground">Last updated {{ updated }}</p>
        </header>
        <!-- Sanitized on the server (PublicMarkdown::html): the only v-html in the kit's public pages. -->
        <div class="grid gap-4 leading-7 [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:mt-4 [&_h3]:text-lg [&_h3]:font-semibold [&_li]:ms-6 [&_ol]:list-decimal [&_ul]:list-disc" v-html="html" />
    </article>
</template>
