<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleCheck, Info, OctagonAlert, TriangleAlert, X } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, type Component } from 'vue';
import {
    alertIconClass,
    alertVariants,
    type AlertTier,
    type AlertVariant,
} from './alertVariants';

/*
 * An inline message inside the page's content: a callout about this page or this thing (not a toast, which is one-shot after an action, and not a page banner).
 *
 * `live` is the urgency: `none` (default) is a plain region for what is already on the page when it loads; `status` is a polite announcement for a message that
 * appears after load because of something the person did; `alert` is assertive and only for one that blocks what they are doing. A live alert mounts its wrapper
 * empty and fills it, so assistive technology announces it once.
 *
 * `dismiss` is a server descriptor ({ url, method }) and is only for a message the person can safely ignore; the server remembers it. Without one the alert is not
 * dismissible: it goes away when its condition is fixed. After dismissal the focus moves on, never to the body. Actions come from server descriptors in `#actions`.
 *
 * Alignment: the icon and the dismiss button each sit in a box one text line tall (`h-5`, text-sm's line height), centred in it, on an `items-start` row, so both
 * line up with the first line of text however many lines follow and whatever padding the host gives the alert. The title, body and actions share the text column.
 *
 * `actionsPlacement`: `below` (default) puts the actions under the body; `inline` puts them, from sm up, on the right of the title and body, vertically centred
 * beside them, and below them on a phone. Opt in for a short alert with one or two actions, such as a lock notice with Reopen.
 */
const props = withDefaults(
    defineProps<{
        variant?: AlertVariant;
        tier?: AlertTier;
        title?: string;
        icon?: boolean | Component;
        live?: 'none' | 'status' | 'alert';
        dismissible?: boolean;
        dismiss?: {
            url: string;
            method?: 'post' | 'put' | 'patch' | 'delete';
        } | null;
        actionsPlacement?: 'below' | 'inline';
    }>(),
    {
        variant: 'info',
        tier: 'pale',
        icon: true,
        live: 'none',
        dismissible: false,
        dismiss: null,
        actionsPlacement: 'below',
    },
);

defineOptions({ inheritAttrs: false });

const defaults: Record<AlertVariant, Component> = {
    info: Info,
    success: CircleCheck,
    warning: TriangleAlert,
    danger: OctagonAlert,
    primary: Info,
    brand: Info,
    muted: Info,
};
const glyph = computed<Component | null>(() =>
    props.icon === false
        ? null
        : props.icon === true
          ? defaults[props.variant]
          : props.icon,
);
const root = ref<HTMLElement | null>(null);
const gone = ref(false);
// A live region must exist before its text does, or it is not announced.
const filled = ref(props.live === 'none');
onMounted(async () => {
    await nextTick();
    filled.value = true;
});
const canDismiss = computed(() => props.dismissible && props.dismiss !== null);
const inline = computed(
    () => props.actionsPlacement === 'inline' && filled.value,
);
const dismissName = computed(() => `Dismiss ${props.title ?? props.variant}`);

function dismissed() {
    const next = (root.value?.nextElementSibling ??
        root.value?.parentElement) as HTMLElement | null;
    gone.value = true;
    if (props.dismiss)
        router.visit(props.dismiss.url, {
            method: props.dismiss.method ?? 'post',
            preserveScroll: true,
            preserveState: true,
        });
    nextTick(() => {
        if (!next) return;
        next.setAttribute('tabindex', '-1');
        next.focus({ preventScroll: true });
    });
}
</script>
<template>
    <div
        v-if="!gone"
        ref="root"
        v-bind="$attrs"
        :class="[alertVariants({ variant, tier }), 'self-start']"
        :role="live === 'none' ? (title ? 'region' : undefined) : live"
        :aria-label="live === 'none' && title ? title : undefined"
        :data-variant="variant"
        :data-tier="tier"
        data-slot="alert"
    >
        <span
            v-if="glyph"
            class="flex h-5 shrink-0 items-center"
            data-slot="alert-icon"
            ><component
                :is="glyph"
                class="size-5"
                :class="alertIconClass(variant, tier)"
                aria-hidden="true"
        /></span>
        <div v-if="!inline" class="min-w-0 flex-1 space-y-1">
            <template v-if="filled">
                <p v-if="title" class="font-semibold" data-slot="alert-title">
                    {{ title }}
                </p>
                <div data-slot="alert-body"><slot /></div>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center gap-3 pt-2 font-medium sm:justify-end"
                    data-slot="alert-actions"
                >
                    <slot name="actions" />
                </div>
            </template>
        </div>
        <div
            v-else
            class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4"
            data-actions-placement="inline"
        >
            <div class="min-w-0 flex-1 space-y-1">
                <p v-if="title" class="font-semibold" data-slot="alert-title">
                    {{ title }}
                </p>
                <div data-slot="alert-body"><slot /></div>
            </div>
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-3 font-medium"
                data-slot="alert-actions"
            >
                <slot name="actions" />
            </div>
        </div>
        <!-- The 44px target overhangs its one-line box evenly, so the × stays centred on the first line and reaches the alert's right edge. -->
        <span v-if="canDismiss" class="-mr-4 flex h-5 shrink-0 items-center">
            <button
                type="button"
                class="focus-visible:ring-ring flex size-11 items-center justify-center rounded-lg opacity-80 hover:opacity-100 focus-visible:ring-2 focus-visible:outline-none"
                :aria-label="dismissName"
                data-slot="alert-dismiss"
                @click="dismissed"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </span>
    </div>
</template>
