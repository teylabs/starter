<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { AlertDialogCancel, AlertDialogDescription, AlertDialogTitle } from 'reka-ui';
import { ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';
import { useMenuPanel } from '@/components/Kit/Menu/useMenuPanel';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';

// A menu entry that deletes after a confirmation dialog (DropdownDelete.md); the menu stays open behind the dialog. Confirming sends an Inertia DELETE to `href`, then closes both. Attributes land on the entry's button.
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ href?: string; noun?: string; context?: string; deleteLabel?: string; preserveState?: boolean; preserveScroll?: boolean; onSuccess?: (page: unknown) => void }>(), { href: undefined, noun: undefined, context: undefined, deleteLabel: undefined, preserveState: false, preserveScroll: true, onSuccess: undefined });
const emit = defineEmits<{ confirming: []; success: [page: unknown]; error: [errors: unknown] }>();

const menu = useMenuPanel();
const asking = ref(false);
const busy = ref(false);

function ask() {
    if (asking.value) return;

    menu?.hold();
    asking.value = true;
    emit('confirming');
}

function finish() {
    if (busy.value || !asking.value) return;

    asking.value = false;
    menu?.release();
    menu?.close();
}

function confirm() {
    if (busy.value) return;

    busy.value = true;

    router.delete(props.href as string, {
        preserveState: props.preserveState,
        preserveScroll: props.preserveScroll,
        onSuccess: (page) => {
            busy.value = false;
            finish();
            // An `on-success` prop and a `@success` listener share one key, so emitting reaches either exactly once.
            emit('success', page);
        },
        onError: (errors) => emit('error', errors),
    });
}
</script>
<template>
    <DropdownItem v-bind="$attrs" as="button" @click="ask">
        <template #icon><Trash2 aria-hidden="true" class="size-4 text-destructive" /></template>
        <slot>Delete</slot>
        <template v-if="$slots.description" #caption><slot name="description" /></template>
    </DropdownItem>
    <Dialog :open="asking" title="Confirm Delete" variant="danger" @update:open="(now: boolean) => !now && finish()">
        <template #title><AlertDialogTitle class="text-lg font-semibold"><slot name="title">Confirm Delete</slot></AlertDialogTitle></template>
        <template #body>
            <AlertDialogDescription as="div" class="space-y-3 text-base">
                <slot name="message"><p>{{ noun ? `Are you sure you want to delete this ${noun}?` : 'Are you sure you want to delete this?' }}</p></slot>
                <div v-if="$slots.caption || context"><slot name="caption">{{ context }}</slot></div>
            </AlertDialogDescription>
        </template>
        <template #footer>
            <div class="flex flex-wrap justify-end gap-2">
                <AlertDialogCancel as-child><Button variant="secondary" type="button" :disabled="busy">Nevermind</Button></AlertDialogCancel>
                <Button variant="destructive" type="button" :aria-busy="busy ? 'true' : undefined" :disabled="busy" @click="confirm">{{ deleteLabel || 'Delete' }}</Button>
            </div>
        </template>
    </Dialog>
</template>
