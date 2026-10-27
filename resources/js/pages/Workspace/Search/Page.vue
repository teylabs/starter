<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Input, Page, PageEnd } from '@kit/ui';
import { ref } from 'vue';

// Global search's full results: each provider's group, as the server sent them. A result without a URL has no page of its own and is shown as text.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.SearchViewModel>();
const term = ref(props.query);
function submit(): void {
    const q = term.value.trim();
    router.get(props.url, q ? { q } : {}, { preserveState: true, replace: true });
}
</script>
<template>
    <Page md :title="title">
        <form role="search" class="flex items-center gap-2" data-search-page-form @submit.prevent="submit">
            <Input v-model="term" type="search" name="q" maxlength="100" aria-label="Search" placeholder="Search" class="max-w-md" />
        </form>
        <p v-if="partial" class="mt-4 text-sm text-muted-foreground" role="status" data-search-partial>Some sources could not be searched, so these results may be incomplete.</p>
        <p v-if="query && !groups.length" class="mt-6 text-sm text-muted-foreground" data-search-none>No results for “{{ query }}”.</p>
        <section v-for="group in groups" :key="group.key" class="mt-6" :aria-labelledby="`search-group-${group.key}`" :data-search-group="group.key">
            <h2 :id="`search-group-${group.key}`" class="text-sm font-medium text-muted-foreground">{{ group.label }}</h2>
            <ul class="mt-2 divide-y divide-border rounded-lg ring-1 ring-border">
                <li v-for="result in group.results" :key="result.id" class="px-4 py-3" data-search-result>
                    <Link v-if="result.url" :href="result.url" class="font-medium underline-offset-4 hover:underline">{{ result.label }}</Link>
                    <span v-else class="font-medium">{{ result.label }}</span>
                    <p v-if="result.description || result.meta" class="text-sm text-muted-foreground">{{ [result.description, result.meta].filter(Boolean).join(' · ') }}</p>
                </li>
            </ul>
        </section>
        <PageEnd />
    </Page>
</template>
