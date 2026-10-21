<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Button from '@/components/Kit/Form/Button.vue';
import ConfirmDialog from '@/components/Kit/Form/ConfirmDialog.vue';

// One row's actions, as the server decided them: nothing here checks a permission, an action that is not in the list is not shown. One or two plain actions
// show inline; three or more become the first inline plus a menu. A destructive action is a menu item whenever another action exists (a lone one is a button), and
// always asks first with the action's own `confirm` text. `intercept` names actions the page performs itself (a dialog) instead of an Inertia visit.
type RowAction = { key: string | null; label: string; url: string; method: string; destructive: boolean; confirm: { title: string; description: string; label: string } | null };
const props = defineProps<{ actions: RowAction[]; label: string; intercept?: string[] }>();
const emit = defineEmits<{ act: [action: RowAction] }>();
const plain = computed(() => props.actions.filter((action) => !action.destructive));
const destructive = computed(() => props.actions.filter((action) => action.destructive));
const inline = computed(() => (plain.value.length >= 3 ? plain.value.slice(0, 1) : plain.value));
const menu = computed(() => [...(plain.value.length >= 3 ? plain.value.slice(1) : []), ...(plain.value.length ? destructive.value : [])]);
const lone = computed(() => (plain.value.length ? [] : destructive.value));
const asking = ref<RowAction | null>(null);
// The dialog closes itself on Confirm (clearing `asking`) around the same click, so the action to run is kept apart.
let pending: RowAction | null = null;
// Its text stays after the dialog closes: the dialog must not be removed in the middle of the click that confirms it, or the confirm never fires.
const shown = ref<RowAction['confirm']>(null);
const asked = computed({ get: () => asking.value !== null, set: (open: boolean) => { if (!open) asking.value = null; } });

function perform(action: RowAction) {
    if (props.intercept?.includes(action.key ?? '')) return emit('act', action);
    if (action.confirm) {
        pending = action;
        shown.value = action.confirm;
        // After the menu has closed and given focus back: opened in the same tick, the menu's own dismissal swallows the confirmation.
        setTimeout(() => (asking.value = action), 0);
        return;
    }
    run(action);
}
function run(action: RowAction) {
    router.visit(action.url, { method: action.method as 'get' | 'post' | 'put' | 'patch' | 'delete', preserveScroll: true, preserveState: true });
}
function confirmed() {
    if (pending) run(pending);
    pending = null;
    asking.value = null;
}
</script>
<template>
    <div v-if="actions.length" class="flex items-center justify-end gap-2" data-row-actions>
        <Button v-for="action in inline" :key="action.key ?? action.label" variant="secondary" type="button" :data-action="action.key" @click="perform(action)">{{ action.label }}</Button>
        <Button v-for="action in lone" :key="action.key ?? action.label" variant="destructive" type="button" :data-action="action.key" @click="perform(action)">{{ action.label }}</Button>
        <DropdownMenu v-if="menu.length">
            <DropdownMenuTrigger as-child><Button variant="secondary" type="button" :aria-label="label" data-row-menu>…</Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem v-for="action in menu" :key="action.key ?? action.label" :class="action.destructive ? 'text-destructive focus:text-destructive' : ''" :data-action="action.key" @select="perform(action)">{{ action.label }}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <ConfirmDialog v-if="shown" v-model:open="asked" :title="shown.title" :description="shown.description" :confirm-label="shown.label" @confirm="confirmed" />
    </div>
</template>
