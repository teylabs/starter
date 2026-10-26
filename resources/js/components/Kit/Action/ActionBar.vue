<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ActionMenuItems from '@/components/Kit/Action/ActionMenuItems.vue';
import ActionSegment from '@/components/Kit/Action/ActionSegment.vue';
import ButtonGroup from '@/components/Kit/Action/ButtonGroup.vue';
import { layoutActions, placeActions, type ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Toolbar from '@/components/Kit/Layout/Toolbar.vue';
import { inverseControl, inverseFilled, useSurface } from '@/components/Kit/Surface/surface';
import { withoutEditActions } from '@/components/Kit/Form/locked';
import { useLocked } from '@/components/Kit/Form/useLocked';

/*
 * Server action descriptors as button groups. With declared `group`s each group (or a standalone action) is one ButtonGroup, its `menu` actions behind a ⌄;
 * without, the placement rule decides. Dialog intent opens the route modal, document intent a new tab, ordinary GETs navigate; every other action is emitted for
 * the host to run (confirmation included). Nothing here checks a permission. `busy` blocks all but a standalone document link; `dialogOpen` holds menu focus for the
 * dialog the host opens (pill surface). On an inverse pill surface (FloatingToolbar) it keeps segments of its own instead of ButtonGroup: the pill's tokens do restyle a
 * ButtonGroup (tone), but ButtonGroup has no hook for the ⌄ trigger's name, the focus hold or the pill's radius and 36px height, and has no filled segment. Below `sm`
 * the bar keeps one row: the main action and a ⌄ menu with every other action, in order. Inside a locked LockedForm (the LockedForm spec) an `intent: 'edit'` descriptor is hidden; the server should not send it, this covers one that slips through.
 */
const props = withDefaults(defineProps<{ actions: ActionDescriptor[]; busy?: boolean; dialogOpen?: boolean; primary?: boolean }>(), { busy: false, dialogOpen: false, primary: false });
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const surface = useSurface();
const lock = useLocked();
const shown = computed(() => withoutEditActions(props.actions, lock.locked));
const parts = computed(() => layoutActions(shown.value)?.map(part => (part.kind === 'group' ? part : { key: part.key, inline: [part.action], menu: [] as ActionDescriptor[] }))
    ?? [{ key: 'actions', ...placeActions(shown.value) }]);
const pill = computed(() => surface.value.shape === 'pill');
const inverse = computed(() => surface.value.tone === 'inverse');
const ordered = computed(() => parts.value.flatMap(part => [...part.inline, ...part.menu]));
const compact = computed(() => ({ key: 'compact', inline: ordered.value.slice(0, 1), menu: ordered.value.slice(1) }));
const group = computed(() => ['inline-flex isolate min-w-0 [&>*+*]:-ml-px', pill.value ? '[&>*:first-child]:rounded-l-full [&>*:last-child]:rounded-r-full' : '[&>*:first-child]:rounded-l-md [&>*:last-child]:rounded-r-md']);
function segment(filled: boolean): string[] {
    return [
        'inline-flex min-w-0 shrink items-center justify-center rounded-none text-sm font-medium whitespace-nowrap no-underline transition-colors focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50',
        pill.value ? 'h-9 px-3.5' : 'h-9 px-3',
        inverse.value ? (filled ? `${inverseFilled} [&+*]:border-l [&+*]:border-primary-foreground/25` : inverseControl)
            : filled ? 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90' : 'border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:hover:bg-input/50',
    ];
}
const filled = (index: number) => props.primary && index === 0;
const holdFocus = (event: Event) => { if (props.dialogOpen) event.preventDefault(); };
</script>
<template>
    <Toolbar v-if="shown.length && pill" class="shrink-0 max-sm:flex-nowrap" data-action-bar>
        <template v-for="(set, layout) in [{ parts, class: 'max-sm:hidden' }, { parts: [compact], class: 'shrink-0 sm:hidden *:shrink-0' }]" :key="layout">
            <div v-for="part in set.parts" :key="part.key" role="group" :class="[group, set.class]" data-action-group :data-action-layout="part.key">
                <ActionSegment v-for="(action, index) in part.inline" :key="action.key ?? action.url" :action="action" :busy="busy" :class="segment(filled(index))" @action="emit('action', $event)" />
                <DropdownMenu v-if="part.menu.length">
                    <DropdownMenuTrigger as-child>
                        <button type="button" :class="[segment(false), 'px-2.5']" :aria-label="part.inline.length ? `More actions: ${part.inline[0].label}` : 'More actions'" data-action-menu><ChevronDown class="size-4" aria-hidden="true" /></button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" @close-auto-focus="holdFocus">
                        <ActionMenuItems :actions="part.menu" :busy="busy" @action="emit('action', $event)" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </template>
    </Toolbar>
    <Toolbar v-else-if="shown.length" data-action-bar>
        <ButtonGroup v-for="part in parts" :key="part.key" :label="part.inline.length ? undefined : 'Actions'" data-action-group :data-action-layout="part.key">
            <template v-if="part.inline.length" #default>
                <ActionSegment v-for="(action, index) in part.inline" :key="action.key ?? action.url" :action="action" :busy="busy" :class="filled(index) && 'primary-action font-semibold!'" @action="emit('action', $event)" />
            </template>
            <template v-if="part.menu.length" #menu>
                <ActionMenuItems :actions="part.menu" :busy="busy" @action="emit('action', $event)" />
            </template>
        </ButtonGroup>
    </Toolbar>
</template>
