<script setup lang="ts">
import { SplitterGroup, SplitterPanel, SplitterResizeHandle } from 'reka-ui';
import { onBeforeUnmount, ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import PaneScroll from './PaneScroll.vue';
import PaneToggle from './PaneToggle.vue';
import { splitStorage } from './splitState';
import { useStickyWhenFits } from './useStickyWhenFits';

/*
 * Work beside its context: `main` on the left, `aside` on the right, on Reka's Splitter (keyboard resize in 5-point steps, limits, collapsible). Below `breakpoint` the aside
 * moves into the kit Modal's drawer (`mobile="sheet"`, with an opener) or stays in the flow (`inline`). The layout state is remembered per person through `preference`
 * (server state wins; guarded localStorage is the fallback). State and scope are read once: remount (a new :key) when the identity changes. The aside slot can be mounted
 * twice (pane and sheet), so keep ids and effects inside it safe to duplicate.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ storageKey: string; storageScope: string; label: string; state?: string | null; preference?: { url: string; method: string } | null; breakpoint?: 'lg' | 'xl'; asideSize?: number; mobile?: 'sheet' | 'inline'; showMobileTrigger?: boolean; mainScroll?: boolean; compact?: boolean; stickyAside?: boolean }>(), { state: null, preference: null, breakpoint: 'lg', asideSize: 35, mobile: 'sheet', showMobileTrigger: true, mainScroll: true });
const panel = ref<InstanceType<typeof SplitterPanel>>();
const collapsed = ref(false);
const sheet = ref(false);
const saved = ref('');
let opener: HTMLElement | null = null;
function toggle() { if (collapsed.value) panel.value?.expand(); else panel.value?.collapse(); }
function show(target?: HTMLElement) { opener = target ?? (document.activeElement as HTMLElement | null); sheet.value = true; }
// Focus goes back to a still-connected opener once the dialog has left the page.
let focusObserver: MutationObserver | undefined;
function returnFocus() {
    focusObserver?.disconnect();
    const open = () => document.querySelector('dialog[open], [role="dialog"]');
    const restore = () => { if (opener?.isConnected) opener.focus({ preventScroll: true }); };
    if (!open()) { restore(); return; }
    focusObserver = new MutationObserver(() => { if (!open()) { focusObserver?.disconnect(); restore(); } });
    focusObserver.observe(document.body, { attributes: true, attributeFilter: ['open'], childList: true, subtree: true });
}
onBeforeUnmount(() => focusObserver?.disconnect());
defineExpose({ show });
// `stickyAside`: the aside sticks only while it fits below the shell header; taller, it scrolls with the page and never has a scrollbar of its own.
const { el: asideEl, sticky: asideSticky } = useStickyWhenFits(!!props.stickyAside);
const storage = splitStorage({ key: props.storageKey, scope: props.storageScope, state: props.state, preference: props.preference, saved: value => { saved.value = value; } });
// Literal class names, so the host's Tailwind finds them.
const xl = props.breakpoint === 'xl';
const desktopOnly = xl ? 'max-xl:hidden' : 'max-lg:hidden';
const phoneOnly = xl ? 'xl:hidden' : 'lg:hidden';
const stacked = xl ? 'max-xl:!block' : 'max-lg:!block';
</script>
<template>
    <div class="grid min-h-0 min-w-0" :class="compact ? 'grid-rows-[minmax(0,1fr)]' : 'grid-rows-[auto_minmax(0,1fr)] gap-2'" v-bind="$attrs" :data-preference-value="saved || undefined" data-split-pane>
        <div v-if="!compact" class="flex justify-end gap-2" :class="desktopOnly">
            <Button type="button" variant="secondary" :aria-expanded="!collapsed" @click="toggle">{{ collapsed ? 'Show' : 'Hide' }} {{ label.toLowerCase() }}</Button>
        </div>
        <Button v-if="mobile === 'sheet' && showMobileTrigger" type="button" variant="secondary" class="justify-self-end" :class="phoneOnly" @click="show($event.currentTarget as HTMLElement)">{{ label }}</Button>
        <SplitterGroup direction="horizontal" :auto-save-id="storageKey" :storage="storage" :keyboard-resize-by="5" :class="['min-h-0 min-w-0', stickyAside && '!overflow-visible', stacked]">
            <SplitterPanel id="main" :default-size="100 - asideSize" :min-size="25" :class="['min-h-0 min-w-0', stickyAside && '!overflow-visible']">
                <PaneScroll v-if="mainScroll"><slot name="main" /></PaneScroll><slot v-else name="main" />
            </SplitterPanel>
            <SplitterResizeHandle :aria-label="`Resize ${label.toLowerCase()} pane`" :class="['w-8 shrink-0 rounded focus-visible:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring', desktopOnly]"><span aria-hidden="true" class="mx-auto block h-full w-px bg-border" /></SplitterResizeHandle>
            <SplitterPanel id="aside" ref="panel" :default-size="asideSize" :min-size="20" :max-size="75" collapsible :collapsed-size="compact ? 3 : 0" :class="['min-h-0 min-w-0', stickyAside && '!overflow-visible', mobile === 'sheet' && desktopOnly]" @collapse="collapsed = true" @expand="collapsed = false">
                <div v-if="compact && collapsed" class="flex justify-center pt-1"><PaneToggle :label="label" :collapsed="collapsed" @click="toggle" /></div>
                <div v-show="!compact || !collapsed" ref="asideEl" :class="stickyAside ? ['min-w-0', asideSticky && 'sticky top-[7.5rem]'] : 'h-full min-h-0 min-w-0'"><PaneScroll v-if="!stickyAside"><slot name="aside" :sheet="false" :collapsed="collapsed" :toggle="toggle" /></PaneScroll><slot v-else name="aside" :sheet="false" :collapsed="collapsed" :toggle="toggle" /></div>
            </SplitterPanel>
        </SplitterGroup>
        <Modal v-if="mobile === 'sheet'" v-model:open="sheet" :title="label" placement="drawer" size="lg" @closed="returnFocus"><slot name="aside" :sheet="true" :collapsed="false" :toggle="() => { sheet = false; }" /></Modal>
    </div>
</template>
