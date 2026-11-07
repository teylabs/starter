<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import { computed } from 'vue';
import { Button as BaseButton } from '@/components/ui/button';
import {
    inverseControl,
    inverseFilled,
    pillControl,
    useSurface,
} from '@/components/Kit/Surface/surface';
import { insideFormSubmit } from './formSubmit';

// What a link's visit may send: the kit's own type (it imports none from @inertiajs/core), shaped like Inertia's form data.
export type VisitValue =
    | string
    | number
    | boolean
    | null
    | undefined
    | Date
    | Blob
    | File
    | VisitValue[]
    | { [key: string]: VisitValue };
export type VisitData = Record<string, VisitValue>;

// The buttons a form and a toolbar need: primary, secondary, destructive and success (a positive or go action), `ghost` and `link` (the host button's own ghost and link), and `bar`: a ghost for
// controls ON the shell's header bar, drawn from `--shell-bar-foreground` so it reads on a dark bar too. No variant needs anything new in the host button.
// `size` is the host button's default, sm, lg or icon; `xl` is the host's lg made a large tap target (about 96px tall: a phone used as a remote) with kit classes. `type` defaults to `submit` only inside a form that submits it (Inertia's <Form> or a FormCard) and to
// `button` everywhere else, so a toolbar or menu button never submits a form by accident; pass `type` to say otherwise.
// On a surface (FloatingToolbar's pill, a Surface) it takes that surface's shape and tone; elsewhere the shared radius and tones hold.
// With `href` it is a link styled as this button (the host button's as-child, so the classes land on the link and a <button> is never inside an <a>): an Inertia
// <Link> (`method`, `data`, `preserveScroll`; a method other than get renders it as a <button>, as Inertia requires for a non-GET visit), or a plain <a> when
// `external`, with `rel="noopener noreferrer"` when `target` is `_blank`. A `mailto:`, `tel:` or `sms:` href is always a plain <a>: an Inertia visit would mangle it.
const props = withDefaults(
    defineProps<{
        variant?:
            | 'primary'
            | 'secondary'
            | 'destructive'
            | 'success'
            | 'ghost'
            | 'link'
            | 'bar';
        size?: 'default' | 'sm' | 'lg' | 'xl' | 'icon';
        type?: 'submit' | 'button';
        href?: string;
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
        data?: VisitData;
        preserveScroll?: boolean;
        external?: boolean;
        target?: string;
    }>(),
    {
        variant: 'primary',
        size: 'default',
        type: undefined,
        href: undefined,
        method: 'get',
        data: undefined,
        preserveScroll: false,
        external: false,
        target: undefined,
    },
);
const base = computed(() =>
    props.variant === 'primary' || props.variant === 'success'
        ? 'default'
        : props.variant === 'bar'
          ? 'ghost'
          : props.variant,
);
const inForm = Boolean(useOptionalFormContext()) || insideFormSubmit();
const kind = computed(() => props.type ?? (inForm ? 'submit' : 'button'));
// A visit that is not a GET is a <button> (Inertia's Link `as="button"`), never an <a>.
const visitAsButton = computed(() => props.method !== 'get');
// A scheme a browser hands to another app, never an Inertia visit.
const plainLink = computed(
    () => props.external || /^(mailto|tel|sms):/i.test(props.href ?? ''),
);
const rel = computed(() =>
    props.target === '_blank' ? 'noopener noreferrer' : undefined,
);
// `success`: a positive or go action (Start, Publish), filled green on the host's default button. A host themes it with --color-success and
// --color-success-foreground; the fallbacks are green-700 on white (5.0:1, AA) and green-800 on hover (7.1:1), in light and dark alike.
const successClass =
    'bg-[var(--color-success,#15803d)] text-[var(--color-success-foreground,#fff)] hover:bg-[var(--color-success-hover,#166534)] hover:text-[var(--color-success-foreground,#fff)]';
// `destructive` hover: the host button's `hover:bg-destructive/90` is translucent, so on a light page it falls to about 4.3:1 with white text (fails AA).
// The kit sets a solid hover instead, as for `success`: --color-destructive-hover, else red-700 (6.5:1 on white text), in light and dark.
const destructiveClass =
    'hover:bg-[var(--color-destructive-hover,#b91c1c)] dark:hover:bg-[var(--color-destructive-hover,#b91c1c)] hover:text-white';
const barClass =
    'text-[color:var(--shell-bar-foreground)] hover:bg-[color:var(--shell-bar-foreground)]/10 hover:text-[color:var(--shell-bar-foreground)]';
const hostSize = computed(() => (props.size === 'xl' ? 'lg' : props.size));
const xlClass = 'h-24 min-w-24 rounded-xl px-8 text-lg [&_svg]:size-6';
const surface = useSurface();
const blend = computed(() => [
    props.size === 'xl' && xlClass,
    props.variant === 'bar' && barClass,
    props.variant === 'success' && successClass,
    props.variant === 'destructive' && destructiveClass,
    surface.value.shape === 'pill' && pillControl,
    surface.value.tone === 'inverse' &&
        (props.variant === 'primary' ? inverseFilled : inverseControl),
]);
</script>
<template>
    <BaseButton
        v-if="!href"
        :variant="base"
        :size="hostSize"
        :type="kind"
        :class="blend"
        ><slot
    /></BaseButton>
    <BaseButton v-else as-child :variant="base" :size="hostSize" :class="blend">
        <a
            v-if="plainLink"
            :href="href"
            :target="target"
            :rel="rel"
            data-button-link
            ><slot
        /></a>
        <Link
            v-else
            :href="href"
            :method="method"
            :data="data"
            :preserve-scroll="preserveScroll"
            :as="visitAsButton ? 'button' : 'a'"
            :type="visitAsButton ? 'button' : undefined"
            data-button-link
            ><slot
        /></Link>
    </BaseButton>
</template>
