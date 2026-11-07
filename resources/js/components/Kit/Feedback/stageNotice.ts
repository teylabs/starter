import { alertVariants, type AlertTier } from './alertVariants';

/*
 * StageNotice's placement and tone, without Vue: where the notice sits inside its stage, and its colours from the shared alert variants (no colour of its own).
 * Sizes are container-query units (cqw), so the notice scales with the stage it is laid over (a slide on a projector), not with the viewport.
 */
export type StageNoticeTone = 'info' | 'success' | 'warning' | 'danger';
export type StageNoticePlacement =
    | 'top'
    | 'bottom'
    | 'top-left'
    | 'top-right'
    | 'bottom-left'
    | 'bottom-right';

export const STAGE_NOTICE_TONES: StageNoticeTone[] = [
    'info',
    'success',
    'warning',
    'danger',
];
export const STAGE_NOTICE_PLACEMENTS: StageNoticePlacement[] = [
    'top',
    'bottom',
    'top-left',
    'top-right',
    'bottom-left',
    'bottom-right',
];

// Literal class names, so the host's Tailwind finds them. The inset is the stage's own (--stage-notice-inset, 3cqw by default).
const POSITIONS: Record<StageNoticePlacement, string> = {
    top: 'top-[var(--stage-notice-inset,3cqw)] left-1/2 -translate-x-1/2',
    bottom: 'bottom-[var(--stage-notice-inset,3cqw)] left-1/2 -translate-x-1/2',
    'top-left':
        'top-[var(--stage-notice-inset,3cqw)] left-[var(--stage-notice-inset,3cqw)]',
    'top-right':
        'top-[var(--stage-notice-inset,3cqw)] right-[var(--stage-notice-inset,3cqw)]',
    'bottom-left':
        'bottom-[var(--stage-notice-inset,3cqw)] left-[var(--stage-notice-inset,3cqw)]',
    'bottom-right':
        'bottom-[var(--stage-notice-inset,3cqw)] right-[var(--stage-notice-inset,3cqw)]',
};

// Where it enters from: down from a top placement, up from a bottom one (reduced motion: it only fades, see StageNotice.vue).
const ENTER_FROM: Record<StageNoticePlacement, string> = {
    top: 'motion-safe:-translate-y-[1cqw]',
    bottom: 'motion-safe:translate-y-[1cqw]',
    'top-left': 'motion-safe:-translate-y-[1cqw]',
    'top-right': 'motion-safe:-translate-y-[1cqw]',
    'bottom-left': 'motion-safe:translate-y-[1cqw]',
    'bottom-right': 'motion-safe:translate-y-[1cqw]',
};

/** The notice's classes: its place in the stage, the tone's colours (an unknown placement or tone falls back to top and info). */
export function stageNoticeClass(
    tone: StageNoticeTone = 'info',
    placement: StageNoticePlacement = 'top',
    tier: AlertTier = 'solid',
): string {
    const at = POSITIONS[placement] ?? POSITIONS.top;
    const colour = alertVariants({
        variant: STAGE_NOTICE_TONES.includes(tone) ? tone : 'info',
        tier,
    });
    return `${at} ${colour}`;
}

/** The transition's starting offset for a placement (the side it slides in from). */
export function stageNoticeEnterFrom(
    placement: StageNoticePlacement = 'top',
): string {
    return ENTER_FROM[placement] ?? ENTER_FROM.top;
}
