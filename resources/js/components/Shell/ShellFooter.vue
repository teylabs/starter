<script setup lang="ts">
/* Quiet: the product and a link or two. Never a second nav; the environment pill lives in the header. */
defineProps<{ product: string; links?: { label: string; url: string }[] }>();
</script>

<template>
    <footer class="border-t border-border">
        <div
            class="mx-auto flex max-w-2k flex-wrap items-center gap-x-4 gap-y-2 px-4 py-6 text-xs text-muted-foreground sm:px-6 lg:px-8"
        >
            <span>© 2026 {{ product }}</span>
            <a
                v-for="link in links ?? []"
                :key="link.url"
                :href="link.url"
                class="underline-offset-4 hover:text-foreground hover:underline"
            >
                {{ link.label }}
            </a>
        </div>
    </footer>
</template>
