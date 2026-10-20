// The shell's view of the backend `layout` props (tey/kit's ShellData), shaped for the Shell components.
export type FeatureState = 'available' | 'locked';
export type ShellLink = { label: string; url: string };
export type ShellNavItem = { label: string; url: string; current: boolean; state: FeatureState };
export type ShellNavGroup = { label: string; items: ShellNavItem[] };
export type ShellUser = { name: string; email: string; initials: string };
export type Shell = {
    product: string;
    homeUrl: string;
    navigation: ShellNavGroup[];
    user: ShellUser;
    settingsUrl: string;
    signOutUrl: string;
    secondaryLinks: ShellLink[];
    environment: string | null;
};

type Layout = Tey.Kit.Features.SessionUi.Data.ShellData;

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    return (parts.length > 1 ? parts[0][0] + parts[parts.length - 1][0] : (parts[0] ?? '?').slice(0, 2)).toUpperCase();
}

/** Null for a guest: the shell is only for a signed-in user. */
export function shellFrom(layout: Layout | undefined): Shell | null {
    if (!layout?.user || !layout.logout_url) return null;
    const user = layout.user as { name: string; email: string };
    const navigation = (layout.navigation as { label: string; href: string; current: boolean }[]).map((item) => ({ label: item.label, url: item.href, current: item.current, state: 'available' as const }));
    const workspaces = (layout.workspaces as { id: number; name: string; href: string | null }[]).filter((workspace) => workspace.href !== null).map((workspace) => ({ label: workspace.name, url: workspace.href as string, current: (layout.workspace as { id: number } | null)?.id === workspace.id, state: 'available' as const }));
    const groups: ShellNavGroup[] = [];
    if (navigation.length) groups.push({ label: (layout.workspace as { name: string } | null)?.name ?? 'Workspace', items: navigation });
    if (workspaces.length > 1 || !navigation.length) groups.push({ label: 'Workspaces', items: workspaces });

    return {
        product: layout.brand,
        homeUrl: '/workspaces',
        navigation: groups,
        user: { name: user.name, email: user.email, initials: initials(user.name) },
        settingsUrl: navigation.find((item) => item.label === 'Settings')?.url ?? '/workspaces',
        signOutUrl: layout.logout_url,
        secondaryLinks: [],
        environment: null,
    };
}
