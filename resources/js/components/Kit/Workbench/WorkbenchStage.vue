<script setup lang="ts">
import { SplitterGroup, SplitterPanel, SplitterResizeHandle } from 'reka-ui';
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import PaneScroll from '@/components/Kit/Layout/PaneScroll.vue';
import PaneToggle from '@/components/Kit/Layout/PaneToggle.vue';
import WorkbenchCollapsedBar from './WorkbenchCollapsedBar.vue';
import { useWorkbench } from './useWorkbench';
import { provideWorkbenchStage } from './workbenchStage';

/*
 * The Workbench's stage column: the optional `toolbar` (insert, format) sticky at the top of the stage, the stage itself, and the optional `footer` under it
 * (speaker notes, working totals) on a vertical Reka Splitter, resizable and collapsible, remembered with the other panes. The stage reports the space it has, so a
 * fit zoom follows it; a collapsed footer leaves its slim Expand bar (WorkbenchCollapsedBar) under the stage; `canvas` is the content's natural size (a 1920×1080 slide), without which a fit is 100%. `footer` false (the phone tabs show the footer as its own tab) leaves the footer out.
 * It provides the stage context (provideWorkbenchStage: the key, the viewport and the toolbar's element) for a stage-anchored or docked FloatingToolbar.
 */
const props = withDefaults(
    defineProps<{
        workbenchKey: string;
        storage?: {
            getItem: (id: string) => string | null;
            setItem: (id: string, value: string) => void;
        } | null;
        footer?: boolean;
        toolbar?: boolean;
        canvas?: { width: number; height: number } | null;
        stageLabel: string;
        footerLabel: string;
    }>(),
    { storage: null, footer: false, toolbar: false, canvas: null },
);
const wb = useWorkbench(props.workbenchKey);
const viewport = ref<HTMLElement>();
const toolbarEl = ref<HTMLElement>();
provideWorkbenchStage({
    workbenchKey: props.workbenchKey,
    viewport,
    toolbar: toolbarEl,
});
const footerPanel = ref<InstanceType<typeof SplitterPanel>>();
const footerId = `workbench-${useId()}-footer`;
let observer: ResizeObserver | undefined;
function measure(): void {
    if (!viewport.value) return;
    const box = viewport.value.getBoundingClientRect();
    wb.measure(props.canvas ?? { width: 0, height: 0 }, {
        width: box.width,
        height: box.height,
    });
}
watch(() => props.canvas, measure, { deep: true });
onMounted(() => {
    measure();
    if (viewport.value && 'ResizeObserver' in window) {
        observer = new ResizeObserver(measure);
        observer.observe(viewport.value);
    }
});
onBeforeUnmount(() => observer?.disconnect());
const toggleFooter = () => {
    if (wb.collapsed.footer) footerPanel.value?.expand();
    else footerPanel.value?.collapse();
};
let unregisterFooter: (() => void) | undefined;
onMounted(() => {
    if (props.footer)
        unregisterFooter = wb.registerPane('footer', toggleFooter);
});
onBeforeUnmount(() => unregisterFooter?.());
</script>
<template>
    <SplitterGroup
        v-if="footer"
        direction="vertical"
        auto-save-id="stage"
        :storage="storage ?? undefined"
        :keyboard-resize-by="5"
        class="min-h-0 min-w-0"
        data-workbench-stage-split
    >
        <SplitterPanel
            id="stage"
            :default-size="78"
            :min-size="30"
            class="min-h-0 min-w-0"
        >
            <div class="flex h-full min-h-0 min-w-0 flex-col">
                <div
                    v-if="toolbar"
                    ref="toolbarEl"
                    class="border-border bg-background/95 sticky top-0 z-10 shrink-0 border-b px-2 py-1.5 backdrop-blur"
                    data-workbench-toolbar
                >
                    <slot name="toolbar" />
                </div>
                <div
                    ref="viewport"
                    class="relative min-h-0 flex-1 overflow-auto"
                    data-workbench-viewport
                >
                    <slot name="stage" />
                </div>
            </div>
        </SplitterPanel>
        <SplitterResizeHandle
            :aria-label="`Resize ${footerLabel.toLowerCase()} pane`"
            class="focus-visible:bg-accent focus-visible:ring-ring h-3 shrink-0 rounded focus-visible:ring-2 focus-visible:outline-none"
            ><span
                aria-hidden="true"
                class="bg-border my-auto block h-px w-full"
        /></SplitterResizeHandle>
        <SplitterPanel
            id="footer"
            ref="footerPanel"
            :default-size="22"
            :min-size="10"
            :max-size="60"
            collapsible
            :collapsed-size="0"
            class="min-h-0 min-w-0"
            @collapse="wb.collapsed.footer = true"
            @expand="wb.collapsed.footer = false"
        >
            <section
                :id="footerId"
                :aria-label="footerLabel"
                tabindex="-1"
                class="flex h-full min-h-0 flex-col focus-visible:outline-none"
                data-workbench-region="footer"
            >
                <div
                    class="flex shrink-0 items-center justify-between gap-2 px-1 pb-1"
                >
                    <h2 class="text-muted-foreground text-xs font-medium">
                        {{ footerLabel }}
                    </h2>
                    <PaneToggle
                        :label="footerLabel"
                        :collapsed="false"
                        @click="toggleFooter"
                    />
                </div>
                <PaneScroll class="min-h-0 flex-1"
                    ><slot name="footer"
                /></PaneScroll>
            </section>
        </SplitterPanel>
        <WorkbenchCollapsedBar
            v-if="wb.collapsed.footer"
            :label="footerLabel"
            :controls="footerId"
            edge="bottom"
            @expand="toggleFooter"
        />
    </SplitterGroup>
    <div v-else class="flex h-full min-h-0 min-w-0 flex-col">
        <div
            v-if="toolbar"
            ref="toolbarEl"
            class="border-border bg-background/95 sticky top-0 z-10 shrink-0 border-b px-2 py-1.5 backdrop-blur"
            data-workbench-toolbar
        >
            <slot name="toolbar" />
        </div>
        <div
            ref="viewport"
            class="relative min-h-0 flex-1 overflow-auto"
            data-workbench-viewport
        >
            <slot name="stage" />
        </div>
    </div>
</template>
