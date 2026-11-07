<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Alert from '@/components/Kit/Feedback/Alert.vue';
import type { AlertVariant } from '@/components/Kit/Feedback/alertVariants';

/*
 * A form's persistent confirmation after a submit ("we emailed you a link", "you're on the list"): an Alert with `live="status"` (a polite announcement), not a toast,
 * so it never times out. The text is `message` when given (null shows nothing), else `page.flash[flashKey]` when `flashKey` is given (Inertia v3's page flash),
 * else the `status` page prop (Fortify's convention, through the view model).
 *
 * Visibility is state, not event order: it shows when there is a text and no visit is in flight, where in flight is `form.processing` (a useForm() result) or the
 * router between `start` and `finish`. So it returns after a visit only when the new page still carries a message. Rendering the Alert afresh each time makes the
 * live region announce it again. Nothing when empty.
 */
type FormStatusTone = 'success' | 'info' | 'warning';

const props = withDefaults(
    defineProps<{
        message?: string | null;
        flashKey?: string;
        tone?: FormStatusTone;
        form?: { processing: boolean } | null;
    }>(),
    { message: undefined, flashKey: undefined, tone: 'success', form: null },
);

const variants: Record<FormStatusTone, AlertVariant> = {
    success: 'success',
    info: 'info',
    warning: 'warning',
};
const variant = computed<AlertVariant>(() => variants[props.tone] ?? 'success');

const page = usePage();
const text = computed(() => {
    const value =
        props.message !== undefined
            ? props.message
            : props.flashKey !== undefined
              ? (page.flash as Record<string, unknown> | undefined)?.[
                    props.flashKey
                ]
              : (page.props as { status?: unknown }).status;
    return typeof value === 'string' && value.trim() !== '' ? value : null;
});

const navigating = ref(false);
const inFlight = computed(
    () => navigating.value || props.form?.processing === true,
);
const visible = computed(() => text.value !== null && !inFlight.value);
let offStart: (() => void) | undefined;
let offFinish: (() => void) | undefined;
onMounted(() => {
    offStart = router.on('start', () => {
        navigating.value = true;
    });
    offFinish = router.on('finish', () => {
        navigating.value = false;
    });
});
onBeforeUnmount(() => {
    offStart?.();
    offFinish?.();
});
</script>
<template>
    <Alert
        v-if="visible"
        :variant="variant"
        live="status"
        data-slot="form-status"
        >{{ text }}</Alert
    >
</template>
