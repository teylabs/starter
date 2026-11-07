<script setup lang="ts">
import { inject } from 'vue';
import { scopeKey } from './scope';
const scope = inject(scopeKey);
if (!scope) throw new Error('ComputedValueToggle requires ComputedValueScope.');
</script>
<template>
    <button
        type="button"
        :aria-pressed="scope!.all.value"
        class="border-border focus-visible:ring-ring min-h-11 rounded-md border px-4 focus-visible:ring-2"
        @click="
            scope!.request(
                !scope!.all.value,
                $event.currentTarget as HTMLElement,
            )
        "
    >
        {{ scope!.all.value ? 'Hide all working' : 'Show all working' }}
    </button>
    <span
        v-if="scope!.some.value && !scope!.all.value"
        class="text-muted-foreground ml-2 text-sm"
        >Some working shown</span
    >
</template>
