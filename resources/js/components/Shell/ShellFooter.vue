<script setup lang="ts">
/*
 * The slim one-line utility bar: the product identity, the credit line, then Terms and Privacy, all on one quiet line (a © is optional and comes from the brand config) that
 * wraps at the separators on a phone. Never a second nav. The lines come from the brand config on the server.
 *
 * The separators are explicit: Vue's whitespace condensing drops the newline-only text between elements, so without the
 * `{{ ' ' }}` spaces the dot sat flush against the words ("Terms·Privacy"). The dots are aria-hidden; the spaces keep the
 * links distinct for a screen reader and give the line somewhere to wrap. Each item stays whole (`whitespace-nowrap`).
 */
import { computed } from 'vue';

const props = defineProps<{
    footer: Tey.Kit.Features.SessionUi.Data.ShellData['footer'];
}>();

const items = computed(() => [
    {
        key: 'identity',
        label: props.footer.identity,
        url: null as string | null,
    },
    ...(props.footer.credit
        ? [
              {
                  key: 'credit',
                  label: props.footer.credit,
                  url: null as string | null,
              },
          ]
        : []),
    ...props.footer.links.map((link) => ({
        key: link.url,
        label: link.label,
        url: link.url as string | null,
    })),
]);
</script>

<template>
    <footer class="border-t border-border">
        <p
            class="mx-auto max-w-2k px-4 py-3 text-xs leading-relaxed text-muted-foreground sm:px-6 lg:px-8"
            data-shell-footer
        >
            <template v-for="(item, index) in items" :key="item.key">
                <template v-if="index > 0"
                    >{{ ' '
                    }}<span
                        aria-hidden="true"
                        class="mx-1"
                        data-shell-footer-separator
                        >·</span
                    >{{ ' ' }}</template
                >
                <a
                    v-if="item.url"
                    :href="item.url"
                    class="whitespace-nowrap underline-offset-4 hover:text-foreground hover:underline"
                    >{{ item.label }}</a
                >
                <span v-else class="whitespace-nowrap">{{ item.label }}</span>
            </template>
        </p>
    </footer>
</template>
