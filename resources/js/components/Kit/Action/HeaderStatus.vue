<script setup lang="ts">
import { Check, CircleAlert, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import type { HeaderStatus } from '@/components/Kit/Action/headerSplit';
import { useSurface } from '@/components/Kit/Surface/surface';

/*
 * A page header's status, before its actions: a muted text-sm live region the kit owns (role="status", polite), never a button. The slot is free-form text; the
 * `status` sugar is a small icon and a label (saved, saving, idle or error). Below `sm` the label is shortened to fit beside the primary action, the icon kept.
 * On an inverse surface (the FloatingToolbar pill) it takes the pill's text colour, softened.
 */
defineProps<{ status?: HeaderStatus | null }>();
const surface = useSurface();
const tone = computed(() =>
    surface.value.tone === 'inverse' ? 'opacity-80' : 'text-muted-foreground',
);
</script>
<template>
    <p
        role="status"
        aria-live="polite"
        :class="['flex min-w-0 items-center gap-1.5 text-sm', tone]"
        data-header-status
        :data-state="status?.state"
    >
        <slot>
            <template v-if="status">
                <LoaderCircle
                    v-if="status.state === 'saving'"
                    class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <Check
                    v-else-if="status.state === 'saved'"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                <CircleAlert
                    v-else-if="status.state === 'error'"
                    class="text-destructive-text size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 truncate max-sm:max-w-24">{{
                    status.label
                }}</span>
            </template>
        </slot>
    </p>
</template>
