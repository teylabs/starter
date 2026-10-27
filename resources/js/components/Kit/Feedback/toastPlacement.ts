/*
 * Where the shell's Toaster sits and how a flashed toast meets its trigger. INTERNAL to Toaster; pure, so it is tested without a browser.
 *
 * Position: the shell's `kit.toasts.position` (shared as `kitToasts`): auto, top-center, top-right, bottom-right or bottom-center; an unknown value is auto.
 * Below `sm` it is always bottom-center, lifted above the registered sticky bars (the safe-area inset is added in CSS). Otherwise `auto` is top-center when
 * sticky bottom bars are registered and the shell header has right-aligned actions, top-right with sticky bars only, else bottom-right. A top position sits
 * just under the shell's header and breadcrumb bar, never over it.
 *
 * Trigger: the server names it by action-descriptor key (`from`), and rendered descriptor buttons carry that key as `data-action`. The last activated
 * element with that key wins (a row's own button among many rows), else the only one on the page. Anything else, a gone or offscreen element, is no trigger.
 */
export type ToastPosition = 'auto' | 'top-center' | 'top-right' | 'bottom-right' | 'bottom-center';
export type ResolvedPosition = Exclude<ToastPosition, 'auto'>;
export type ToastPlacement = { position: ResolvedPosition; offset: number };
export type ToastStyle = 'plain' | 'fly-out' | 'anchored' | 'pulse';
export type ShellState = { bars: { count: number; height: number }; small: boolean; headerActions: boolean; headerHeight: number };

const GAP = 16;
const POSITIONS: ResolvedPosition[] = ['top-center', 'top-right', 'bottom-right', 'bottom-center'];

export function resolvePosition(position: string | null | undefined, shell: ShellState): ResolvedPosition {
    if (shell.small) return 'bottom-center';
    if (POSITIONS.includes(position as ResolvedPosition)) return position as ResolvedPosition;
    if (shell.bars.count > 0) return shell.headerActions ? 'top-center' : 'top-right';
    return 'bottom-right';
}

export function toastPlacement(position: string | null | undefined, shell: ShellState): ToastPlacement {
    const resolved = resolvePosition(position, shell);
    if (resolved.startsWith('top-')) return { position: resolved, offset: Math.max(0, shell.headerHeight) + GAP };
    return { position: resolved, offset: GAP + (shell.bars.count > 0 ? Math.max(0, shell.bars.height) + GAP / 2 : 0) };
}

export type FlashedToast = { type: 'success' | 'info' | 'warning' | 'error'; message: string; description?: string; action?: { label: string; href: string; method?: 'get' | 'post' | 'put' | 'patch' }; from?: string; style?: ToastStyle; duration?: number };

// A flashed `toast` the Toaster can render, or null: it renders what the server sent and invents nothing.
export function flashedToast(value: unknown): FlashedToast | null {
    if (!value || typeof value !== 'object') return null;
    const toast = value as Record<string, unknown>;
    if (!['success', 'info', 'warning', 'error'].includes(toast.type as string) || typeof toast.message !== 'string' || toast.message === '') return null;
    return toast as FlashedToast;
}

type Box = { top: number; left: number; bottom: number; right: number; width: number; height: number };
export type TriggerLike = { isConnected: boolean; getAttribute(name: string): string | null; getBoundingClientRect(): Box };

// The trigger element for `from`, or null for a plain toast.
export function resolveTrigger<T extends TriggerLike>(from: string | undefined, candidates: T[], last: T | null, viewport: { width: number; height: number }): T | null {
    if (!from) return null;
    const live = candidates.filter((el) => el.isConnected && el.getAttribute('data-action') === from);
    const chosen = last && last.isConnected && last.getAttribute('data-action') === from ? last : live.length === 1 ? live[0] : null;
    if (!chosen) return null;
    const box = chosen.getBoundingClientRect();
    const visible = box.width > 0 && box.height > 0 && box.bottom > 0 && box.right > 0 && box.top < viewport.height && box.left < viewport.width;
    return visible ? chosen : null;
}

export type ToastPlan = { shell: boolean; flight: boolean; anchored: boolean; pulse: boolean; animate: boolean };

// What one toast does, from its style, whether its trigger resolved and the viewer's motion preference. No trigger is always a plain shell toast; reduced
// motion never flies (a fly-out becomes a toast and a pulse) and the anchored bubble appears without animating.
export function toastPlan(style: ToastStyle | undefined, resolved: boolean, reducedMotion: boolean): ToastPlan {
    const plain = { shell: true, flight: false, anchored: false, pulse: false, animate: !reducedMotion };
    if (!resolved) return plain;
    switch (style) {
        case 'fly-out':
            return reducedMotion ? { ...plain, pulse: true } : { ...plain, flight: true };
        case 'anchored':
            return { shell: false, flight: false, anchored: true, pulse: false, animate: !reducedMotion };
        case 'pulse':
            return { ...plain, pulse: true };
        default:
            return plain;
    }
}
