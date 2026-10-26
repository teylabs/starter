<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertDialogCancel, AlertDialogDescription, AlertDialogTitle } from 'reka-ui';
import { ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';
import { useMenuPanel } from '@/components/Kit/Menu/useMenuPanel';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';

// A guarded menu entry (DropdownConfirm.md): choosing it opens a confirmation dialog while the menu stays open behind it; confirming runs `onConfirmed` or an Inertia visit, then closes both. The dialog is the kit's Dialog.
// `noun` is declared and does nothing (an owner question in the spec).
const props = withDefaults(defineProps<{ href?: string; method?: string; onConfirmed?: () => unknown; confirmLabel?: string; variant?: string; noun?: string }>(), { href: undefined, method: 'post', onConfirmed: undefined, confirmLabel: undefined, variant: 'primary', noun: '' });

const menu = useMenuPanel();
const asking = ref(false);
const busy = ref(false);

function ask() {
    if (asking.value) return;

    menu?.hold();
    asking.value = true;
}

function finish() {
    if (busy.value || !asking.value) return;

    asking.value = false;
    menu?.release();
    menu?.close();
}

async function confirm() {
    if (busy.value) return;

    busy.value = true;

    if (props.onConfirmed) {
        try {
            await props.onConfirmed();
        } catch (error) {
            busy.value = false;

            throw error;
        }

        busy.value = false;
        finish();

        return;
    }

    router.visit(props.href as string, {
        method: props.method as 'post' | 'put' | 'patch' | 'delete' | 'get',
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            busy.value = false;
            finish();
        },
    });
}
</script>
<template>
    <div>
        <DropdownItem as="button" @click="ask">
            <template v-if="$slots.icon" #icon><slot name="icon" /></template>
            <slot />
            <template v-if="$slots.caption" #caption><slot name="caption" /></template>
        </DropdownItem>
        <Dialog :open="asking" title="Please confirm" :variant="variant === 'danger' ? 'danger' : 'neutral'" @update:open="(now: boolean) => !now && finish()">
            <template #title><AlertDialogTitle class="text-lg font-semibold"><slot name="title">Please confirm</slot></AlertDialogTitle></template>
            <template #body>
                <AlertDialogDescription as="div" class="space-y-3 text-base">
                    <slot name="confirmation"><p>Are you sure you want to proceed with this action?</p></slot>
                </AlertDialogDescription>
            </template>
            <template #footer>
                <div class="flex flex-wrap justify-end gap-2">
                    <AlertDialogCancel as-child><Button variant="secondary" type="button" :disabled="busy">Nevermind</Button></AlertDialogCancel>
                    <Button :variant="variant === 'danger' ? 'destructive' : 'primary'" type="button" :aria-busy="busy ? 'true' : undefined" :disabled="busy" @click="confirm">{{ confirmLabel || 'Proceed' }}</Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
