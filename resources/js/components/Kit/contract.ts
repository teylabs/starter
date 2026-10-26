// The contract of the UI set behind `@kit/ui`: the props each component takes. An app's own module exports components of these
// names that accept these props (and the v-model and slots noted here), and kit pages render them unchanged.

/** A labelled field: `name` reads its error from Inertia's <Form> context (`error` passes one), `span` (1 to 6 of the card's columns, from `sm` up) sets its width. */
export interface FieldProps { id: string; label: string; name?: string; description?: string; error?: string; span?: 1 | 2 | 3 | 4 | 5 | 6 }

/** A text input (`v-model`: string | number). `invalid` sets the error state; `autofocus` focuses it on arrival (never a read-only one); other attributes (id, readonly, required, maxlength, aria-*) pass through. */
export interface InputProps { invalid?: boolean }

/**
 * An inline message in the page's content (slots: default body, `actions`). `variant`: info (default), success, warning, danger, primary, brand, muted; `tier`: pale (default) or
 * solid; `title` is one short line; `icon` is on by default (by variant), `false` hides it. `live`: none (default, already on the page at load), status (polite, appears after
 * something the person did) or alert (assertive, blocks them; sparingly). `dismiss` is a server descriptor and `dismissible` shows the close button: only for what can be ignored.
 */
export interface AlertProps { variant?: 'info' | 'success' | 'warning' | 'danger' | 'primary' | 'brand' | 'muted'; tier?: 'pale' | 'solid'; title?: string; icon?: boolean; live?: 'none' | 'status' | 'alert'; dismissible?: boolean; dismiss?: { url: string; method?: 'post' | 'put' | 'patch' | 'delete' } | null }

/** A page that is not built yet: `description` says what it will do, `milestone` (words only) renders "Planned for {milestone}", `links` are descriptors. A plain region, no live role. */
export interface PlaceholderProps { description: string; milestone?: string | null; links?: { label: string; url: string }[] }

/** A multi-line input (`v-model`: string), as Input. */
export interface TextareaProps { invalid?: boolean }

/** A select (`v-model`: string): `options` are values with labels; `id` goes on the trigger. */
export interface SelectProps { id?: string; name?: string; options: { value: string; label: string }[]; placeholder?: string; disabled?: boolean; invalid?: boolean }

/**
 * The kit's one rich select (`v-model`: a value or `null`, or an array with `multiple`), on Reka's Combobox and non-modal. `options` are `{ value, label, description?, disabled? }` or groups `{ label, options }`.
 * `searchable` `null` turns search on past 8 options; `filter="remote"` leaves filtering to the caller (`search`, after `debounce` ms and `minSearchLength` characters; `''` on close). `clearable` is on with `multiple`;
 * `selectAll`, `chosenFirst` and `creatable` (`create`, with the typed text) are opt-in; `maxChips` swaps chips for a count. With `name` it submits hidden `name` / `name[]` inputs. `readonly` stays a focusable combobox.
 * Default strings come from the kit's `ui` lang file (`select.*`, shared as `kitUi`); `messages` overrides them per use. Slots: `option`, `chip`, `empty`, `loading`. Other attributes (id, aria-*) land on the input.
 */
export interface InputSelectProps { modelValue?: string | number | null | (string | number)[]; options?: { value: string | number; label: string; description?: string; disabled?: boolean }[] | { label: string; options: { value: string | number; label: string; description?: string; disabled?: boolean }[] }[]; multiple?: boolean; name?: string; placeholder?: string; searchable?: boolean | null; clearable?: boolean | null; selectAll?: boolean; chosenFirst?: boolean; creatable?: boolean; disabled?: boolean; readonly?: boolean; invalid?: boolean; loading?: boolean; filter?: 'local' | 'remote'; debounce?: number; minSearchLength?: number; messages?: Record<string, string>; maxChips?: number | null; nativeRequired?: boolean }

/** A timezone select (`v-model`: an IANA zone name): Select over the zones the browser knows. */
export interface TimezoneSelectProps { id?: string; name?: string; disabled?: boolean; invalid?: boolean }

/** A checkbox (`v-model`: boolean) with its label in the default slot. */
export interface CheckboxProps { id: string; name?: string; value?: string; disabled?: boolean }

/** A button: `primary`, `secondary`, `destructive`, or `bar` (a ghost for controls on the shell's header bar, from `--shell-bar-foreground`); a submit button unless `type` says otherwise. The default slot is its label; other attributes (disabled, @click) pass through. */
export interface ButtonProps { variant?: 'primary' | 'secondary' | 'destructive' | 'bar'; type?: 'submit' | 'button' }

/** The root of every page: the header (the `title` prop, or `title` here, and an `actions` slot) and the content in one column. the width is a boolean prop (`<Page sm>`: `xs`, `sm`, `md`, `lg`, `xl`, or `full`); none is full (the default: the whole shell, for lists and tables), and fixed-content pages declare a focused one. `state` swaps the content for the `loading`, `empty` or `error` slot. FocusPage is internal to it. */
export interface PageProps { xs?: boolean; sm?: boolean; md?: boolean; lg?: boolean; xl?: boolean; full?: boolean; title?: string; heading?: boolean; state?: 'ready' | 'loading' | 'empty' | 'error' }


/** A card for a group of fields: optional `title` and `description` above, the content in the default slot. */
export interface SectionProps { title?: string; description?: string }

// The menu and sheet primitives are the shadcn-vue ones (`DropdownMenu*`, `Sheet*`): Reka UI-shaped, with their usual props and slots.

/** A password input with a show/hide toggle; the other attributes pass to the input. */
export interface PasswordInputProps { invalid?: boolean }

/** The header's light/dark switch (no props) and the appearance choice (light, dark or system, no props): both read and write the one appearance setting. `useAppearance()` and `initializeTheme()` are exported with them. */
export type AppearanceProps = Record<string, never>;

/** A settings section's icon by its lucide name (kebab case); an unknown name is the generic Settings icon. */
export interface SectionIconProps { name?: string }

/** The frame of a settings section's page: the sidebar of the sections the user may open (the page's `layout.navigation`, `aria-current` on the shown one), and the content in the default slot. */
export type SettingsFrameProps = Record<string, never>;

/** A person's photo (`src`, `src2x`) or their initials when there is none; `large` is the profile size. */
export interface AvatarProps { initials: string; src?: string | null; src2x?: string | null; large?: boolean }

/** A file chooser (`v-model`: File or null); `accept` filters the picker. `current` is the record's file (name, size, link), shown in place of the picker inside a locked LockedForm. */
export interface FileInputProps { id: string; accept?: string; disabled?: boolean; current?: { name: string; size?: number | null; url?: string | null } | null }

/**
 * The bar at the foot of a form (status, Cancel, submit; right-aligned; sticky). It reads Inertia's <Form> context, or `form` (a useForm() result). `submit` is the server's action descriptor (its label, and `disabled` with a `reason` shown in the status slot); `submitLabel` is the verb
 * for the action, never "Submit"; `busyLabel` follows it. Slots: `status` (slot props `{ state }`), `actions` (before Cancel), `submit`. Delete never goes here.
 */
export interface FormActionsProps { form?: object; submit?: { label: string; disabled?: boolean; reason?: string | null }; submitLabel?: string; busyLabel?: string; savedLabel?: string; cancelUrl?: string; cancel?: boolean; sticky?: boolean; warnOnLeave?: boolean }

/** A card for a form: a title, a six-column field grid, fieldsets, and the FormActions bar in its footer (same props). */
export interface FormCardProps extends FormActionsProps { title?: string; description?: string }

/**
 * A titled group of fields inside a FormCard: a native fieldset whose legend holds `title` as a heading at `level` (2 to 6, default 2), `description` after the legend (aria-describedby),
 * `legendHidden` for screen readers only, `separator` (`rule`, default, or `space`) between sibling groups, `fullwidth` to stack the fields. Slots: `title`, `description`, default (the fields). `legend` is the old name of `title`, until 0.4.
 */
export interface FormFieldsetProps { title?: string; legend?: string; description?: string; level?: 2 | 3 | 4 | 5 | 6; legendHidden?: boolean; separator?: 'rule' | 'space'; fullwidth?: boolean }

/**
 * A form someone may read but not change: locked when `save`, the page's save descriptor, is `null` (the server decides). Every kit control inside turns read-only (never disabled), kit edit
 * actions and `intent: 'edit'` descriptors are hidden, each FormCard shows the notice in place of FormActions and nothing submits. `quantity` picks the plural (default) or singular notice,
 * `notice` replaces it, `reason` adds a muted line. Slot props `{ locked }`. `EditOnly` (no props) renders its slot only while editable; `useLocked()` returns `{ locked, notice, reason }`.
 */
export interface LockedFormProps { save: object | null; quantity?: 'many' | 'one'; notice?: string; reason?: string | null }
export type EditOnlyProps = Record<string, never>;

/** The kit's Dialog: a confirmation or a prompt (static; never a form), `variant` danger (icon circle, destructive action) or neutral; `action` is a server action the dialog performs. The trigger is the default slot, `@confirm` runs the action; `v-model:open` opens it without one; `@closed` fires after it has closed and returned focus. Slots: icon, title, body, footer. Bottom-aligned on phones. */
export interface DialogProps { title: string; description?: string; confirmLabel?: string; cancelLabel?: string; variant?: 'danger' | 'neutral'; action?: { url: string; method: string } }


/**
 * A server-driven index table (TanStack headless, Tailwind). `table` is the server's TableData: rows, columns, sort, search, filters, tabs, pagination and the empty state, with each
 * row's `actions` already decided. Every change is an Inertia visit with the state in the URL. Slots: `cell-<key>` ({ row, value }), `row-actions`, `toolbar`, `empty-first-use`,
 * `empty-no-results`. `intercept` names row actions the page performs itself (`@act`). Nothing here checks a permission.
 */
export interface DataTableProps { table: object; itemKey?: string; stickyHeader?: boolean; searchLabel?: string; intercept?: string[]; toolbar?: boolean }

/** Vertical rhythm between sibling blocks: `flex flex-col gap-4` (`class="gap-6"` for a deliberate other rhythm). Spacing has one owner: siblings carry no margins. `as` / `asChild` as Reka's Primitive. */
export interface BlockStackProps { as?: string; asChild?: boolean }

/** Inline controls in one row that owns the gap and alignment: `overflow` (`wrap` | `scroll`) and `layout` (`inline` | `heading`). It never styles its children. */
export interface ToolbarProps { as?: string; asChild?: boolean; overflow?: 'wrap' | 'scroll'; layout?: 'inline' | 'heading' }

/**
 * The kit's Modal: content and forms in a layer over the page, ONE component for a server-driven modal page (inertiaui/modal, detected automatically) and for local use with `v-model:open`.
 * A fixed header, a scrolling body and a footer; with `submit` (the server's action) it is a form with FormActions as the sticky footer. Closing asks first with unsaved edits; the backdrop never closes it.
 * `size` sm | md | lg | xl | full; `slideover`; bottom sheet (sm, md) or full screen (the rest) on phones.
 */
export interface ModalProps { title: string; description?: string; size?: 'sm' | 'md' | 'lg' | 'xl' | 'full'; placement?: 'center' | 'slideover' | 'drawer'; as?: 'form' | 'div'; submit?: { label: string; url: string; method: string }; dirty?: boolean; busyLabel?: string; cancelLabel?: string; showCloseButton?: boolean; confirmMessage?: string }

/**
 * A repeating group of rows posted as native bracket paths (`name[0][field]`). `rows` carry string ids and a server-supplied `template` (without an id) is cloned for each added row, which gets a `new:` id. Deletion is staged in the form, never a request. `removable` is `true`, `false` or a server descriptor `{ label, rowIds }` that decides which ids show a remove control (visibility only: save must re-authorize). `limits` bound the row count. `errors` default to the Inertia form's. Reordering is by move buttons. One nested Repeater is supported.
 * Slots: `row` ({ row, index, name(field), errors }), `row-title`, `row-actions`, `row-menu`, `before-rows`, `empty`. Event: `change` (the stable row ids in order). `columns` switches to an input-grid layout (one header row, one cell per column; narrow containers stack); `gridClass`, `headerClass` and `rowClass` shape it, `flush` removes the card padding. `actionsInMenu` moves row actions and Remove into a menu. `externalAdd` hides the built-in add button; the page calls the exposed `add(overrides?)` itself.
 */
export interface RepeaterProps<T extends { id: string } = { id: string }> { name: string; rows: T[]; template?: Omit<T, 'id'>; limits?: { min?: number; max?: number }; addLabel: string; itemLabel: string; reorderable?: boolean; actionsInMenu?: boolean; removable?: boolean | { label: string; rowIds: string[] }; errors?: Record<string, string>; flush?: boolean; externalAdd?: boolean; columns?: string[]; gridClass?: string; headerClass?: string; rowClass?: string }

/**
 * An exact-number input. The visible field is labelled and unnamed; a hidden control carries `name` and the canonical string (money in integer minor units, decimals at a fixed `scale`, integers as digits; empty stays empty). Excess precision is invalid, never rounded, and blocks submission. `min`, `max` and `step` are canonical strings and client hints only: check them again on the server. `locale` comes from the host (default en-CA; Latin digits only). Inside a Repeater row it reads its error from the row. Event: `canonical` (the exact string).
 */
export interface InputNumberProps { name: string; label: string; kind?: 'money' | 'decimal' | 'integer'; id?: string; currency?: string; locale?: string; scale?: number; signed?: boolean; grouping?: boolean; defaultValue?: string; min?: string; max?: string; step?: string; unit?: string; suffix?: string; placeholder?: string; error?: string; disabled?: boolean; required?: boolean }
/** @deprecated Use InputNumberProps; removed in 0.4. */
export type NumberInputProps = InputNumberProps;

/**
 * A scrolling list over a server `ScrollListViewModel` prop named `listKey` (Inertia infinite scroll, cursor paging). The default row draws a `ListItemData` (title, link, description, meta, badges, actions through RowActions); the `row` slot ({ item }) draws a richer one. "Load more" is always present while there is more; a failed load keeps the rows and offers Retry. Cursor-only, fixed selection, count-free: no sorts, segments, totals, polling, page or simple modes.
 */
export interface ScrollListProps { listKey: string; title: string; density?: 'default' | 'compact' }

/**
 * A centred pill of page actions and status: `mode` auto (inline under short content, floating while long content scrolls, settling at its container's end), float or inline.
 * `reveal` is a selector whose absence from view sets `v-model:revealed` (also the default slot's `{ revealed }`), so the host repeats header actions only then. The slot takes
 * ActionBar (every action is a server descriptor, so on phones it overflows into the ⌄ menu) and STATUS only (text, the save state), never a control of its own: it cannot
 * overflow and would clip, so a button or link slotted directly warns in development. It reads no store. It provides the inverse tone and pill shape (`provideSurface` / `useSurface`) to the controls inside. FloatingToolbarDot separates groups.
 */
export interface FloatingToolbarProps { mode?: 'auto' | 'float' | 'inline'; reveal?: string; label?: string; revealed?: boolean }

/**
 * Server action descriptors as button groups: declared `group`/`menu` (layoutActions: menu-bearing groups last), else the placement rule (placeActions). Dialog intent opens the
 * route modal, document intent a new tab, ordinary GETs navigate; other actions are emitted (`@action`) for the host to run. `busy` blocks all but a standalone document link;
 * `dialogOpen` holds menu focus for the host's dialog; `primary` fills each group's leading segment.
 */
export interface ActionBarProps { actions: import('./Action/actionPlacement').ActionDescriptor[]; busy?: boolean; dialogOpen?: boolean; primary?: boolean }
/** ButtonGroup (ButtonGroup.md): raw `button`/`a`/`Link` children in the default slot, styled by the group; menu entries in `menu`; `label` names the trigger; `offset` is [skid, gap]. */
export interface ButtonGroupProps { label?: string | null; offset?: [number, number] }

/**
 * The one header for a section, widget, card or pane: `title` as a heading at `level` (default 2), `count` (zero shown, null hidden), `badge` and `avatar` descriptors, `meta` and
 * `description`; the `status` slot and the descriptor `actions` (ActionBar) on the other side. `@action` emits the original descriptor, matched by key.
 */
export interface SectionHeaderProps { title: string; count?: number | null; actions?: import('./Action/actionPlacement').ActionDescriptor[]; level?: 1 | 2 | 3 | 4 | 5 | 6; badge?: { label: string; variant: string } | null; meta?: string | null; description?: string | null; avatar?: { initials: string; src?: string | null; src2x?: string | null } | null }

/**
 * The menu family (Dropdown.md, DropdownItem.md and the DropdownLink/Confirm/Action/Delete/ModalLink specs, ButtonDropdown.md). Menus: `Dropdown` (`type="button"` for the labelled trigger, else an icon one; `label`, `alignLeft`, `horizontal`, `chevron`, `spin`,
 * `colorClasses`, `menuClasses`; slots default, `header`, `buttontext`, `trigger`; attributes land on the trigger) and `ButtonDropdown` (`label`, `offset` [skid, gap]; slots default, `menu`, `menuheader`). Items must sit inside a kit menu. As specced, `active` (DropdownItem,
 * DropdownLink) and `noun` (DropdownConfirm) do nothing, and DropdownLink's button form is always `type="submit"`.
 */
export interface DropdownProps { alignLeft?: boolean; horizontal?: boolean; type?: string; label?: string; colorClasses?: string; chevron?: boolean; spin?: boolean; menuClasses?: string }
export interface ButtonDropdownProps { label?: string | null; offset?: [number, number] }
/** DropdownItem: `as` "button" (a native button), "a" (a plain anchor) or anything else (an Inertia link; `inertia` with "button" makes an Inertia link rendered as a button); slots default, `icon`, `caption`, `wrapper` ({ close }). */
export interface DropdownItemProps { href?: string; as?: string | object | null; inertia?: boolean; active?: boolean }
export interface DropdownLinkProps { href?: string; as?: string | object | null; active?: boolean }
/** DropdownConfirm: confirming runs `onConfirmed` or an Inertia visit to `href` (`method`), then closes the dialog and the menu; `variant="danger"` for the destructive button. Slots default, `icon`, `caption`, `title`, `confirmation`. */
export interface DropdownConfirmProps { href?: string; method?: string; onConfirmed?: () => unknown; confirmLabel?: string; variant?: string; noun?: string }
/** DropdownDelete: confirming sends an Inertia DELETE to `href`; emits `confirming`, `success`, `error`. Slots default, `description`, `title`, `message`, `caption` (`context` is its default). */
export interface DropdownDeleteProps { href?: string; noun?: string; context?: string; deleteLabel?: string; preserveState?: boolean; preserveScroll?: boolean; onSuccess?: (page: unknown) => void }
/** DropdownAction: an entry from a server action; with `confirmationMessage` it asks first. `icon` is a CSS class for an `<i>`. */
export interface DropdownActionProps { action: { url: string; method?: string; label: string; icon?: string; description?: string; variant?: string; confirmationMessage?: string } }
export interface DropdownModalLinkProps { href: string }

/**
 * Work beside its context: `main` and `aside` slots on Reka's Splitter (aside 35% by default, 20–75%, collapsible; main at least 25%; keyboard resize by 5). Below `breakpoint` (lg | xl) the aside is a Modal drawer with an opener (`mobile="sheet"`) or stays in the flow (`inline`). Aside slot props: `{ sheet, collapsed, toggle }`. Exposed `show(target?)` opens the sheet. Layout state is saved per person through `preference` (`{ key, value }`), the server `state` wins, guarded localStorage is the fallback; remount when `storageKey`/`storageScope` change.
 */
export interface SplitPaneProps { storageKey: string; storageScope: string; label: string; state?: string | null; preference?: { url: string; method: string } | null; breakpoint?: 'lg' | 'xl'; asideSize?: number; mobile?: 'sheet' | 'inline'; showMobileTrigger?: boolean; mainScroll?: boolean; compact?: boolean; stickyAside?: boolean }

/** A pane's own vertical scroll area; `chain` lets the wheel pass on to the page at either end. */
export interface PaneScrollProps { chain?: boolean }

/** Pane switches (single choice, `v-model`): not ARIA tabs, not navigation. */
export interface PaneTabsProps { tabs: { value: string; label: string }[]; label: string; modelValue: string }

/** The collapse/expand icon button for a pane; it owns no state. */
export interface PaneToggleProps { label: string; collapsed?: boolean }

/**
 * The one dropzone: drop, focused paste or browse into a hidden native file input (the form's value). Emits `files` (an empty list on a rejected selection or removing the last file); starts no upload. `accept` (MIME types) and `maxBytes` come from the server; `maxFiles` defaults to 1. `handleDrops` consumes drops, otherwise the host owns them. `compact` is overlay-only. Default slot: `{ browse }`. Exposed: `attach(files)`, `remove(index)`, `open()`.
 */
export interface BulkDropProps { name: string; label: string; accept: string[]; maxBytes: number; hasFiles: boolean; empty?: object | null; active?: boolean; multiple?: boolean; maxFiles?: number; handleDrops?: boolean; prompt?: string; hint?: string; disabled?: boolean; compact?: boolean; busy?: string }
