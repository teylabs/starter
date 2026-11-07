import { inject, provide, type InjectionKey } from 'vue';

// Whether a Button sits in a form that submits it: FormCard provides it (Inertia's <Form> is read directly, see Button), so a Button there is a submit by default.
const key: InjectionKey<boolean> = Symbol('kit.form-submit');

export function provideFormSubmit(): void {
    provide(key, true);
}

export function insideFormSubmit(): boolean {
    return inject(key, false);
}
