import { onBeforeUnmount, watch } from 'vue';

/*
 * The shell owns the document title on the client. Inertia's <Head> collects every provider's head and writes it on a debounced timer, so a write queued
 * from the page being left could still land after Back or a visit had rendered the next page, leaving the heading, the current item and the address on the
 * new page and the tab on the old one. Here the title is written in the same flush as the page, into the shell's own <title data-kit-title>, which Inertia
 * neither renders nor diffs: no Inertia head update can name an older page. On the server there is no document; the shell renders <Head :title> there
 * instead, and the first client commit removes that server <title> (it is no longer a provider's), leaving this one.
 */
export function writeDocumentTitle(title: string | null | undefined): void {
    if (typeof document === 'undefined') return;
    // No title to own (`documentTitle` false, a layout without one): the shell's element goes, so a page's own <Head> title is the one shown.
    if (!title) {
        document.head.querySelector('title[data-kit-title]')?.remove();
        return;
    }
    let element = document.head.querySelector<HTMLTitleElement>('title[data-kit-title]');
    if (!element) {
        element = document.createElement('title');
        element.setAttribute('data-kit-title', '');
    }
    // document.title reads the first <title>: the host's static one (<title inertia>) goes, and the shell's stands before any Inertia still renders.
    document.head.querySelectorAll('title:not([data-inertia]):not([data-kit-title])').forEach((other) => other.remove());
    const first = document.head.querySelector('title');
    if (first !== element) document.head.insertBefore(element, first);
    if (element.textContent !== title) element.textContent = title;
}

/**
 * Keeps the document title on `source` (the page's `layout.documentTitle`, or undefined to hand it back to the pages' <Head>), from the first render on;
 * when the shell unmounts (a visit to a page outside it), its title goes with it.
 */
export function useDocumentTitle(source: () => string | null | undefined): void {
    watch(source, writeDocumentTitle, { flush: 'post', immediate: true });
    onBeforeUnmount(() => writeDocumentTitle(null));
}
