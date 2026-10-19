<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ href: string; product: string }>();
</script>

<template>
    <Link
        :href="href"
        class="shrink-0 truncate rounded-md px-1 text-base font-semibold tracking-tight text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
        {{ product }}
    </Link>
</template>
