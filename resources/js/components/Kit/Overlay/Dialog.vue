<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, useId, watch } from 'vue';
import { TriangleAlert } from '@lucide/vue';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
    AlertDialogTrigger,
} from 'reka-ui';
import { useLayerTarget } from '@/components/Kit/Overlay/layer';
import SurfaceReset from '@/components/Kit/Surface/SurfaceReset.vue';
import Button from '@/components/Kit/Form/Button.vue';
import { Input as BaseInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmationData, typedMatches } from './confirmText';

// The kit's Dialog: a confirmation or a prompt (a title, a message, actions, and optionally a prompt control in the `body` slot), on Reka's AlertDialog. It is static: Escape and the backdrop do nothing but cancel, and it never holds a form (that is <Modal>). `variant="danger"` draws an icon circle and a destructive action (`:icon="false"` drops the circle and its gutter, on any variant, and keeps the action's style); the default, neutral, does not. `action` (a server action: url and method) is performed by the dialog, which closes when it succeeds; without it `@confirm` is for local handling. Declared in the template, opened with `v-model:open` or by its trigger (the default slot, the button that asks); there is no programmatic confirm(). On a phone it is bottom-aligned.
// Behaviour: the trigger is the default slot (the button that asks), Cancel is
// focused first, and the action runs only on `confirm`. Without a trigger slot it is opened with `v-model:open`. Slots: `icon`, `title`, `body` (content above the buttons, such as a password to confirm with) and `footer`. A destructive confirm (the default) has an icon circle; on a phone it is bottom-aligned.
// Typed confirmation: with `confirmText`, a labelled input asks for that exact text (case-sensitive unless `caseSensitive` is false; pasting is fine) and Confirm stays
// disabled until it matches; the change is announced politely. The typed text is emitted with `confirm` and sent as `confirmation` with the `action` visit, so
// the server re-checks it (TypedConfirmation): the disabled button alone is never the guard.
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'danger' | 'neutral';
        action?: { url: string; method: string };
        confirmText?: string | null;
        caseSensitive?: boolean;
        confirmTextLabel?: string;
        icon?: boolean;
    }>(),
    {
        description: undefined,
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        variant: 'neutral',
        confirmText: null,
        caseSensitive: true,
        confirmTextLabel: undefined,
        icon: true,
    },
);
const open = defineModel<boolean>('open', { default: false });
// `closed` fires once the dialog has closed and handed focus back, so a caller that closes something more (a modal under it) waits for it.
const emit = defineEmits<{
    confirm: [confirmation?: string];
    cancel: [];
    closed: [];
}>();
const typed = ref('');
const inputId = `confirm-text-${useId()}`;
const asksTyped = computed(
    () => typeof props.confirmText === 'string' && props.confirmText !== '',
);
const matches = computed(
    () =>
        !asksTyped.value ||
        typedMatches(typed.value, props.confirmText ?? '', props.caseSensitive),
);
// Each opening starts empty: a match from last time never carries over.
watch(open, (now) => {
    if (now) typed.value = '';
});
function confirm() {
    if (!matches.value) return;
    const confirmation = asksTyped.value ? typed.value.trim() : undefined;
    emit('confirm', confirmation);
    if (props.action) {
        router.visit(props.action.url, {
            method: props.action.method as 'post' | 'put' | 'patch' | 'delete',
            data: confirmationData(confirmation),
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        });
    }
}
// Inside a Modal its overlay renders into the Modal's <dialog>, over it (layer.ts).
const layerTarget = useLayerTarget();
</script>
<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogTrigger v-if="$slots.default" as-child
            ><slot
        /></AlertDialogTrigger>
        <AlertDialogPortal :to="layerTarget"
            ><SurfaceReset>
                <AlertDialogOverlay
                    class="bg-scrim fixed inset-0 z-(--z-dialog)"
                />
                <AlertDialogContent
                    class="border-border bg-background text-foreground fixed top-1/2 left-1/2 z-(--z-dialog) grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border p-6 shadow-lg max-sm:top-auto max-sm:bottom-0 max-sm:left-0 max-sm:w-full max-sm:max-w-none max-sm:translate-x-0 max-sm:translate-y-0 max-sm:rounded-b-none"
                    data-confirm-dialog
                    @close-auto-focus="nextTick(() => emit('closed'))"
                >
                    <div
                        :class="icon ? 'flex gap-4' : 'grid gap-4'"
                        :data-confirm-flush="icon ? undefined : ''"
                    >
                        <!-- The danger variant (the default) has an icon circle; the `icon` slot replaces it, and a non-destructive confirm has none. `:icon="false"` hides it on any variant and the content sits flush. -->
                        <slot v-if="icon" name="icon"
                            ><span
                                v-if="variant === 'danger'"
                                class="bg-destructive/10 text-destructive-text flex size-10 shrink-0 items-center justify-center rounded-full"
                                data-confirm-icon
                                ><TriangleAlert
                                    class="size-5"
                                    aria-hidden="true" /></span
                        ></slot>
                        <div class="grid min-w-0 gap-1">
                            <slot name="title"
                                ><AlertDialogTitle
                                    class="text-lg font-semibold break-words"
                                    >{{ title }}</AlertDialogTitle
                                ></slot
                            >
                            <AlertDialogDescription
                                v-if="description"
                                class="text-muted-foreground text-sm break-words"
                                >{{ description }}</AlertDialogDescription
                            >
                            <div
                                v-if="asksTyped"
                                class="grid gap-2 pt-2"
                                data-confirm-text
                            >
                                <Label :for="inputId">{{
                                    confirmTextLabel ??
                                    `Type ${confirmText} to confirm`
                                }}</Label>
                                <BaseInput
                                    :id="inputId"
                                    v-model="typed"
                                    type="text"
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    :aria-describedby="`${inputId}-hint`"
                                    data-confirm-text-input
                                />
                                <p
                                    :id="`${inputId}-hint`"
                                    class="text-muted-foreground text-xs"
                                >
                                    {{ confirmLabel }} stays off until it
                                    matches{{
                                        caseSensitive
                                            ? ', including capitals'
                                            : ''
                                    }}.
                                </p>
                                <p role="status" class="sr-only">
                                    {{
                                        matches
                                            ? `It matches. ${confirmLabel} is on.`
                                            : ''
                                    }}
                                </p>
                            </div>
                            <slot name="body" />
                        </div>
                    </div>
                    <slot name="footer">
                        <div class="flex flex-wrap justify-end gap-2">
                            <AlertDialogCancel as-child
                                ><Button
                                    variant="secondary"
                                    type="button"
                                    data-cancel-action
                                    @click="emit('cancel')"
                                    >{{ cancelLabel }}</Button
                                ></AlertDialogCancel
                            >
                            <AlertDialogAction as-child
                                ><Button
                                    :variant="
                                        variant === 'danger'
                                            ? 'destructive'
                                            : 'primary'
                                    "
                                    type="button"
                                    :disabled="!matches"
                                    :aria-describedby="
                                        asksTyped
                                            ? `${inputId}-hint`
                                            : undefined
                                    "
                                    data-confirm-action
                                    @click="confirm"
                                    >{{ confirmLabel }}</Button
                                ></AlertDialogAction
                            >
                        </div>
                    </slot>
                </AlertDialogContent>
            </SurfaceReset></AlertDialogPortal
        >
    </AlertDialogRoot>
</template>
