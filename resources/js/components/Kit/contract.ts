// The contract of the UI set behind `@kit/ui`: the props each component takes. An app's own module exports components of these
// names that accept these props (and the v-model and slots noted here), and kit pages render them unchanged.

/** A labelled field: `name` reads its error from Inertia's <Form> context (`error` passes one), `span` (1 to 6 of the card's columns, from `sm` up) sets its width. */
export interface FieldProps {
    id: string;
    label: string;
    name?: string;
    description?: string;
    error?: string;
    span?: 1 | 2 | 3 | 4 | 5 | 6;
}

/** A text input (`v-model`: string | number). `invalid` sets the error state; `autofocus` focuses it on arrival (never a read-only one); other attributes (id, readonly, required, maxlength, aria-*) pass through. */
export interface InputProps {
    invalid?: boolean;
}

/**
 * An inline message in the page's content (slots: default body, `actions`). `variant`: info (default), success, warning, danger, primary, brand, muted; `tier`: pale (default) or
 * solid; `title` is one short line; `icon` is on by default (by variant), `false` hides it. `live`: none (default, already on the page at load), status (polite, appears after
 * something the person did) or alert (assertive, blocks them; sparingly). `dismiss` is a server descriptor and `dismissible` shows the close button: only for what can be ignored.
 */
export interface AlertProps {
    variant?:
        | 'info'
        | 'success'
        | 'warning'
        | 'danger'
        | 'primary'
        | 'brand'
        | 'muted';
    tier?: 'pale' | 'solid';
    title?: string;
    icon?: boolean;
    live?: 'none' | 'status' | 'alert';
    dismissible?: boolean;
    dismiss?: {
        url: string;
        method?: 'post' | 'put' | 'patch' | 'delete';
    } | null;
}

/**
 * An announcement laid over a stage or slide (the default slot is its content): `tone` info (default), success, warning or danger; `tier` solid (default) or pale;
 * `placement` top (default), bottom, top-left, top-right, bottom-left or bottom-right; `show` toggles it (a fade and slide, or no motion when reduced motion is set);
 * `live` polite (default) or off. Positioned inside its container, sized in container-query units, and `pointer-events: none`.
 */
export interface StageNoticeProps {
    tone?: 'info' | 'success' | 'warning' | 'danger';
    tier?: 'solid' | 'pale';
    placement?:
        | 'top'
        | 'bottom'
        | 'top-left'
        | 'top-right'
        | 'bottom-left'
        | 'bottom-right';
    show?: boolean;
    live?: 'polite' | 'off';
}

/** A page that is not built yet: `description` says what it will do, `milestone` (words only) renders "Planned for {milestone}", `links` are descriptors. A plain region, no live role. */
/** A short status label on the kit's tone tokens (AA in light and dark): `tone` neutral, info, success, warning or danger; `size` sm or md. The text carries the meaning, never the colour alone. */
export interface BadgeProps {
    tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
    size?: 'sm' | 'md';
}
export interface PlaceholderProps {
    description: string;
    milestone?: string | null;
    links?: { label: string; url: string }[];
}

/** A multi-line input (`v-model`: string), as Input. */
export interface TextareaProps {
    invalid?: boolean;
}

/** A select (`v-model`: string): `options` are values with labels; `id` goes on the trigger. */
export interface SelectProps {
    id?: string;
    name?: string;
    options: { value: string; label: string }[];
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
}

/**
 * The kit's one rich select (`v-model`: a value or `null`, or an array with `multiple`), on Reka's Combobox and non-modal. `options` are `{ value, label, description?, disabled? }` or groups `{ label, options }`.
 * `searchable` `null` turns search on past 8 options; `filter="remote"` leaves filtering to the caller (`search`, after `debounce` ms and `minSearchLength` characters; `''` on close). `clearable` is on with `multiple`;
 * `selectAll`, `chosenFirst` and `creatable` (`create`, with the typed text) are opt-in; `maxChips` swaps chips for a count. With `name` it submits hidden `name` / `name[]` inputs. `readonly` stays a focusable combobox.
 * Default strings come from the kit's `ui` lang file (`select.*`, shared as `kitUi`); `messages` overrides them per use. Slots: `option`, `chip`, `empty`, `loading`. Other attributes (id, aria-*) land on the input.
 */
export interface InputSelectProps {
    modelValue?: string | number | null | (string | number)[];
    options?:
        | {
              value: string | number;
              label: string;
              description?: string;
              disabled?: boolean;
          }[]
        | {
              label: string;
              options: {
                  value: string | number;
                  label: string;
                  description?: string;
                  disabled?: boolean;
              }[];
          }[];
    multiple?: boolean;
    name?: string;
    placeholder?: string;
    searchable?: boolean | null;
    clearable?: boolean | null;
    selectAll?: boolean;
    chosenFirst?: boolean;
    creatable?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    invalid?: boolean;
    loading?: boolean;
    filter?: 'local' | 'remote';
    debounce?: number;
    minSearchLength?: number;
    messages?: Record<string, string>;
    maxChips?: number | null;
    nativeRequired?: boolean;
    popoverClass?: string;
}

/**
 * The kit's one non-modal surface: contextual detail anchored to a trigger, on Reka's Popover (`v-model:open`, uncontrolled when unbound). Contextual detail only: text, values, short lists, links. A form goes in a `Modal`, a question in a `Dialog`.
 * `title` is a visible heading and names the panel; `label` names it with no heading; with neither it is named by the trigger. `side` (bottom), `align` (start), `offset` (8px) and `width` (`auto`, `sm` 256px, `md` 320px, `lg` 480px or `trigger`, capped at
 * the viewport minus 32px) place it; Reka does the collision handling, so keep it clear of a fixed bar with `collisionBoundary` or `collisionPadding`. Below 640px (`sheet`, on by default) it renders in `Modal` as a bottom sheet.
 * Slots: `trigger` (`{ open, close }`; one native button, to which it adds aria-expanded, aria-controls and aria-haspopup), `anchor`, `title`, `default` and `footer` (`{ close }`). `dismiss` reports `escape`, `outside`, `close` or `navigate` when the user closes it,
 * never when the parent does. An Inertia navigation closes it. The close button is named by the kit's `ui` lang file (`popover.close`); `messages` overrides it. Attributes land on the panel.
 */
export interface PopoverProps {
    title?: string;
    label?: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
    align?: 'start' | 'center' | 'end';
    offset?: number;
    width?: 'auto' | 'sm' | 'md' | 'lg' | 'trigger';
    closeButton?: boolean;
    collisionPadding?: number;
    collisionBoundary?: Element | Element[] | null;
    sheet?: boolean;
    messages?: { close?: string };
}

/** A timezone select (`v-model`: an IANA zone name): Select over the zones the browser knows. */
export interface TimezoneSelectProps {
    id?: string;
    name?: string;
    disabled?: boolean;
    invalid?: boolean;
    autofocus?: boolean;
}

/** A checkbox (`v-model`: boolean) with its label in the default slot. `id` is required: its label and a Field's error point at it. */
export interface CheckboxProps {
    id: string;
    name?: string;
    value?: string;
    disabled?: boolean;
    invalid?: boolean;
    description?: string;
}

/**
 * A button: `primary`, `secondary`, `destructive`, `ghost`, `link`, or `bar` (a ghost for controls on the shell's header bar, from `--shell-bar-foreground`); `size` default, sm or icon.
 * `type` defaults to `submit` inside Inertia's <Form> or a FormCard and to `button` elsewhere; pass it to say otherwise. The default slot is its label; other attributes (disabled, @click) pass through.
 */
/** A button, or with `href` a link styled as one: an Inertia Link (`method`, `data`, `preserveScroll`; a non-GET method renders a <button>), or a plain <a> when `external` (`rel="noopener noreferrer"` for `target="_blank"`). Never a <button> inside an <a>. */
export interface ButtonProps {
    variant?:
        'primary' | 'secondary' | 'destructive' | 'ghost' | 'link' | 'bar';
    size?: 'default' | 'sm' | 'lg' | 'xl' | 'icon';
    type?: 'submit' | 'button';
    href?: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    data?: import('./Form/Button.vue').VisitData;
    preserveScroll?: boolean;
    external?: boolean;
    target?: string;
}
/** Large tappable radio choices: native radios in cards (one tab stop, arrow keys skipping disabled options), a `radiogroup` named by its Field's label or `label`. `v-model` is the value; `name` submits it; `showResults` draws each option's `result` (0–100) as a bar and percentage, or the `result` slot. The `option` slot (`{ option, checked, disabled }`) replaces a card's body; an option with an `image` (and no slot) is a lazy square image tile named by its label or its image's `alt` (one is required); a checked tile shows a ring and a check mark. `columns` (2, 3, 4, 6 or `auto`) lays the cards in a grid. `commit="click"`: arrows only move focus; Space, Enter, a click or a tap commits and emits `commit` (once; re-selecting emits nothing). */
export interface RadioCardGroupProps {
    id: string;
    name?: string;
    options: import('./Form/RadioCardGroup.vue').RadioCardOption[];
    label?: string;
    disabled?: boolean;
    showResults?: boolean;
    invalid?: boolean;
    modelValue?: string | number | null;
    commit?: 'change' | 'click';
    columns?: 2 | 3 | 4 | 6 | 'auto';
}
/** Slider (docs/slider.md): a native range input; `v-model` is the number. Emits `input` live and `change` on release; `valueText` sets `aria-valuetext`; `showValue` shows it in a linked <output>. In a Field it takes the Field's `id` and is described by its description and error. Read-only in a locked LockedForm. */
export interface SliderProps {
    id?: string;
    min?: number;
    max?: number;
    step?: number;
    disabled?: boolean;
    invalid?: boolean;
    valueText?: (value: number) => string;
    showValue?: boolean;
    modelValue?: number;
}
/** SegmentedToggle (docs/segmented-toggle.md): a neutral single-choice view switch, a named radiogroup; `v-model` is the selected value. */
export interface SegmentedToggleProps {
    options: {
        value: string;
        label: string;
        icon?: import('vue').Component;
        disabled?: boolean;
    }[];
    label: string;
    size?: 'default' | 'sm';
    modelValue: string;
}
/** SwatchPicker (docs/swatch-picker.md): suggested swatches, a palette and a hex field; a named radiogroup; `v-model` is "#RRGGBB" or '' for the default. */
export interface SwatchPickerProps {
    label: string;
    palette: string[];
    suggested?: string[];
    suggestedLabel?: string;
    paletteLabel?: string;
    defaultValue?: string;
    background?: string;
    minContrast?: number;
    contrastHint?: string;
    name?: string;
    hexLabel?: string;
    hexLevel?: 2 | 3 | 4 | 5 | 6;
    id?: string;
}
/** A compact reaction picker: a `toolbar` of `reactions` (emoji and label; a default six), roving tabindex, emitting `react` at most once per `throttle` ms. */
export interface EmojiTrayProps {
    reactions?: { emoji: string; label: string }[];
    label?: string;
    throttle?: number;
    disabled?: boolean;
}

/** A card-like container (padding, border, radius, the card tokens) that gives what is inside its tone and shape (provideSurface): `tone` default or inverse, `shape` default or pill, `padding` none, sm or md, `as` its element, `clip` (default true: overflow-hidden; false for a sticky child), `translucent` (80% fill and a backdrop blur, solid under prefers-contrast/reduced-transparency, unblurred under reduced motion). */
export interface SurfaceProps {
    tone?: 'default' | 'inverse';
    shape?: 'default' | 'pill';
    padding?: 'none' | 'sm' | 'md';
    as?: string;
    clip?: boolean;
    translucent?: boolean;
}

/** The trail above a page's heading: the page's ancestors from its `layout.breadcrumbs` (or `crumbs`), the current page left out; with none, an aria-hidden spacer of the same height, so a heading sits at the same height on every page. */
export interface BreadcrumbsProps {
    crumbs?: Array<{
        label: string;
        url: string | null;
        home: boolean;
        current: boolean;
        title?: string | null;
    }>;
}

/** The root of every page: in the shell, the trail (Breadcrumbs) above the header, drawn whether or not there is a heading; then the header (the `title` prop, or `title` here, and an `actions` slot) and the content in one column. the width is a boolean prop (`<Page sm>`: `xs`, `sm`, `md`, `lg`, `xl`, or `full`); none is full (the default: the whole shell, for lists and tables), and fixed-content pages declare a focused one. `state` swaps the content for the `loading`, `empty` or `error` slot. FocusPage is internal to it. Rhythm: the page gaps its visible direct children (`gap-6`; out-of-flow children take none), so children carry no outer margins; `flush` turns the gap off, for one full-bleed child. */
export interface PageProps {
    xs?: boolean;
    sm?: boolean;
    md?: boolean;
    lg?: boolean;
    xl?: boolean;
    full?: boolean;
    flush?: boolean;
    title?: string;
    heading?: boolean;
    state?: 'ready' | 'loading' | 'empty' | 'error';
}

/** A card for a group of fields: optional `title` and `description` above, the content in the default slot. */
export interface SectionProps {
    title?: string;
    description?: string;
}

// The menu and sheet primitives are the shadcn-vue ones (`DropdownMenu*`, `Sheet*`): Reka UI-shaped, with their usual props and slots.

/** A password input with a show/hide toggle; the other attributes pass to the input. */
export interface PasswordInputProps {
    invalid?: boolean;
    rules?: boolean;
}

/** The header's light/dark switch (no props) and the appearance choice (light, dark or system, no props): both read and write the one appearance setting. `useAppearance()` and `initializeTheme()` are exported with them. */
export type AppearanceProps = Record<string, never>;

/** A settings section's icon by its lucide name (kebab case); an unknown name is the generic Settings icon. */
export interface SectionIconProps {
    name?: string;
}

/** The frame of a settings section's page: the sidebar of the sections the user may open (the page's `layout.navigation`, `aria-current` on the shown one), and the content in the default slot. */
export type SettingsFrameProps = Record<string, never>;

/** A person's photo (`src`, `src2x`) or their initials when there is none; `large` is the profile size. */
export interface AvatarProps {
    initials: string;
    src?: string | null;
    src2x?: string | null;
    large?: boolean;
}

/** A file chooser (`v-model`: File or null); `accept` filters the picker. `current` is the record's file (name, size, link), shown in place of the picker inside a locked LockedForm. */
export interface FileInputProps {
    id: string;
    accept?: string;
    disabled?: boolean;
    invalid?: boolean;
    current?: {
        name: string;
        size?: number | null;
        url?: string | null;
    } | null;
}

/**
 * The bar at the foot of a form (status, Cancel, submit; right-aligned; sticky). It reads Inertia's <Form> context, or `form` (a useForm() or useSubmitForm() result). `submit` is the server's action descriptor (its label, and `disabled` with a `reason` shown in the status slot); `submitLabel` is the verb
 * for the action, never "Submit"; `busyLabel` follows it. `dirtyLabel`, `errorsLabel`, `upToDateLabel` and `savedLabel` replace the status lines. `warnOnLeave` false registers no leave guard at all. Slots: `status` (slot props `{ state }`), `actions` (before Cancel), `submit`. Delete never goes here.
 */
export interface FormActionsProps {
    form?: object;
    submit?: { label: string; disabled?: boolean; reason?: string | null };
    submitLabel?: string;
    busyLabel?: string;
    savedLabel?: string;
    dirtyLabel?: string;
    errorsLabel?: string;
    upToDateLabel?: string;
    cancelUrl?: string;
    cancel?: boolean;
    sticky?: boolean;
    warnOnLeave?: boolean;
}

/** A card for a form: a title, a six-column field grid, fieldsets, and the FormActions bar in its footer (same props). Given a useSubmitForm() as `form`, it is the <form> itself. */
export interface FormCardProps extends FormActionsProps {
    title?: string;
    description?: string;
}

/**
 * A form's persistent confirmation after a submit, not a toast: an Alert with `live="status"` (role status, polite) that never times out. The text is `message` when given
 * (null shows nothing), otherwise the flashed `status` page prop. `tone`: success (default), info or warning, as Alert's variants. It hides on the next Inertia visit's start
 * or while `form` (a useForm() result) is processing, and shows again only if the next response still carries a status. Nothing renders without a message.
 */
export interface FormLinkProps {
    href: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    data?: Record<string, unknown>;
    target?: string;
    as?: string | object;
    disabled?: boolean;
}
export interface FormStatusProps {
    message?: string | null;
    flashKey?: string;
    tone?: 'success' | 'info' | 'warning';
    form?: { processing: boolean } | null;
}

/**
 * A titled group of fields inside a FormCard: a native fieldset whose legend holds `title` as a heading at `level` (2 to 6, default 2), `description` after the legend (aria-describedby),
 * `legendHidden` for screen readers only, `separator` (`rule`, default, or `space`) between sibling groups, `fullwidth` to stack the fields. Slots: `title`, `description`, default (the fields). `legend` is the old name of `title`, until 0.4.
 */
export interface FormFieldsetProps {
    title?: string;
    legend?: string;
    description?: string;
    level?: 2 | 3 | 4 | 5 | 6;
    legendHidden?: boolean;
    separator?: 'rule' | 'space';
    fullwidth?: boolean;
}

/**
 * A form someone may read but not change: locked when `save`, the page's save descriptor, is `null` (the server decides). Every kit control inside turns read-only (never disabled), kit edit
 * actions and `intent: 'edit'` descriptors are hidden, each FormCard shows the notice in place of FormActions and nothing submits. `quantity` picks the plural (default) or singular notice,
 * `notice` replaces it, `reason` adds a muted line. Slot props `{ locked }`. `EditOnly` (no props) renders its slot only while editable; `useLocked()` returns `{ locked, notice, reason }`.
 */
export interface LockedFormProps {
    save: object | null;
    quantity?: 'many' | 'one';
    notice?: string;
    reason?: string | null;
}
export type EditOnlyProps = Record<string, never>;

/** The kit's Dialog: a confirmation or a prompt (static; never a form), `variant` danger (icon circle, destructive action) or neutral; `icon` false hides the circle on any variant and sets the content flush; `action` is a server action the dialog performs. The trigger is the default slot, `@confirm` runs the action; `v-model:open` opens it without one; `@closed` fires after it has closed and returned focus. Slots: icon, title, body, footer. Bottom-aligned on phones. */
/** A confirmation on Reka's AlertDialog. `confirmText` asks for that exact text to be typed (case-sensitive unless `caseSensitive` is false) before Confirm enables; the typed text is emitted with `confirm` and sent as `confirmation` with the `action` visit, for TypedConfirmation to re-check on the server. */
export interface DialogProps {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'danger' | 'neutral';
    action?: { url: string; method: string };
    confirmText?: string | null;
    caseSensitive?: boolean;
    confirmTextLabel?: string;
    icon?: boolean;
}

/**
 * A server-driven index table (TanStack headless, Tailwind). `table` is the server's TableData: rows, columns, sort, search, filters, tabs, pagination and the empty state, with each
 * row's `actions` already decided, plus the filter `choices` the toolbar shows and the `bulkActions` that make rows selectable. Every change is an Inertia visit with the state in the URL.
 * Slots: `cell-<key>` ({ row, value }), `row-actions`, `toolbar`, `toolbar-end`, `empty-first-use`, `empty-no-results`. `intercept` names row and bulk actions the page performs itself
 * (`@act`, `@bulk` with the selected ids). Nothing here checks a permission. `rowActions.variant` lays out each row's actions: `split` (the primary joined to a chevron
 * menu) or `overflow` (quiet inline actions plus "…"); it wins over the server's `table.rowActions`, and without either a row of one primary plus menu items is split.
 */
export interface DataTableProps {
    table: object;
    itemKey?: string;
    stickyHeader?: boolean;
    searchLabel?: string;
    intercept?: string[];
    toolbar?: boolean;
    rowLabel?:
        | string
        | ((row: Record<string, unknown>) => string | null | undefined)
        | null;
    rowActions?: { variant?: 'split' | 'overflow' | null } | null;
}

/** The index toolbar: search (300ms after typing, Enter at once) and one choice per server filter `choice`. It emits `search` and `filter` (name, value or null); the host visits. Slots: default (after the filters), `end`. */
export interface IndexToolbarProps {
    search?: string | null;
    filters?: Record<string, string>;
    choices?: {
        key: string;
        label: string;
        options: { value: string; label: string }[];
    }[];
    searchLabel?: string;
    searchable?: boolean;
}

/** The selected rows' bar: a floating FloatingToolbar (registered with useStickyBars) with the count, the server's bulk actions and Clear, shown while `ids` is not empty. An action posts `ids[]` after its `confirm`; `@clear` on success. */
export interface BulkBarProps {
    ids: (string | number)[];
    actions: import('./Action/actionPlacement').ActionDescriptor[];
    intercept?: string[];
    label?: string;
}

/** Vertical rhythm between sibling blocks: `flex flex-col gap-4` (`class="gap-6"` for a deliberate other rhythm). Vertical only. Spacing has one owner: siblings carry no margins. `as` / `asChild` as Reka's Primitive. */
export interface StackProps {
    as?: string;
    asChild?: boolean;
}

/** @deprecated BlockStack is now Stack (same props); the alias is removed in the next release. */
export type BlockStackProps = StackProps;

/** One titled region of a page: a `<section>` named by `title`, with a SectionHeader (`title`, `description`, descriptor `actions`, heading `level`) above its stacked content. No outer margin. Event: `action`. */
export interface PageSectionProps {
    title?: string;
    description?: string | null;
    actions?: import('./Action/actionPlacement').ActionDescriptor[];
    level?: 2 | 3 | 4 | 5 | 6;
}

/** Page columns: 12 from `md`, stacked below it. `preset` sizes the cells: `main-aside` (8 and 4), `halves` (6 each), `thirds` (4 each); otherwise each cell spans 12 or its PageGridItem `span`. */
export interface PageGridProps {
    preset?: 'main-aside' | 'halves' | 'thirds';
}

/** One PageGrid cell: `span` (1 to 12) columns from `md`, overriding the grid's preset; the full width below `md`. `as` / `asChild` as Reka's Primitive. */
export interface PageGridItemProps {
    as?: string;
    asChild?: boolean;
    span?: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12;
}

/** Inline controls in one row that owns the gap and alignment: `overflow` (`wrap` | `scroll`) and `layout` (`inline` | `heading`). It never styles its children. */
export interface ToolbarProps {
    as?: string;
    asChild?: boolean;
    overflow?: 'wrap' | 'scroll';
    layout?: 'inline' | 'heading';
}

/**
 * The kit's Modal: content and forms in a layer over the page, ONE component for a server-driven modal page (inertiaui/modal, detected automatically) and for local use with `v-model:open`.
 * A fixed header, a scrolling body and a footer; with `submit` (the server's action) it is a form with FormActions as the sticky footer. Closing asks first with unsaved edits; the backdrop never closes it.
 * `size` sm | md | lg | xl | full; `slideover`; bottom sheet (sm, md) or full screen (the rest) on phones.
 */
export interface ModalProps {
    title: string;
    description?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
    placement?: 'center' | 'slideover' | 'drawer';
    as?: 'form' | 'div';
    submit?: { label: string; url: string; method: string };
    dirty?: boolean;
    busyLabel?: string;
    cancelLabel?: string;
    showCloseButton?: boolean;
    initialFocus?: 'field' | 'body';
    confirmMessage?: string;
}

/**
 * A repeating group of rows posted as native bracket paths (`name[0][field]`). `rows` carry string ids and a server-supplied `template` (without an id) is cloned for each added row, which gets a `new:` id. Deletion is staged in the form, never a request. `removable` is `true`, `false` or a server descriptor `{ label, rowIds }` that decides which ids show a remove control (visibility only: save must re-authorize). `limits` bound the row count. `errors` default to the Inertia form's. Reordering is by move buttons. One nested Repeater is supported.
 * Slots: `row` ({ row, index, name(field), errors }), `row-title`, `row-actions`, `row-menu`, `before-rows`, `empty`. Event: `change` (the stable row ids in order). `columns` switches to an input-grid layout (one header row, one cell per column; narrow containers stack); `gridClass`, `headerClass` and `rowClass` shape it, `flush` removes the card padding. `actionsInMenu` moves row actions and Remove into a menu. `externalAdd` hides the built-in add button; the page calls the exposed `add(overrides?)` itself.
 */
export interface RepeaterProps<T extends { id: string } = { id: string }> {
    name: string;
    rows: T[];
    template?: Omit<T, 'id'>;
    limits?: { min?: number; max?: number };
    addLabel: string;
    itemLabel: string;
    reorderable?: boolean;
    actionsInMenu?: boolean;
    removable?: boolean | { label: string; rowIds: string[] };
    errors?: Record<string, string>;
    flush?: boolean;
    externalAdd?: boolean;
    columns?: string[];
    gridClass?: string;
    headerClass?: string;
    rowClass?: string;
}

/**
 * Free-text tags as chips, on InputSelect (multiple, creatable): a separator (`,` `;` by default) or Enter adds the typed tag (Enter takes a suggestion only after the arrow keys move to it), a paste with separators or line breaks
 * adds each piece, Backspace on an empty field selects the last chip and again removes it. Tags are tidied (spaces collapsed, then `normalize`); a duplicate (same tag ignoring case in
 * the locale), one longer than `maxLength`, one not matching `pattern` (the whole tag) or one past `max` is refused with the reason under the field, never trimmed.
 * `suggestions` are offered and filtered locally. v-model: string[], submitted as `<name>[]`. Caller attributes land on the input.
 */
export interface InputDateProps {
    name: string;
    label: string;
    id?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
    defaultValue?: string;
    min?: string;
    max?: string;
}
export interface InputDateRangeProps {
    name: string;
    label: string;
    id?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
    defaultValue?: { start: string; end: string };
    min?: string;
    max?: string;
}
export interface InputMonthProps {
    name: string;
    label: string;
    id?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
    defaultValue?: string;
    min?: string;
    max?: string;
    placeholder?: string;
}
export interface InputYearProps {
    name: string;
    label: string;
    id?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
    defaultValue?: string;
    min?: string;
    max?: string;
    placeholder?: string;
}
export interface InputTimeProps {
    name: string;
    label: string;
    id?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
    defaultValue?: string;
    min?: string;
    max?: string;
    step?: number;
}
export interface InputTimeRangeProps {
    name: string;
    label: string;
    id?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
    defaultValue?: { start: string; end: string };
    min?: string;
    max?: string;
    step?: number;
}
export interface InputTagsProps {
    name?: string;
    suggestions?: string[];
    separators?: string[];
    max?: number | null;
    maxLength?: number | null;
    pattern?: string | null;
    normalize?: ((tag: string) => string) | null;
    locale?: string;
    placeholder?: string;
    disabled?: boolean;
    readonly?: boolean;
    invalid?: boolean;
    messages?: Partial<import('./Form/tags').TagsMessages>;
}

/**
 * A postal address as one named group (a FormFieldset whose legend is `title`): street, unit, city, region, postal code and country, submitted as `<name>[line1]` …
 * `<name>[country]` and reading errors as `<name>.<part>`. `countries` lists the choices (CA and US by default); a fixed `country` hides that field. The region and
 * postal-code labels follow the country; text fields carry autocomplete tokens; a Canadian postal code is written A1A 1A1 on blur, and an unlikely CA or US code shows
 * a hint, never a block. Regions are CA and US codes ("ON", "NY"), named in French for a French `locale`; other countries take free text. v-model: an Address.
 */
export interface AddressFieldsetProps {
    name?: string;
    title?: string;
    description?: string;
    level?: 2 | 3 | 4 | 5 | 6;
    countries?: string[];
    country?: string | null;
    locale?: string;
    required?: boolean;
    disabled?: boolean;
    messages?: Partial<import('./Form/address').AddressMessages>;
}

/** A province, territory or state for CA or the US (searchable InputSelect, ISO 3166-2 codes without the prefix); free text for any other country. v-model: the code. */
export interface RegionSelectProps {
    country?: string | null;
    locale?: string;
    id?: string;
    name?: string;
    disabled?: boolean;
    invalid?: boolean;
    placeholder?: string;
}

/** A postal address for reading in an <address> element. `hideCountry` drops the country line; `mapUrl` (the app's own URL builder) adds a map link. No provider by default. */
export interface AddressTextProps {
    address: import('./Form/address').Address | null | undefined;
    locale?: string;
    hideCountry?: boolean;
    mapUrl?:
        ((address: import('./Form/address').Address) => string | null) | null;
    messages?: Partial<import('./Form/address').AddressMessages>;
}

/**
 * An exact-number input. The visible field is labelled and unnamed; a hidden control carries `name` and the canonical string (money in integer minor units, percent in percentage points at a fixed `scale` ("12.5" is 12.5 %, never the fraction 0.125), decimals at a fixed `scale`, integers as digits; empty stays empty). Excess precision is invalid, never rounded, and blocks submission. `min`, `max` and `step` are canonical strings and client hints only: check them again on the server. `locale` and `currency` come from the props, else the host's shared `kitNumber` (config `kit.currency` and the app locale), else the browser's language (then en-CA; Latin digits only). Money never guesses a currency: without one it shows no symbol and warns in development. The currency or percent symbol sits outside the editable text in the locale's position, and the label names the unit ("Price, CAD"). `negative` shows negatives with a minus (default) or accounting parentheses after blur. Inside a Repeater row it reads its error from the row. Event: `canonical` (the exact string).
 */
export interface InputNumberProps {
    name: string;
    label: string;
    kind?: 'money' | 'percent' | 'decimal' | 'integer';
    negative?: 'minus' | 'parentheses';
    id?: string;
    currency?: string;
    locale?: string;
    scale?: number;
    signed?: boolean;
    grouping?: boolean;
    defaultValue?: string;
    min?: string;
    max?: string;
    step?: string;
    unit?: string;
    suffix?: string;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
}
/**
 * Money or a percentage for display: the canonical string InputNumber submits (money in integer minor units, percent in percentage points), with the locale's grouping,
 * decimal and symbol placement, in tabular figures. `negative="parentheses"` is the accounting form, with "negative" for screen readers. Locale and currency resolve as
 * InputNumber's do; money without a currency shows no symbol. An empty value renders nothing.
 */
export interface AmountProps {
    value: string | null | undefined;
    kind: 'money' | 'percent';
    currency?: string;
    locale?: string;
    scale?: number;
    negative?: 'minus' | 'parentheses';
}
/** @deprecated Use InputNumberProps; removed in 0.4. */
export type NumberInputProps = InputNumberProps;

/**
 * A scrolling list over a server `ScrollListViewModel` prop named `listKey` (Inertia infinite scroll, cursor paging). The default row draws a `ListItemData` (title, link, description, meta, badges, actions through RowActions); the `row` slot ({ item }) draws a richer one. "Load more" is always present while there is more; a failed load keeps the rows and offers Retry. Cursor-only, fixed selection, count-free: no sorts, segments, totals, polling, page or simple modes.
 */
export interface ScrollListProps {
    listKey: string;
    title: string;
    density?: 'default' | 'compact';
}

/**
 * A centred pill of page actions and status: `mode` auto (inline under short content, floating while long content scrolls, settling at its container's end), float or inline.
 * `reveal` is a selector whose absence from view sets `v-model:revealed` (also the default slot's `{ revealed }`), so the host repeats header actions only then. The slot takes
 * ActionBar (every action is a server descriptor, so on phones it overflows into the ⌄ menu) and STATUS only (text, the save state), never a control of its own: it cannot
 * overflow and would clip, so a button or link slotted directly warns in development. It reads no store (but the Workbench selection in `stage` mode). It provides the inverse tone and pill shape (`provideSurface` / `useSurface`) to the controls inside. FloatingToolbarDot separates groups.
 * `items` are client-side actions (ToolbarAction: key, label, icon, disabled, reason; no URL) handed to `onAction` (`@action`) by the toolbar's own handler. Inside a
 * Workbench stage, `stage` floats over the selected elements (`data-workbench-element`; above, flipping below, clamped inside the stage) and `docked` renders in the stage toolbar.
 */
export interface FloatingToolbarProps {
    mode?: 'auto' | 'float' | 'inline' | 'stage' | 'docked';
    reveal?: string;
    label?: string;
    revealed?: boolean;
    items?: import('./Layout/toolbarAction').ToolbarAction[];
    onAction?: (action: import('./Layout/toolbarAction').ToolbarAction) => void;
}

/**
 * A disclosure section: a heading whose real <button> toggles the content (`aria-expanded`, `aria-controls` naming the region, which is hidden while closed). `title` or the
 * `summary` slot labels it, `actions` sits beside it; `v-model:open`, else `defaultOpen`; `level` is the heading level (default 3); `id` seeds the trigger and content ids.
 */
export interface CollapsibleProps {
    title?: string;
    defaultOpen?: boolean;
    open?: boolean;
    level?: 2 | 3 | 4 | 5 | 6;
    id?: string;
}
/** Term and description pairs as a real `<dl>`: `items` (or the default slot), `layout` inline (side by side from `sm`, stacked on a phone) or stacked, `copyable` (or an item's `copy`) for a copy button with a polite status, `empty` for a blank value (an em dash). */
export interface DescriptionListProps {
    items?: {
        term: string;
        description: string | number | null;
        key?: string;
        copy?: boolean;
    }[];
    layout?: 'inline' | 'stacked';
    copyable?: boolean;
    empty?: string;
}
/** Nothing here yet: `title`, `body`, `icon`, and the next step as the `action` slot or server `actions` (an ActionBar; `action` is re-emitted with the descriptor passed). `compact` for a panel or list. A plain region labelled by its title, never a live region. */
export interface EmptyStateProps {
    title: string;
    body?: string | null;
    icon?: import('vue').Component | null;
    actions?: import('./Action/actionPlacement').ActionDescriptor[];
    compact?: boolean;
    level?: 2 | 3 | 4 | 5 | 6;
}

/**
 * A full-width bar pinned to its container's foot while the container's end is below the fold, then back in flow: the page's own controls (a pager, totals,
 * kept filters). FloatingToolbar is for page actions. While pinned it registers with useStickyBars. `surface` keeps the card look unpinned. Slot props `{ pinned }`.
 */
export interface StickyBarProps {
    sticky?: boolean;
    surface?: boolean;
}

/** The end of a long page (shown only past one and a half screens, hidden under a pinned form bar): Back to top, a `sections` jump list, and `pagination` / `actions` slots. */
export interface PageEndProps {
    sections?: { id: string; label: string }[];
    label?: string;
    topLabel?: string;
    sectionsLabel?: string;
}

/** A search combobox over the server's grouped results (`url` answers JSON `{ groups, partial }`); Enter opens the active result or the full results page. Exposes `focus()`; emits `navigate`. */
export interface SearchBoxProps {
    url: string;
    label?: string;
    placeholder?: string;
    minLength?: number;
}

/**
 * Server action descriptors as button groups: declared `group`/`menu` (layoutActions: menu-bearing groups last), else the placement rule (placeActions). Dialog intent opens the
 * route modal, document intent a new tab, ordinary GETs navigate; other actions are emitted (`@action`) for the host to run. `busy` blocks all but a standalone document link;
 * `dialogOpen` holds menu focus for the host's dialog; `primary` fills each group's leading segment. When the full layout does not fit
 * the bar's own width (a ResizeObserver on its root, never the viewport), it compacts to the main action and a ⌄ menu (fitActions).
 */
export interface ActionBarProps {
    actions: import('./Action/actionPlacement').ActionDescriptor[];
    busy?: boolean;
    dialogOpen?: boolean;
    primary?: boolean;
}
/** ButtonGroup (ButtonGroup.md): raw `button`/`a`/`Link` children in the default slot, styled by the group; menu entries in `menu`; `label` names the trigger; `offset` is [skid, gap]. */
export interface ButtonGroupProps {
    label?: string | null;
    offset?: [number, number];
}

/**
 * The one header for a section, widget, card or pane: `title` as a heading at `level` (default 2), `count` (zero shown, null hidden), `badge` and `avatar` descriptors, `meta` and
 * `description`; the `status` slot and the descriptor `actions` (ActionBar) on the other side. `@action` emits the original descriptor, matched by key.
 */
export interface SectionHeaderProps {
    title: string;
    count?: number | null;
    actions?: import('./Action/actionPlacement').ActionDescriptor[];
    level?: 1 | 2 | 3 | 4 | 5 | 6;
    badge?: { label: string; variant: string } | null;
    meta?: string | null;
    description?: string | null;
    avatar?: {
        initials: string;
        src?: string | null;
        src2x?: string | null;
    } | null;
    /** `page` sets the title at the page heading's scale, for a page that heads itself. Default `section`. */
    size?: 'section' | 'page';
}

/**
 * The menu family (Dropdown.md, DropdownItem.md and the DropdownLink/Confirm/Action/Delete/ModalLink specs, ButtonDropdown.md). Menus: `Dropdown` (`type="button"` for the labelled trigger, else an icon one; `label`, `alignLeft`, `horizontal`, `chevron`, `spin`,
 * `colorClasses`, `menuClasses`; slots default, `header`, `buttontext`, `trigger`; attributes land on the trigger) and `ButtonDropdown` (`label`, `offset` [skid, gap]; slots default, `menu`, `menuheader`). Items must sit inside a kit menu. As specced, `active` (DropdownItem,
 * DropdownLink) and `noun` (DropdownConfirm) do nothing, and DropdownLink's button form is always `type="submit"`.
 */
export interface DropdownProps {
    alignLeft?: boolean;
    horizontal?: boolean;
    type?: string;
    label?: string;
    colorClasses?: string;
    chevron?: boolean;
    spin?: boolean;
    menuClasses?: string;
}
export interface ButtonDropdownProps {
    label?: string | null;
    offset?: [number, number];
}
/** DropdownItem: `as` "button" (a native button), "a" (a plain anchor) or anything else (an Inertia link; `inertia` with "button" makes an Inertia link rendered as a button); slots default, `icon`, `caption`, `wrapper` ({ close }). */
export interface DropdownItemProps {
    href?: string;
    as?: string | object | null;
    inertia?: boolean;
    active?: boolean;
}
export interface DropdownLinkProps {
    href?: string;
    as?: string | object | null;
    active?: boolean;
}
/** DropdownConfirm: confirming runs `onConfirmed` or an Inertia visit to `href` (`method`), then closes the dialog and the menu; `variant="danger"` for the destructive button. Slots default, `icon`, `caption`, `title`, `confirmation`. */
export interface DropdownConfirmProps {
    href?: string;
    method?: string;
    onConfirmed?: () => unknown;
    confirmLabel?: string;
    variant?: string;
    noun?: string;
}
/** DropdownDelete: confirming sends an Inertia DELETE to `href`; emits `confirming`, `success`, `error`. Slots default, `description`, `title`, `message`, `caption` (`context` is its default). */
export interface DropdownDeleteProps {
    href?: string;
    noun?: string;
    context?: string;
    deleteLabel?: string;
    preserveState?: boolean;
    preserveScroll?: boolean;
    onSuccess?: (page: unknown) => void;
}
/** DropdownAction: an entry from a server action; with `confirmationMessage` it asks first. `icon` is a CSS class for an `<i>`. */
export interface DropdownActionProps {
    action: {
        url: string;
        method?: string;
        label: string;
        icon?: string;
        description?: string;
        variant?: string;
        confirmationMessage?: string;
    };
}
export interface DropdownModalLinkProps {
    href: string;
}

/**
 * Work beside its context: `main` and `aside` slots on Reka's Splitter (aside 35% by default, 20–75%, collapsible; main at least 25%; keyboard resize by 5). Below `breakpoint` (lg | xl) the aside is a Modal drawer with an opener (`mobile="sheet"`) or stays in the flow (`inline`). Aside slot props: `{ sheet, collapsed, toggle }`. Exposed `show(target?)` opens the sheet. Layout state is saved per person through `preference` (`{ key, value }`), the server `state` wins, guarded localStorage is the fallback; remount when `storageKey`/`storageScope` change.
 */
export interface SplitPaneProps {
    storageKey: string;
    storageScope: string;
    label: string;
    state?: string | null;
    preference?: { url: string; method: string } | null;
    breakpoint?: 'lg' | 'xl';
    asideSize?: number;
    mobile?: 'sheet' | 'inline';
    showMobileTrigger?: boolean;
    mainScroll?: boolean;
    compact?: boolean;
    stickyAside?: boolean;
}

/** A pane's own vertical scroll area; `chain` lets the wheel pass on to the page at either end. */
export interface PaneScrollProps {
    chain?: boolean;
}

/** Pane switches (single choice, `v-model`): not ARIA tabs, not navigation. */
export interface PaneTabsProps {
    tabs: { value: string; label: string }[];
    label: string;
    modelValue: string;
}

/** The collapse/expand icon button for a pane; it owns no state. */
export interface PaneToggleProps {
    label: string;
    collapsed?: boolean;
}

/**
 * The one dropzone: drop, focused paste or browse into a hidden native file input (the form's value). Emits `files` (an empty list on a rejected selection or removing the last file); starts no upload. `accept` (MIME types) and `maxBytes` come from the server; `maxFiles` defaults to 1. `handleDrops` consumes drops, otherwise the host owns them. `compact` is overlay-only. Default slot: `{ browse }`. Exposed: `attach(files)`, `remove(index)`, `open()`.
 */
export interface BulkDropProps {
    name: string;
    label: string;
    accept: string[];
    maxBytes: number;
    hasFiles: boolean;
    empty?: object | null;
    active?: boolean;
    multiple?: boolean;
    maxFiles?: number;
    handleDrops?: boolean;
    prompt?: string;
    hint?: string;
    disabled?: boolean;
    compact?: boolean;
    busy?: string;
}

/** A date or time for the viewer: a <time> with the machine value and the full date in its title. A calendar date (`YYYY-MM-DD`) never shifts between zones; an instant (RFC 3339 with its offset) shows in the viewer's zone (`kitTimezone`). */
export interface DateTimeProps {
    value: string;
    format?: 'relative' | 'local' | 'datetime' | 'date' | 'time' | 'ymd';
    timezone?: string;
    locale?: string;
    now?: number | Date;
    live?: boolean;
    relativeUntilDays?: number;
}
