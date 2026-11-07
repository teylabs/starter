<script setup lang="ts">
import { Maximize, Minus, MoveHorizontal, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { Button as BaseButton } from '@/components/ui/button';
import { zoomLabel } from './workbench';
import { useWorkbench } from './useWorkbench';

/*
 * Stage zoom: fit to width, fit to page, zoom out, the current percentage (it resets to 100%), zoom in. ⌘+ / ⌘- / ⌘0 do the same from anywhere in the Workbench.
 * The choice is remembered per person per workbench with the layout. The app's canvas reads `useWorkbench(key).scale`.
 */
const props = defineProps<{ workbenchKey: string }>();
const wb = useWorkbench(props.workbenchKey);
const percent = computed(() => zoomLabel(wb.scale));
const button = 'size-8 p-0';
</script>
<template>
    <div
        role="group"
        aria-label="Zoom"
        class="inline-flex items-center gap-0.5"
        data-workbench-zoom
    >
        <BaseButton
            type="button"
            variant="ghost"
            :class="button"
            title="Fit to width"
            aria-label="Fit to width"
            :aria-pressed="wb.zoom.fit === 'width'"
            data-zoom="width"
            @click="wb.fit('width')"
            ><MoveHorizontal class="size-4" aria-hidden="true"
        /></BaseButton>
        <BaseButton
            type="button"
            variant="ghost"
            :class="button"
            title="Fit to page"
            aria-label="Fit to page"
            :aria-pressed="wb.zoom.fit === 'page'"
            data-zoom="page"
            @click="wb.fit('page')"
            ><Maximize class="size-4" aria-hidden="true"
        /></BaseButton>
        <BaseButton
            type="button"
            variant="ghost"
            :class="button"
            title="Zoom out (⌘-)"
            aria-label="Zoom out"
            data-zoom="out"
            @click="wb.zoomOut()"
            ><Minus class="size-4" aria-hidden="true"
        /></BaseButton>
        <BaseButton
            type="button"
            variant="ghost"
            class="h-8 min-w-14 px-2 text-xs tabular-nums"
            title="Reset to 100% (⌘0)"
            :aria-label="`Zoom ${percent}, reset to 100%`"
            data-zoom="reset"
            @click="wb.zoomReset()"
            >{{ percent }}</BaseButton
        >
        <BaseButton
            type="button"
            variant="ghost"
            :class="button"
            title="Zoom in (⌘+)"
            aria-label="Zoom in"
            data-zoom="in"
            @click="wb.zoomIn()"
            ><Plus class="size-4" aria-hidden="true"
        /></BaseButton>
    </div>
</template>
