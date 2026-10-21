<script setup lang="ts">
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, DialogTrigger } from 'reka-ui';

// A modal dialog on Reka's Dialog, for a form that does not deserve its own page (invite someone, change a role): `title` names it, the default slot is the
// body (a Form with its FormActions, usually) and the optional `trigger` slot is the button that opens it. Without a trigger it is opened with `v-model:open`.
// Destructive confirmations are ConfirmDialog, not this.
withDefaults(defineProps<{ title: string; description?: string }>(), { description: undefined });
const open = defineModel<boolean>('open', { default: false });
</script>
<template>
    <DialogRoot v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child><slot name="trigger" /></DialogTrigger>
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-50 bg-black/50" />
            <DialogContent class="fixed top-1/2 left-1/2 z-50 grid max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 overflow-y-auto rounded-lg border border-border bg-background p-6 text-foreground shadow-lg" data-dialog>
                <DialogTitle class="text-lg font-semibold">{{ title }}</DialogTitle>
                <DialogDescription v-if="description" class="text-sm text-muted-foreground">{{ description }}</DialogDescription>
                <slot />
                <DialogClose v-if="$slots.close" as-child><slot name="close" /></DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
