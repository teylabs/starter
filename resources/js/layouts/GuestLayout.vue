<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Surface } from '@kit/ui';
import Wordmark from '@/components/Shell/Wordmark.vue';

// The guest pages sit on the page ground, and their card is the kit's own (Surface): the radius, border and lifted shadow of every card after sign-in, and
// what is inside knows it is on a card.

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        wide?: boolean;
        showHeading?: boolean;
    }>(),
    { title: '', description: '', wide: false, showHeading: false },
);

const page = usePage<{
    shell?: Tey.Kit.Features.SessionUi.Data.GuestShellData;
    layout?: { documentTitle: string };
}>();
const product = computed(() => page.props.shell?.product.name ?? '');
</script>

<template>
    <Head :title="page.props.layout?.documentTitle" />
    <div
        class="flex min-h-svh flex-col bg-background text-foreground"
        :class="
            wide ? '' : 'items-center justify-center gap-6 px-4 py-10 sm:px-6'
        "
        data-guest-shell
    >
        <header
            class="flex shrink-0 items-center justify-center"
            :class="wide ? 'h-16 gap-6 px-4' : ''"
        >
            <Wordmark href="/" :product="product" data-guest-wordmark />
        </header>
        <Surface
            as="main"
            id="main"
            :padding="wide ? 'none' : 'md'"
            :clip="false"
            class="w-full min-w-0"
            :class="
                wide
                    ? 'flex-1 [&>.h-svh]:h-[calc(100svh-4rem)]'
                    : 'max-w-md space-y-6 sm:p-8'
            "
            data-guest-card
        >
            <div v-if="showHeading && title" class="space-y-2">
                <h1 class="text-xl font-medium">
                    {{ title }}
                </h1>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <slot />
        </Surface>
    </div>
</template>
