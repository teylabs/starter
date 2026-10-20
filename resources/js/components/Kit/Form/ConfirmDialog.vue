<script setup lang="ts">
import { AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogOverlay, AlertDialogPortal, AlertDialogRoot, AlertDialogTitle, AlertDialogTrigger } from 'reka-ui';
import Button from './Button.vue';

// The confirmation every destructive action goes through, on Reka's AlertDialog: the trigger is the default slot (the button that asks), Cancel is
// focused first, and the action runs only on `confirm`. Without a trigger slot it is opened with `v-model:open`.
withDefaults(defineProps<{ title: string; description?: string; confirmLabel?: string; cancelLabel?: string; destructive?: boolean }>(), { description: undefined, confirmLabel: 'Confirm', cancelLabel: 'Cancel', destructive: true });
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: []; cancel: [] }>();
</script>
<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogTrigger v-if="$slots.default" as-child><slot /></AlertDialogTrigger>
        <AlertDialogPortal>
            <AlertDialogOverlay class="fixed inset-0 z-50 bg-black/50" />
            <AlertDialogContent class="fixed top-1/2 left-1/2 z-50 grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border border-border bg-background p-6 text-foreground shadow-lg" data-confirm-dialog>
                <AlertDialogTitle class="text-lg font-semibold">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="description" class="text-sm text-muted-foreground">{{ description }}</AlertDialogDescription>
                <div class="flex flex-wrap justify-end gap-2">
                    <AlertDialogCancel as-child><Button variant="secondary" type="button" @click="emit('cancel')">{{ cancelLabel }}</Button></AlertDialogCancel>
                    <AlertDialogAction as-child><Button :variant="destructive ? 'destructive' : 'primary'" type="button" data-confirm-action @click="emit('confirm')">{{ confirmLabel }}</Button></AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
