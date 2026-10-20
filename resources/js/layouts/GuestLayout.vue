<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Wordmark from '@/components/Shell/Wordmark.vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        wide?: boolean;
        showHeading?: boolean;
    }>(),
    { title: '', description: '', wide: false, showHeading: false },
);

const page = usePage<{ layout?: Tey.Kit.Features.SessionUi.Data.ShellData }>();
const product = computed(() => page.props.layout?.brand ?? '');
</script>

<template>
    <div
        class="flex min-h-svh flex-col bg-muted/40 text-foreground"
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
        <div
            class="w-full min-w-0 rounded-lg bg-card text-card-foreground shadow-xs ring-1 ring-border"
            :class="
                wide
                    ? 'flex-1 [&>.h-svh]:h-[calc(100svh-4rem)]'
                    : 'max-w-md space-y-6 p-6 sm:p-8'
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
        </div>
    </div>
</template>
