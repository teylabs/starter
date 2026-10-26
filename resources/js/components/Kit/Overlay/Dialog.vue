<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick } from 'vue';
import { TriangleAlert } from '@lucide/vue';
import { AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogOverlay, AlertDialogPortal, AlertDialogRoot, AlertDialogTitle, AlertDialogTrigger } from 'reka-ui';
import Button from '@/components/Kit/Form/Button.vue';

// The kit's Dialog: a confirmation or a prompt (a title, a message, actions, and optionally a prompt control in the `body` slot), on Reka's AlertDialog. It is static: Escape and the backdrop do nothing but cancel, and it never holds a form (that is <Modal>). `variant="danger"` draws an icon circle and a destructive action; the default, neutral, does not. `action` (a server action: url and method) is performed by the dialog, which closes when it succeeds; without it `@confirm` is for local handling. Declared in the template, opened with `v-model:open` or by its trigger (the default slot, the button that asks); there is no programmatic confirm(). On a phone it is bottom-aligned.
// Behaviour: the trigger is the default slot (the button that asks), Cancel is
// focused first, and the action runs only on `confirm`. Without a trigger slot it is opened with `v-model:open`. Slots: `icon`, `title`, `body` (content above the buttons, such as a password to confirm with) and `footer`. A destructive confirm (the default) has an icon circle; on a phone it is bottom-aligned.
const props = withDefaults(defineProps<{ title: string; description?: string; confirmLabel?: string; cancelLabel?: string; variant?: 'danger' | 'neutral'; action?: { url: string; method: string } }>(), { description: undefined, confirmLabel: 'Confirm', cancelLabel: 'Cancel', variant: 'neutral' });
const open = defineModel<boolean>('open', { default: false });
// `closed` fires once the dialog has closed and handed focus back, so a caller that closes something more (a modal under it) waits for it.
const emit = defineEmits<{ confirm: []; cancel: []; closed: [] }>();
function confirm() {
    emit('confirm');
    if (props.action) {
        router.visit(props.action.url, { method: props.action.method as 'post' | 'put' | 'patch' | 'delete', preserveScroll: true, onSuccess: () => (open.value = false) });
    }
}
</script>
<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogTrigger v-if="$slots.default" as-child><slot /></AlertDialogTrigger>
        <AlertDialogPortal>
            <AlertDialogOverlay class="fixed inset-0 z-(--z-dialog) bg-black/50" />
            <AlertDialogContent class="fixed top-1/2 left-1/2 z-(--z-dialog) grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border border-border bg-background p-6 text-foreground shadow-lg max-sm:top-auto max-sm:bottom-0 max-sm:left-0 max-sm:w-full max-sm:max-w-none max-sm:translate-x-0 max-sm:translate-y-0 max-sm:rounded-b-none" data-confirm-dialog @close-auto-focus="nextTick(() => emit('closed'))">
                <div class="flex gap-4">
                    <!-- The danger variant (the default) has an icon circle; `icon` replaces it, and a non-destructive confirm has none. -->
                    <slot name="icon"><span v-if="variant === 'danger'" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive" data-confirm-icon><TriangleAlert class="size-5" aria-hidden="true" /></span></slot>
                    <div class="grid min-w-0 gap-1">
                        <slot name="title"><AlertDialogTitle class="text-lg font-semibold">{{ title }}</AlertDialogTitle></slot>
                        <AlertDialogDescription v-if="description" class="text-sm text-muted-foreground">{{ description }}</AlertDialogDescription>
                        <slot name="body" />
                    </div>
                </div>
                <slot name="footer">
                    <div class="flex flex-wrap justify-end gap-2">
                        <AlertDialogCancel as-child><Button variant="secondary" type="button" data-cancel-action @click="emit('cancel')">{{ cancelLabel }}</Button></AlertDialogCancel>
                        <AlertDialogAction as-child><Button :variant="variant === 'danger' ? 'destructive' : 'primary'" type="button" data-confirm-action @click="confirm">{{ confirmLabel }}</Button></AlertDialogAction>
                    </div>
                </slot>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
