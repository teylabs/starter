import type { ComputedRef, InjectionKey } from 'vue';
export type RepeaterRow = { id: string; [key: string]: unknown };
export const rowErrorsKey: InjectionKey<ComputedRef<Record<string, string>>> = Symbol('repeater-errors');
export const repeaterDepthKey: InjectionKey<number> = Symbol('repeater-depth');
export function fieldPath(name: string): string { return name.replace(/\[([^\]]+)\]/g, '.$1'); }
export function mappedErrors(errors: Record<string, string>, original: string, current: string): Record<string, string> {
    return Object.fromEntries(Object.entries(errors).filter(([key]) => key.startsWith(`${fieldPath(original)}.`)).map(([key, value]) => [current + key.slice(fieldPath(original).length).replace(/\.([^.[\]]+)/g, '[$1]'), value]));
}

/** Preserve native uncontrolled edits while Vue patches names and error slots. Hidden
 * canonical controls remain owned by their components and are never restored here. */
export function preserveNativeEdits(root?: HTMLElement): () => void {
    const restores: (() => void)[] = [];
    root?.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>('input[name]:not([type=hidden]):not([type=file]),textarea[name],select[name]').forEach(control => {
        if (control instanceof HTMLSelectElement) {
            const selected = new Set(Array.from(control.selectedOptions).map(option => option.value));
            restores.push(() => { if (control.isConnected) Array.from(control.options).forEach(option => { option.selected = selected.has(option.value); }); });
        } else {
            const value = control.value;
            const checked = control instanceof HTMLInputElement ? control.checked : undefined;
            const start = control.selectionStart, end = control.selectionEnd;
            restores.push(() => {
                if (!control.isConnected) return;
                control.value = value;
                if (checked !== undefined && control instanceof HTMLInputElement) control.checked = checked;
                if (start !== null && end !== null && document.activeElement === control) control.setSelectionRange(start, end);
            });
        }
    });
    return () => restores.forEach(restore => restore());
}
