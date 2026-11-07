<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Toaster as Sonner, toast } from 'vue-sonner';
import 'vue-sonner/style.css';
import { useStickyBars } from '@/components/Kit/Layout/stickyBars';
import { useTopLayer } from '@/components/Kit/Overlay/layer';
import {
    flashedToast,
    isAway,
    resolveTrigger,
    toastGate,
    toastPlacement,
    toastPlan,
    toastTiming,
    whenBack,
    type FlashedToast,
} from './toastPlacement';

/*
 * The app's one toast renderer, mounted once by the shell (Workspace Layout, GuestShell). It renders the `toast` a controller flashed (Tey\Kit\Features\Core\Toast) and decides
 * nothing: pages never call it, and there is no page-level toast API. Where it sits is the shell's (`kitToasts.position`, resolved by toastPlacement); below `sm`
 * it is bottom-center, above the registered sticky bars (useStickyBars) and the safe-area inset. An action is one link or request.
 * A toast `from` an action key meets its trigger (the rendered descriptor's `data-action`): fly-out animates from the trigger into the resolved position, anchored
 * shows a short "✓ message" bubble on the trigger instead of a shell toast, pulse rings the trigger beside a shell toast. No trigger found: a plain toast.
 * Reduced motion never flies: a fly-out is a toast and a pulse, and the bubble appears without animating.
 * How long it stays is the server's (`duration`, from kit.toasts.durations; null until dismissed). Shown while the viewer is away (tab hidden, window unfocused), a timed
 * toast waits: its duration starts when they are back.
 */
const page = usePage<{ kitToasts?: { position?: string } }>();
const bars = useStickyBars();
const small = ref(false);
const headerActions = ref(false);
const headerHeight = ref(0);
const placement = computed(() =>
    toastPlacement(page.props.kitToasts?.position, {
        bars: { count: bars.count.value, height: bars.height.value },
        small: small.value,
        headerActions: headerActions.value,
        headerHeight: headerHeight.value,
    }),
);
const offset = computed(() =>
    placement.value.position.startsWith('top-')
        ? { top: `${placement.value.offset}px` }
        : {
              bottom: `calc(${placement.value.offset}px + env(safe-area-inset-bottom))`,
          },
);
const bubble = ref<{
    message: string;
    top: number;
    left: number;
    animate: boolean;
} | null>(null);
let bubbleTimer: ReturnType<typeof setTimeout> | undefined;
let lastTrigger: HTMLElement | null = null;
const waiting = new Set<() => void>();
const timers = new Set<ReturnType<typeof setTimeout>>();

function measureShell(): void {
    const top = document.querySelector<HTMLElement>('[data-shell-top]');
    headerHeight.value = top ? top.getBoundingClientRect().height : 0;
    headerActions.value = !!document.querySelector('[data-shell-actions] > *');
}

function trigger(from: string | undefined): HTMLElement | null {
    return resolveTrigger(
        from,
        [...document.querySelectorAll<HTMLElement>('[data-action]')],
        lastTrigger,
        { width: window.innerWidth, height: window.innerHeight },
    );
}

function pulse(el: HTMLElement): void {
    el.animate?.(
        [
            {
                boxShadow:
                    '0 0 0 0 color-mix(in oklab, var(--color-primary) 55%, transparent)',
            },
            { boxShadow: '0 0 0 8px transparent' },
        ],
        { duration: 700, easing: 'ease-out' },
    );
}

// FLIP: the newest shell toast starts over the trigger and settles where it is.
function fly(from: HTMLElement): void {
    requestAnimationFrame(() => {
        const to = document.querySelector<HTMLElement>(
            '[data-kit-toaster] [data-sonner-toast][data-front="true"]',
        );
        if (!to) return;
        const a = from.getBoundingClientRect();
        const b = to.getBoundingClientRect();
        to.animate?.(
            [
                {
                    transform: `translate(${a.left - b.left}px, ${a.top - b.top}px) scale(${Math.max(0.2, a.width / b.width)}, ${Math.max(0.2, a.height / b.height)})`,
                    opacity: 0.4,
                },
                { transform: 'none', opacity: 1 },
            ],
            { duration: 300, easing: 'cubic-bezier(0.2, 0, 0, 1)' },
        );
    });
}

// The first page's flash arrives both on the page and as the router's first `flash` event (an equal copy): one toast per server id, not two.
const fresh = toastGate();
async function show(value: unknown): Promise<void> {
    if (!fresh(value)) return;
    const flashed: FlashedToast | null = flashedToast(value);
    if (!flashed) return;
    // Headless history: the app can record every shown toast (a notification bell, say) without the kit owning one.
    window.dispatchEvent(new CustomEvent('kit:toast', { detail: flashed }));
    measureShell();
    const el = trigger(flashed.from);
    const plan = toastPlan(
        flashed.style,
        el !== null,
        window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );
    if (plan.anchored && el) {
        const box = el.getBoundingClientRect();
        clearTimeout(bubbleTimer);
        bubble.value = {
            message: flashed.message,
            top: box.top - 8,
            left: box.left + box.width / 2,
            animate: plan.animate,
        };
        bubbleTimer = setTimeout(() => (bubble.value = null), 2500);
        return;
    }
    const action = flashed.action;
    const timing = toastTiming(flashed.duration, isAway(document));
    const id = toast[flashed.type](flashed.message, {
        description: flashed.description,
        duration: timing.duration,
        action: action
            ? {
                  label: action.label,
                  onClick: () =>
                      router.visit(action.href, {
                          method: action.method ?? 'get',
                      }),
              }
            : undefined,
    });
    if (timing.deferred !== null) {
        const ms = timing.deferred;
        const cancel = whenBack(document, window, () => {
            waiting.delete(cancel);
            const timer = setTimeout(() => {
                timers.delete(timer);
                toast.dismiss(id);
            }, ms);
            timers.add(timer);
        });
        waiting.add(cancel);
    }
    await nextTick();
    if (el && plan.flight) fly(el);
    if (el && plan.pulse) pulse(el);
}

let media: MediaQueryList | undefined;
const onMedia = () => (small.value = !!media?.matches);
const onActivate = (event: Event) =>
    (lastTrigger =
        (event.target as Element | null)?.closest<HTMLElement>(
            '[data-action]',
        ) ?? lastTrigger);
let stop: (() => void) | undefined;
// A kit component that acts without a page visit (the Workbench's refused reorder) asks through `kit:toast:show`; it renders like a flash. Pages never send it.
const onClientToast = (event: Event) =>
    show((event as CustomEvent<unknown>).detail);
onMounted(() => {
    window.addEventListener('kit:toast:show', onClientToast);
    media = window.matchMedia('(max-width: 39.99rem)');
    onMedia();
    media.addEventListener('change', onMedia);
    document.addEventListener('pointerdown', onActivate, true);
    document.addEventListener('keydown', onActivate, true);
    measureShell();
    show((page as unknown as { flash?: Record<string, unknown> }).flash?.toast);
    stop = router.on(
        'flash',
        (event: CustomEvent<{ flash?: Record<string, unknown> }>) =>
            show(event.detail.flash?.toast),
    );
});
onBeforeUnmount(() => {
    window.removeEventListener('kit:toast:show', onClientToast);
    media?.removeEventListener('change', onMedia);
    document.removeEventListener('pointerdown', onActivate, true);
    document.removeEventListener('keydown', onActivate, true);
    clearTimeout(bubbleTimer);
    waiting.forEach((cancel) => cancel());
    timers.forEach((timer) => clearTimeout(timer));
    stop?.();
});
// While a Modal is open (a native <dialog> in the top layer), the toasts move into it, or a toast raised from the Modal would show under it (layer.ts).
const layer = useTopLayer();
</script>
<template>
    <Teleport :to="layer" :disabled="layer === 'body'">
        <Sonner
            :position="placement.position"
            :offset="offset"
            :mobile-offset="offset"
            close-button
            rich-colors
            data-kit-toaster
            :data-position="placement.position"
        />
        <div
            v-if="bubble"
            role="status"
            :class="[
                'bg-foreground text-background pointer-events-none fixed top-(--kit-toast-top) left-(--kit-toast-left) z-50 inline-flex -translate-x-1/2 -translate-y-full items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium shadow-md',
                bubble.animate &&
                    'motion-safe:animate-in motion-safe:fade-in motion-safe:zoom-in-95',
            ]"
            :style="{
                '--kit-toast-top': `${bubble.top}px`,
                '--kit-toast-left': `${bubble.left}px`,
            }"
            data-kit-toast-anchored
        >
            <Check class="size-3.5" aria-hidden="true" />{{ bubble.message }}
        </div>
    </Teleport>
</template>
