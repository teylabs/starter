<script setup lang="ts">
import PaneToggle from '@/components/Kit/Layout/PaneToggle.vue';

/*
 * What a collapsed Workbench pane leaves behind: a slim bar (2rem) with the pane's label and its Expand button, so a collapsed navigator, inspector or footer
 * always has a visible way back, not only a hairline resize handle. The button is in the tab order, `aria-expanded="false"`, and `aria-controls` names the
 * pane's region. `side` bars stand beside a column with the label running down; the `bottom` bar lies under the stage.
 */
defineProps<{ label: string; controls: string; edge: 'side' | 'bottom' }>();
const emit = defineEmits<{ expand: [] }>();
</script>
<template>
    <div
        :class="[
            'border-border bg-muted/40 flex shrink-0 items-center gap-1 rounded-md border',
            edge === 'side' ? 'w-8 flex-col py-1' : 'h-8 w-full flex-row px-1',
        ]"
        :data-workbench-collapsed="edge"
    >
        <PaneToggle
            :label="label"
            collapsed
            :aria-controls="controls"
            @click="emit('expand')"
        />
        <span
            :class="[
                'text-muted-foreground truncate text-xs font-medium select-none',
                edge === 'side' && '[writing-mode:vertical-rl]',
            ]"
            aria-hidden="true"
            >{{ label }}</span
        >
    </div>
</template>
