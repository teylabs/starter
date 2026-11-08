import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

/*
 * One action as InertiaAction and DropdownAction read it: the kit's own Action (Tey\Kit\Features\Core\Data\Action: `confirm`, `destructive`, `disabled` with
 * `reason`, `newTab`) or the older keyed-link shape (`confirmationMessage`, `variant: 'danger'`, `target`, `description`). Both normalise to the same fields.
 */
export type LegacyAction = {
    url: string;
    method?: string;
    label: string;
    icon?: string | null;
    description?: string | null;
    variant?: string | null;
    confirmationMessage?: string | null;
    target?: string | null;
};
export type SingleAction = ActionDescriptor | LegacyAction;

export type NormalAction = {
    label: string;
    url: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    icon: string | null;
    caption: string | null;
    danger: boolean;
    disabled: boolean;
    reason: string | null;
    newTab: boolean;
    confirm: {
        title: string;
        description: string;
        label: string;
        typed: string | null;
    } | null;
};

export function normalAction(action: SingleAction): NormalAction {
    const kit = action as ActionDescriptor;
    const legacy = action as LegacyAction;
    const danger = Boolean(kit.destructive) || legacy.variant === 'danger';
    const confirm = kit.confirm
        ? {
              title: kit.confirm.title ?? `${action.label}?`,
              description: kit.confirm.description,
              label: kit.confirm.label,
              typed: kit.confirm.typed ?? null,
          }
        : legacy.confirmationMessage
          ? {
                title: 'Please confirm',
                description: legacy.confirmationMessage,
                label: 'Proceed',
                typed: null,
            }
          : null;

    return {
        label: action.label,
        url: action.url,
        method: (
            action.method ?? 'get'
        ).toLowerCase() as NormalAction['method'],
        icon: action.icon ?? null,
        caption: legacy.description ?? null,
        danger,
        disabled: Boolean(kit.disabled),
        reason: kit.reason ?? null,
        newTab: Boolean(kit.newTab) || legacy.target === '_blank',
        confirm,
    };
}
