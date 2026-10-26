// The shell parts a host may replace. The simplest way is the Layout's `user-menu` and `workspace-switcher` slots, which take
// the same props. To replace them app-wide, note that the shell Layout imports these only through `@kit/shell`, an alias for this file:
// point `@kit/shell` at your own module, re-export what you keep (`export * from '@/pages/Workspace/Partials'`), then
// export your own `UserMenu` or `WorkspaceSwitcher` with the same props.
export { default as UserMenu } from './UserMenu.vue';
export { default as WorkspaceSwitcher } from './WorkspaceSwitcher.vue';
