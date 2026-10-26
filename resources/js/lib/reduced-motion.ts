// Honour prefers-reduced-motion for the animations the browser runs through the Web Animations API (the server-driven modals' enter and leave): with the preference on, their
// duration is zero, so the panel appears and goes at once. CSS transitions are switched off with Tailwind's `motion-reduce:` variant where they are written.
export function honourReducedMotion(): void {
    if (typeof window === 'undefined' || typeof Element === 'undefined' || !window.matchMedia) return;
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    const original = Element.prototype.animate;
    Element.prototype.animate = function (this: Element, keyframes, options) {
        if (query.matches) {
            const instant = typeof options === 'number' ? 0 : { ...(options ?? {}), duration: 0, delay: 0 };
            return original.call(this, keyframes, instant as KeyframeAnimationOptions);
        }

        return original.call(this, keyframes, options);
    };
}
