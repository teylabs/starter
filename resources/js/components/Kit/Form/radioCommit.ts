// RadioCardGroup's `commit="click"` mode without Vue: where the arrow keys move focus (never selection), and whether a press commits. A press on the current
// option, or on a disabled one, commits nothing.
export type CommitOption = { value: string | number; disabled?: boolean };

/** The option the arrow keys, Home and End move focus to, skipping disabled ones and wrapping; null for any other key (Space and Enter are the button's). */
export function moveFocus(
    key: string,
    current: number,
    options: readonly CommitOption[],
): number | null {
    const enabled = options
        .map((option, index) => (option.disabled ? -1 : index))
        .filter((index) => index >= 0);
    if (enabled.length === 0) return null;
    const position = Math.max(0, enabled.indexOf(current));
    switch (key) {
        case 'ArrowDown':
        case 'ArrowRight':
            return enabled[(position + 1) % enabled.length];
        case 'ArrowUp':
        case 'ArrowLeft':
            return enabled[(position - 1 + enabled.length) % enabled.length];
        case 'Home':
            return enabled[0];
        case 'End':
            return enabled[enabled.length - 1];
        default:
            return null;
    }
}

/** The value a press commits, or null: nothing for a disabled option, a disabled group, or the option already chosen. */
export function commitChoice(
    current: string | number | null,
    option: CommitOption,
    groupDisabled = false,
): string | number | null {
    if (groupDisabled || option.disabled || option.value === current)
        return null;
    return option.value;
}

/**
 * The `commit="click"` controller the component runs: `key` moves focus only (it never commits), `press` (Space, Enter, a click or a tap: the button's click)
 * commits through `commitChoice`, and each commit is reported once to `commit`.
 */
export function createCommitter(state: {
    options: () => readonly (CommitOption & { label?: string })[];
    disabled: () => boolean;
    value: () => string | number | null;
    commit: (value: string | number, label: string) => void;
    focus: (index: number) => void;
}) {
    return {
        press(index: number): void {
            state.focus(index);
            const option = state.options()[index];
            if (!option) return;
            const value = commitChoice(state.value(), option, state.disabled());
            if (value !== null)
                state.commit(value, option.label ?? String(value));
        },
        key(key: string, index: number): boolean {
            const next = moveFocus(
                key,
                index,
                state.disabled() ? [] : state.options(),
            );
            if (next === null) return false;
            state.focus(next);
            return true;
        },
    };
}
