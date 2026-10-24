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

/** A file chooser (`v-model`: File or null); `accept` filters the picker. */
export interface FileInputProps { id: string; accept?: string; disabled?: boolean }

/**
 * The bar at the foot of a form (status, Cancel, submit; right-aligned; sticky). It reads Inertia's <Form> context, or `form` (a useForm() result). `submit` is the server's action descriptor (its label, and `disabled` with a `reason` shown in the status slot); `submitLabel` is the verb
 * for the action, never "Submit"; `busyLabel` follows it. Slots: `status` (slot props `{ state }`), `actions` (before Cancel), `submit`. Delete never goes here.
 */
export interface FormActionsProps { form?: object; submit?: { label: string; disabled?: boolean; reason?: string | null }; submitLabel?: string; busyLabel?: string; savedLabel?: string; cancelUrl?: string; cancel?: boolean; sticky?: boolean; warnOnLeave?: boolean }

/** A card for a form: a title, a six-column field grid, fieldsets, and the FormActions bar in its footer (same props). */
export interface FormCardProps extends FormActionsProps { title?: string; description?: string }

/** A group of fields inside a FormCard, with a legend and a line under it. */
export interface FormFieldsetProps { legend: string; description?: string }

/** The kit's Dialog: a confirmation or a prompt (static; never a form), `variant` danger (icon circle, destructive action) or neutral; `action` is a server action the dialog performs. The trigger is the default slot, `@confirm` runs the action; `v-model:open` opens it without one. Slots: icon, title, body, footer. Bottom-aligned on phones. */
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
export interface NumberInputProps { name: string; label: string; kind?: 'money' | 'decimal' | 'integer'; id?: string; currency?: string; locale?: string; scale?: number; signed?: boolean; grouping?: boolean; defaultValue?: string; min?: string; max?: string; step?: string; unit?: string; suffix?: string; placeholder?: string; error?: string; disabled?: boolean; required?: boolean }

/**
 * A scrolling list over a server `ScrollListViewModel` prop named `listKey` (Inertia infinite scroll, cursor paging). The default row draws a `ListItemData` (title, link, description, meta, badges, actions through RowActions); the `row` slot ({ item }) draws a richer one. "Load more" is always present while there is more; a failed load keeps the rows and offers Retry. Cursor-only, fixed selection, count-free: no sorts, segments, totals, polling, page or simple modes.
 */
export interface ScrollListProps { listKey: string; title: string; density?: 'default' | 'compact' }
