import { computed, type ComputedRef, inject, type InjectionKey } from 'vue';

/*
 * What a <Field> tells the control inside it: the ids of the description and error it actually renders, and whether it has an error. Every Kit/Form control reads
 * it through useFieldAria, so `aria-describedby` names only nodes that exist (none: no attribute) and `aria-invalid="true"` is set while there is an error.
 */
export type FieldAria = {
    id: string;
    describedBy: ComputedRef<string[]>;
    invalid: ComputedRef<boolean>;
};
export const FIELD_ARIA: InjectionKey<FieldAria> = Symbol('kit.field');

/**
 * The control's aria-describedby and aria-invalid, merged with what the page passed. Inside a Field, the field's own `<id>-description` and `<id>-error` are
 * kept only while rendered (a page that names them always, the old pattern, no longer dangles); any other id the page names is kept. `invalid` is the control's
 * own invalid state, if it has one.
 */
export function useFieldAria(
    attrs: Record<string, unknown>,
    invalid?: () => boolean | undefined,
): ComputedRef<{
    'aria-describedby': string | undefined;
    'aria-invalid': 'true' | undefined;
}> {
    const field = inject(FIELD_ARIA, null);
    return computed(() => {
        const passed = String(attrs['aria-describedby'] ?? '')
            .split(/\s+/)
            .filter(Boolean);
        const own = field
            ? [`${field.id}-description`, `${field.id}-error`]
            : [];
        const ids = [
            ...new Set([
                ...passed.filter((id) => !own.includes(id)),
                ...(field?.describedBy.value ?? []),
            ]),
        ];
        const passedInvalid =
            attrs['aria-invalid'] === true || attrs['aria-invalid'] === 'true';
        return {
            'aria-describedby': ids.length ? ids.join(' ') : undefined,
            'aria-invalid':
                field?.invalid.value || invalid?.() || passedInvalid
                    ? 'true'
                    : undefined,
        };
    });
}
