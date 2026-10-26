import type { InjectionKey } from 'vue';

export type PopoverWidth = 'auto' | 'sm' | 'md' | 'lg' | 'trigger';
export type PopoverDismiss = 'escape' | 'outside' | 'close' | 'navigate';
export interface PopoverMessages { close: string }

/** Set while a Popover renders its phone sheet, so a Popover opened inside it stays a popover and sheets never stack. */
export const sheetKey: InjectionKey<boolean> = Symbol('kit-popover-sheet');

/** Every width is capped at the viewport minus 2rem; `trigger` follows the anchor's width and is never narrower. */
export const popoverWidths: Record<PopoverWidth, string> = {
    auto: 'w-max max-w-[min(30rem,calc(100vw-2rem))]',
    sm: 'w-64 max-w-[calc(100vw-2rem)]',
    md: 'w-80 max-w-[calc(100vw-2rem)]',
    lg: 'w-120 max-w-[calc(100vw-2rem)]',
    trigger: 'w-(--reka-popover-trigger-width) min-w-(--reka-popover-trigger-width) max-w-[calc(100vw-2rem)]',
};

/** The strings for one use: the shared `popover` part of the kit's `ui` lang file, then `messages`. A string missing from both is its key, so a gap shows. */
export function popoverMessages(shared: unknown, overrides: Partial<PopoverMessages> = {}): PopoverMessages {
    const found = overrides.close ?? (shared && typeof shared === 'object' ? (shared as Record<string, unknown>).close : undefined);
    return { close: typeof found === 'string' ? found : 'popover.close' };
}
