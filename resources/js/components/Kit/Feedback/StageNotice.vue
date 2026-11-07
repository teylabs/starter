<script setup lang="ts">
import { computed } from 'vue';
import type { AlertTier } from './alertVariants';
import {
    stageNoticeClass,
    stageNoticeEnterFrom,
    type StageNoticePlacement,
    type StageNoticeTone,
} from './stageNotice';

/*
 * An announcement laid over a stage (a slide, a canvas, a projected screen): "Live demo: Billing", "Recording", "Back in 5 minutes". It sits inside its container
 * (the stage needs `relative` and a container, e.g. `relative @container`), sized in container-query units so it scales with the stage, and lets every pointer
 * event through (`pointer-events-none`): the stage underneath stays usable. The content is the default slot.
 *
 * `tone` is the shared alert colour (info, success, warning, danger), `tier` solid by default (read across a room) or pale; `placement` is top, bottom or a corner.
 * `show` toggles it with a short fade and slide; with reduced motion it appears and goes without moving. `live` announces a change politely (`polite`, the
 * default) or not at all (`off`, for a notice that is only for the room). Sizes come from custom properties a host can set on the stage: `--stage-notice-size`
 * (the text, 2.5cqw) and `--stage-notice-inset` (the distance from the edges, 3cqw).
 */
const props = withDefaults(
    defineProps<{
        tone?: StageNoticeTone;
        placement?: StageNoticePlacement;
        tier?: AlertTier;
        show?: boolean;
        live?: 'polite' | 'off';
    }>(),
    {
        tone: 'info',
        placement: 'top',
        tier: 'solid',
        show: true,
        live: 'polite',
    },
);
const classes = computed(() =>
    stageNoticeClass(props.tone, props.placement, props.tier),
);
const enterFrom = computed(
    () => `motion-safe:opacity-0 ${stageNoticeEnterFrom(props.placement)}`,
);
</script>
<template>
    <Transition
        enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-out"
        :enter-from-class="enterFrom"
        leave-active-class="motion-safe:transition motion-safe:duration-200 motion-safe:ease-in"
        :leave-to-class="enterFrom"
    >
        <div
            v-if="show"
            :role="live === 'polite' ? 'status' : undefined"
            :aria-live="live === 'polite' ? 'polite' : undefined"
            :class="[
                'pointer-events-none absolute z-10 flex w-max max-w-[90cqw] items-center gap-[1cqw] rounded-[1cqw] px-[2cqw] py-[1cqw] text-[length:var(--stage-notice-size,2.5cqw)] leading-tight font-semibold shadow-lg select-none',
                classes,
            ]"
            :data-tone="tone"
            :data-placement="placement"
            data-stage-notice
        >
            <slot />
        </div>
    </Transition>
</template>
