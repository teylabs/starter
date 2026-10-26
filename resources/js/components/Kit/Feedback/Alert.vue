<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleCheck, Info, OctagonAlert, TriangleAlert, X } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, type Component } from 'vue';
import { alertIconClass, alertVariants, type AlertTier, type AlertVariant } from './alertVariants';

/*
 * An inline message inside the page's content: a callout about this page or this thing (not a toast, which is one-shot after an action, and not a page banner).
 *
 * `live` is the urgency: `none` (default) is a plain region for what is already on the page when it loads; `status` is a polite announcement for a message that
 * appears after load because of something the person did; `alert` is assertive and only for one that blocks what they are doing. A live alert mounts its wrapper
 * empty and fills it, so assistive technology announces it once.
 *
 * `dismiss` is a server descriptor ({ url, method }) and is only for a message the person can safely ignore; the server remembers it. Without one the alert is not
 * dismissible: it goes away when its condition is fixed. After dismissal the focus moves on, never to the body. Actions come from server descriptors in `#actions`.
 */
const props = withDefaults(
    defineProps<{
        variant?: AlertVariant;
        tier?: AlertTier;
        title?: string;
        icon?: boolean | Component;
        live?: 'none' | 'status' | 'alert';
        dismissible?: boolean;
        dismiss?: { url: string; method?: 'post' | 'put' | 'patch' | 'delete' } | null;
    }>(),
    { variant: 'info', tier: 'pale', icon: true, live: 'none', dismissible: false, dismiss: null },
);

defineOptions({ inheritAttrs: false });

const defaults: Record<AlertVariant, Component> = { info: Info, success: CircleCheck, warning: TriangleAlert, danger: OctagonAlert, primary: Info, brand: Info, muted: Info };
const glyph = computed<Component | null>(() => (props.icon === false ? null : props.icon === true ? defaults[props.variant] : props.icon));
const root = ref<HTMLElement | null>(null);
const gone = ref(false);
// A live region must exist before its text does, or it is not announced.
const filled = ref(props.live === 'none');
onMounted(async () => {
    await nextTick();
    filled.value = true;
});
const canDismiss = computed(() => props.dismissible && props.dismiss !== null);
const dismissName = computed(() => `Dismiss ${props.title ?? props.variant}`);

function dismissed() {
    const next = (root.value?.nextElementSibling ?? root.value?.parentElement) as HTMLElement | null;
    gone.value = true;
    if (props.dismiss) router.visit(props.dismiss.url, { method: props.dismiss.method ?? 'post', preserveScroll: true, preserveState: true });
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
        :class="[alertVariants({ variant, tier }), canDismiss ? 'pr-14' : '']"
        :role="live === 'none' ? (title ? 'region' : undefined) : live"
        :aria-label="live === 'none' && title ? title : undefined"
        :data-variant="variant"
        :data-tier="tier"
        data-slot="alert"
    >
        <component :is="glyph" v-if="glyph" class="mt-0.5 size-5 shrink-0" :class="alertIconClass(variant, tier)" aria-hidden="true" />
        <div class="min-w-0 flex-1 space-y-1">
            <template v-if="filled">
                <p v-if="title" class="font-semibold" data-slot="alert-title">{{ title }}</p>
                <div data-slot="alert-body"><slot /></div>
                <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3 pt-2 font-medium sm:justify-end" data-slot="alert-actions"><slot name="actions" /></div>
            </template>
        </div>
        <button v-if="canDismiss" type="button" class="absolute top-0 right-0 flex size-11 items-center justify-center rounded-lg opacity-80 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" :aria-label="dismissName" data-slot="alert-dismiss" @click="dismissed">
            <X class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
