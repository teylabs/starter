// The tones a card header or banner takes, on the kit's tokens: a solid fill for primary, a tinted band for danger, a muted band with toned text for the rest.
export type CardTone =
    'primary' | 'danger' | 'success' | 'warning' | 'info' | 'muted';

export const CARD_TONES: Record<CardTone, string> = {
    primary: 'bg-primary text-primary-foreground',
    danger: 'bg-destructive/10 text-destructive-text',
    success: 'bg-muted text-success-text',
    warning: 'bg-muted text-warning-text',
    info: 'bg-muted text-info-text',
    muted: 'bg-muted text-foreground',
};

/** A card's gutters, shared by its header, body and footer so their edges line up. */
export const CARD_GUTTER = 'px-4 sm:px-5';
