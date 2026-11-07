import { cva, type VariantProps } from 'class-variance-authority';

// The shared semantic variants, in two tiers: `pale` (a tinted surface, dark text, a coloured icon) and `solid` (full colour, white text) for the rare message that
// must outrank the page. Every pairing is chosen to keep 4.5:1 text contrast in light and dark. Components take colour from here and write none of their own.
// A pale tint takes the theme's foreground for its text and the semantic *-text token for its icon, never a raw palette text colour.
export const alertVariants = cva(
    'relative flex items-start gap-3 rounded-lg border p-4 text-sm',
    {
        variants: {
            variant: {
                info: '',
                success: '',
                warning: '',
                danger: '',
                primary: '',
                brand: '',
                muted: '',
            },
            tier: { pale: '', solid: '' },
        },
        compoundVariants: [
            {
                variant: 'info',
                tier: 'pale',
                class: 'text-foreground border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/60',
            },
            {
                variant: 'success',
                tier: 'pale',
                class: 'text-foreground border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/60',
            },
            {
                variant: 'warning',
                tier: 'pale',
                class: 'text-foreground border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60',
            },
            {
                variant: 'danger',
                tier: 'pale',
                class: 'text-foreground border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60',
            },
            {
                variant: 'primary',
                tier: 'pale',
                class: 'border-primary/30 bg-primary/10 text-foreground',
            },
            {
                variant: 'brand',
                tier: 'pale',
                class: 'border-accent-foreground/20 bg-accent text-accent-foreground',
            },
            {
                variant: 'muted',
                tier: 'pale',
                class: 'border-border bg-muted text-foreground',
            },
            {
                variant: 'info',
                tier: 'solid',
                class: 'border-sky-800 bg-sky-800 text-white',
            },
            {
                variant: 'success',
                tier: 'solid',
                class: 'border-green-800 bg-green-800 text-white',
            },
            {
                variant: 'warning',
                tier: 'solid',
                class: 'border-amber-800 bg-amber-800 text-white',
            },
            {
                variant: 'danger',
                tier: 'solid',
                class: 'border-red-800 bg-red-800 text-white',
            },
            {
                variant: 'primary',
                tier: 'solid',
                class: 'border-primary bg-primary text-primary-foreground',
            },
            {
                variant: 'brand',
                tier: 'solid',
                class: 'border-accent-foreground bg-accent-foreground text-accent',
            },
            {
                variant: 'muted',
                tier: 'solid',
                class: 'border-foreground bg-foreground text-background',
            },
        ],
        defaultVariants: { variant: 'info', tier: 'pale' },
    },
);

/** The leading icon's colour, by variant and tier. */
export const alertIconClass = (
    variant: AlertVariant,
    tier: AlertTier,
): string => {
    if (tier === 'solid') return 'text-current';
    return {
        info: 'text-info-text',
        success: 'text-success-text',
        warning: 'text-warning-text',
        danger: 'text-destructive-text',
        primary: 'text-primary',
        brand: 'text-accent-foreground',
        muted: 'text-muted-foreground',
    }[variant];
};

export type AlertVariant = NonNullable<
    VariantProps<typeof alertVariants>['variant']
>;
export type AlertTier = NonNullable<VariantProps<typeof alertVariants>['tier']>;
