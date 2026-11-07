import {
    computed,
    inject,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    type Ref,
} from 'vue';

/*
 * Where kit overlays render. The kit's Modal is the package's native <dialog>, opened with showModal(): it sits in the browser's top layer and makes the rest of
 * the page inert. Anything a component inside it teleports to <body> (a confirmation, a menu, a select, a popover) would then paint under the modal and take no
 * pointer events, so the Modal panel provides its open <dialog> here and those portals render into it instead, falling back to <body> outside a Modal.
 */
const key = 'kit.layer';

/** The Modal panel: provide its <dialog> (filled once mounted). */
export function provideLayer(dialog: Ref<HTMLElement | null>): void {
    provide(key, dialog);
}

/** A portal's target: the enclosing Modal's <dialog>, or <body>. */
export function useLayerTarget() {
    const dialog = inject<Ref<HTMLElement | null> | null>(key, null);

    return computed<HTMLElement | 'body'>(() => dialog?.value ?? 'body');
}

/**
 * For overlays mounted outside any Modal (the Toaster): the topmost open modal <dialog>, or <body>, kept current as dialogs open and close, so a toast raised
 * while a Modal is open shows over it.
 */
export function useTopLayer() {
    const target = ref<HTMLElement | 'body'>('body');
    let observer: MutationObserver | undefined;
    const update = () => {
        const open = [
            ...document.querySelectorAll<HTMLDialogElement>('dialog[open]'),
        ].filter((dialog) => dialog.matches(':modal'));
        target.value = open.at(-1) ?? 'body';
    };
    onMounted(() => {
        update();
        observer = new MutationObserver(update);
        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['open'],
        });
    });
    onBeforeUnmount(() => observer?.disconnect());

    return target;
}
