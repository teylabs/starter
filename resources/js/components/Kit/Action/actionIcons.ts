import {
    Banknote,
    Download,
    ChartColumn,
    Check,
    Copy,
    CreditCard,
    Eye,
    EyeOff,
    FileText,
    History,
    ArrowDown,
    ArrowUp,
    Image,
    LockOpen,
    Mail,
    Merge,
    MessageSquare,
    Pencil,
    Plus,
    Send,
    TextAlignCenter,
    TextAlignEnd,
    TextAlignStart,
    Trash,
    Vote,
    X,
    type LucideIcon,
} from '@lucide/vue';

// The icons an action descriptor or a toolbar item may name (`icon`, kebab case), imported by name so a bundler keeps only these. An unknown name renders no icon
// rather than an unrelated one. The editor set: align-left/center/right, arrow-up/down (move earlier or later), trash, image, eye/eye-off (a visibility toggle),
// plus, copy, chart and poll; history and mail; lock-open, x, merge and download (the menu's reopen, cancel/decline, merge and download defaults).
export const actionIcons: Record<string, LucideIcon> = {
    'align-center': TextAlignCenter,
    'align-left': TextAlignStart,
    'align-right': TextAlignEnd,
    'arrow-down': ArrowDown,
    'arrow-up': ArrowUp,
    banknote: Banknote,
    chart: ChartColumn,
    check: Check,
    copy: Copy,
    download: Download,
    'credit-card': CreditCard,
    eye: Eye,
    'eye-off': EyeOff,
    'file-text': FileText,
    history: History,
    image: Image,
    'lock-open': LockOpen,
    mail: Mail,
    merge: Merge,
    'message-square': MessageSquare,
    pencil: Pencil,
    plus: Plus,
    poll: Vote,
    send: Send,
    trash: Trash,
    x: X,
};

/**
 * Dev builds only: a menu whose rows mix icons and no icons warns once (per menu instance, through the returned function), since every menu row should carry an
 * icon. The iconless rows still indent to the icon column. Production never warns.
 */
export function mixedIconsWarning(menu: string): (withIcon: boolean[]) => void {
    let warned = false;
    return (withIcon) => {
        if (warned || !import.meta.env?.DEV) return;
        if (!withIcon.includes(true) || !withIcon.includes(false)) return;
        warned = true;
        console.warn(
            `[kit] ${menu}: some menu items have an icon and some do not. Give every item an icon.`,
        );
    };
}
