// The focus shell's props (session-ui's FocusShellData), typed here so the shell needs no session-ui types.
export type FocusShellShape = {
    kind: 'focus';
    product: { name: string; url: string; logomark: string | null; logomark_dark?: string | null; wordmark?: string | null; wordmark_dark?: string | null; lockup?: boolean };
    user: { name: string; email: string; initials: string; avatar_url: string | null; avatar_url_2x?: string | null };
    links: { sign_out: string; switch_account: string };
    step: { current: number; total: number; label: string } | null;
};
