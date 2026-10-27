// The default UI set kit pages and the shell render with. Kit pages import UI only from `@kit/ui`, an alias for this file:
// point `@kit/ui` at your own module that exports the same names and props (see ./contract.ts) and kit pages render your
// components. The default implementation wraps the shadcn-vue primitives in `@/components/ui` and Reka UI.
export { default as AppearanceChoice } from './Appearance/AppearanceChoice.vue';
export { default as AppearanceToggle } from './Appearance/AppearanceToggle.vue';
export { initializeTheme, useAppearance } from './Appearance/useAppearance';
export { browserTimezone, syncTimezoneCookie } from './timezone';
export { default as Placeholder } from './Feedback/Placeholder.vue';
export { default as Alert } from './Feedback/Alert.vue';
export { alertVariants } from './Feedback/alertVariants';
export type { AlertTier, AlertVariant } from './Feedback/alertVariants';
// The app's one toast renderer, mounted by the shell; it renders the server's flashed `toast` (toasts.md). Sticky bottom bars register with useStickyBars.
export { default as Toaster } from './Feedback/Toaster.vue';
export { useStickyBars } from './Layout/stickyBars';
export { default as Avatar } from './Layout/Avatar.vue';
export { default as BlockStack } from './Layout/BlockStack.vue';
export { default as Toolbar } from './Layout/Toolbar.vue';
export { default as Button } from './Form/Button.vue';
export { default as InputCheckbox } from './Form/InputCheckbox.vue';
/** @deprecated Use InputCheckbox; removed in 0.4. */
export { default as Checkbox } from './Form/InputCheckbox.vue';
export { default as DataTable } from './Table/DataTable.vue';
export { default as Dialog } from './Overlay/Dialog.vue';
export { default as Modal } from './Overlay/Modal.vue';
// A non-modal popover anchored to a trigger (Popover.md); a bottom sheet on phones.
export { default as Popover } from './Overlay/Popover.vue';
export type { PopoverDismiss, PopoverMessages, PopoverWidth } from './Overlay/popover';
export { default as Field } from './Form/Field.vue';
export { default as Page } from './Layout/Page.vue';
export { default as FileInput } from './Form/FileInput.vue';
export { default as FormActions } from './Form/FormActions.vue';
export { default as FormCard } from './Form/FormCard.vue';
export { default as FormFieldset } from './Form/FormFieldset.vue';
// A form someone may read but not change (LockedForm.md): the wrapper, the renderless EditOnly for caller-owned edit actions, and the context every kit control reads.
export { default as LockedForm } from './Form/LockedForm.vue';
export { default as EditOnly } from './Form/EditOnly.vue';
export { useLocked } from './Form/useLocked';
export type { LockedState } from './Form/locked';
export { default as Input } from './Form/Input.vue';
export { default as PasswordInput } from './Form/PasswordInput.vue';
export { default as SectionIcon } from './Layout/SectionIcon.vue';
export { default as SettingsFrame } from './Layout/SettingsFrame.vue';
export { default as Section } from './Form/Section.vue';
/** @deprecated Use InputSelect, the kit's rich select; Select stays until 0.4. */
export { default as Select } from './Form/Select.vue';
export { default as InputSelect } from './Form/InputSelect.vue';
export type { InputSelectGroup, InputSelectMessages, InputSelectOption, InputSelectOptions, InputSelectValue } from './Form/inputSelect';
export { default as Textarea } from './Form/Textarea.vue';
export { default as TimezoneSelect } from './Form/TimezoneSelect.vue';
export { default as RowActions } from './Table/RowActions.vue';
export { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
export { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
export { default as InputNumber } from './Form/InputNumber.vue';
/** @deprecated Use InputNumber; removed in 0.4. */
export { default as NumberInput } from './Form/InputNumber.vue';
export { numberFormat } from './Form/number';
export type { NumberKind, NumberOptions } from './Form/number';
export { default as Repeater } from './Form/Repeater.vue';
export type { RepeaterRow } from './Form/repeaterState';
export { default as ScrollList } from './List/ScrollList.vue';
export { useScrollList } from './List/useScrollList';
export type { ListAction, ListItem, ScrollPayload } from './List/useScrollList';
export type * from './contract';
// Computed values with their working (ComputedValue.md).
export { default as ComputedValue } from './Explanation/ComputedValue.vue';
export { default as ComputedValueRoot } from './Explanation/ComputedValueRoot.vue';
export { default as ComputedValueScope } from './Explanation/ComputedValueScope.vue';
export { default as ComputedValueToggle } from './Explanation/ComputedValueToggle.vue';
export type * from './Explanation/explanation.generated';
// Page actions: FloatingToolbar with its surface context, ActionBar over server descriptors, and SectionHeader (FloatingToolbar.md, ActionBar.md, SectionHeader.md).
export { default as FloatingToolbar } from './Layout/FloatingToolbar.vue';
export { default as FloatingToolbarDot } from './Layout/FloatingToolbarDot.vue';
export { provideSurface, useSurface } from './Surface/surface';
export type { Surface } from './Surface/surface';
export { default as ActionBar } from './Action/ActionBar.vue';
export { default as ActionLabel } from './Action/ActionLabel.vue';
export { default as ButtonGroup } from './Action/ButtonGroup.vue';
export { isNavigation, layoutActions, placeActions } from './Action/actionPlacement';
export type { ActionDescriptor, ActionLayout } from './Action/actionPlacement';
export { default as SectionHeader } from './Layout/SectionHeader.vue';
// The menu family (Dropdown.md and its relatives, ButtonDropdown.md), over the shared MenuPanel (internal, not exported). Items must sit inside one of these menus or ButtonGroup's `menu` slot. Menu/DropdownMenu.vue (auto-wrapping items) is not exported: its name is the shadcn primitive's above.
export { default as Dropdown } from './Menu/Dropdown.vue';
export { default as DropdownItem } from './Menu/DropdownItem.vue';
export { default as DropdownLink } from './Menu/DropdownLink.vue';
export { default as DropdownConfirm } from './Menu/DropdownConfirm.vue';
export { default as DropdownAction } from './Menu/DropdownAction.vue';
export { default as DropdownDelete } from './Menu/DropdownDelete.vue';
export { default as DropdownModalLink } from './Menu/DropdownModalLink.vue';
export { default as ButtonDropdown } from './Action/ButtonDropdown.vue';
// Split work and its context (SplitPane.md) and the one dropzone (BulkDrop.md §9).
export { default as SplitPane } from './Layout/SplitPane.vue';
export { default as PaneScroll } from './Layout/PaneScroll.vue';
export { default as PaneTabs } from './Layout/PaneTabs.vue';
export { default as PaneToggle } from './Layout/PaneToggle.vue';
export type { SplitPreference } from './Layout/splitState';
export { default as BulkDrop } from './Form/BulkDrop.vue';
export { captureDrop, capturePaste, checkSelection, describeProblem, setCaptureFiles } from './Form/captureFiles';
