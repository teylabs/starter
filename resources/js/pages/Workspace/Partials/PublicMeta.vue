<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
 * A public page's head: the title, its description, and the Open Graph and Twitter cards a shared link shows (`image` an absolute URL, `url` the canonical one).
 * Rendered once per page through Inertia's Head, so server-side rendering and a shared link get the same tags. No tracking tags, ever.
 */
const props = withDefaults(defineProps<{ title: string; description?: string | null; image?: string | null; url?: string | null; type?: 'website' | 'article' }>(), { description: null, image: null, url: null, type: 'website' });
const card = computed(() => (props.image ? 'summary_large_image' : 'summary'));
</script>
<template>
    <Head :title="title">
        <meta v-if="description" head-key="description" name="description" :content="description" />
        <link v-if="url" head-key="canonical" rel="canonical" :href="url" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta v-if="description" head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:type" property="og:type" :content="type" />
        <meta v-if="url" head-key="og:url" property="og:url" :content="url" />
        <meta v-if="image" head-key="og:image" property="og:image" :content="image" />
        <meta head-key="twitter:card" name="twitter:card" :content="card" />
    </Head>
</template>
