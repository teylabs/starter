import { useFormContext } from '@inertiajs/vue3';
import { getCurrentInstance, inject } from 'vue';

type FormContext = NonNullable<
    ReturnType<typeof useFormContext<Record<string, any>>>
>;

/*
 * The enclosing Inertia `<Form>`'s context, or null outside one, with no warning. Inertia's useFormContext injects without a default, so a kit field or
 * Repeater used standalone logged "injection InertiaFormContext not found" on every render. Inertia doesn't export its key; it provides a Symbol described
 * "InertiaFormContext", so this finds that Symbol on the component's provide chain and injects it with a null default. No provider: null, nothing injected.
 * Call from setup.
 */
export function useOptionalFormContext(): FormContext | null {
    const instance = getCurrentInstance() as {
        parent?: { provides?: object } | null;
        provides?: object;
    } | null;
    for (
        let provides: object | null =
            instance?.parent?.provides ?? instance?.provides ?? null;
        provides;
        provides = Object.getPrototypeOf(provides)
    ) {
        const key = Object.getOwnPropertySymbols(provides).find(
            (symbol) => symbol.description === 'InertiaFormContext',
        );
        if (key) return inject(key, null) as FormContext | null;
        if (Object.getPrototypeOf(provides) === Object.prototype) break;
    }
    return null;
}
