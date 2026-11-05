// A note's fill by colour key: literal class names, so Tailwind sees every one. A colour the app added in kit.notes.colours without a tone here uses the card fill.
const tones: Record<string, string> = {
    yellow: 'bg-amber-100 text-amber-950 dark:bg-amber-950/50 dark:text-amber-50',
    pink: 'bg-pink-100 text-pink-950 dark:bg-pink-950/50 dark:text-pink-50',
    blue: 'bg-sky-100 text-sky-950 dark:bg-sky-950/50 dark:text-sky-50',
    green: 'bg-emerald-100 text-emerald-950 dark:bg-emerald-950/50 dark:text-emerald-50',
};
const dots: Record<string, string> = { yellow: 'bg-amber-400', pink: 'bg-pink-400', blue: 'bg-sky-400', green: 'bg-emerald-400' };

export const tone = (colour: string): string => tones[colour] ?? 'bg-card text-card-foreground';
export const dot = (colour: string): string => dots[colour] ?? 'bg-muted-foreground';
// One key per attempt: a retry of the same save (a lost response) replays it instead of writing twice.
export const intentKey = (): string => crypto.randomUUID();
