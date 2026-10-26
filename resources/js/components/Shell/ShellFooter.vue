<script setup lang="ts">
/*
 * Quiet, and the same three lines everywhere: the product identity, the credit line, then Terms and Privacy. No ©, and
 * never a second nav. The lines come from the brand config on the server.
 */
defineProps<{ footer: Tey.Kit.Features.SessionUi.Data.ShellData['footer'] }>();
</script>

<template>
    <footer class="border-t border-border">
        <div class="mx-auto grid max-w-2k gap-1 px-4 py-6 text-xs text-muted-foreground sm:px-6 lg:px-8" data-shell-footer>
            <p>{{ footer.identity }}</p>
            <p v-if="footer.credit">{{ footer.credit }}</p>
            <p v-if="footer.links.length">
                <template v-for="(link, index) in footer.links" :key="link.url">
                    <span v-if="index > 0" aria-hidden="true"> · </span>
                    <a :href="link.url" class="underline-offset-4 hover:text-foreground hover:underline">{{ link.label }}</a>
                </template>
            </p>
        </div>
    </footer>
</template>
