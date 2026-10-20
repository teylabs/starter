// The contract of the UI set behind `@kit/ui`: the props each component takes. An app's own module exports components of these
// names that accept these props (and the v-model and slots noted here), and kit pages render them unchanged.

/** A labelled field: `name` reads its error from Inertia's <Form> context (`error` passes one), `span` (1 to 6 of the card's columns, from `sm` up) sets its width. */
export interface FieldProps { id: string; label: string; name?: string; description?: string; error?: string; span?: 1 | 2 | 3 | 4 | 5 | 6 }

/** A text input (`v-model`: string | number). `invalid` sets the error state; other attributes (id, readonly, required, maxlength, aria-*) pass through. */
export interface InputProps { invalid?: boolean }

/** A multi-line input (`v-model`: string), as Input. */
export interface TextareaProps { invalid?: boolean }

/** A select (`v-model`: string): `options` are values with labels; `id` goes on the trigger. */
export interface SelectProps { id?: string; name?: string; options: { value: string; label: string }[]; placeholder?: string; disabled?: boolean; invalid?: boolean }

/** A timezone select (`v-model`: an IANA zone name): Select over the zones the browser knows. */
export interface TimezoneSelectProps { id?: string; name?: string; disabled?: boolean; invalid?: boolean }

/** A checkbox (`v-model`: boolean) with its label in the default slot. */
export interface CheckboxProps { id: string; name?: string; value?: string; disabled?: boolean }

/** A button: `primary`, `secondary` or `destructive`; a submit button unless `type` says otherwise. The default slot is its label; other attributes (disabled, @click) pass through. */
export interface ButtonProps { variant?: 'primary' | 'secondary' | 'destructive'; type?: 'submit' | 'button' }

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

/** The frame of a settings section's page: the sidebar of the sections the user may open (the server's `settings_nav`, `aria-current` on the shown one), and the content in the default slot. */
export type SettingsFrameProps = Record<string, never>;

/** A person's photo (`src`, `src2x`) or their initials when there is none; `large` is the profile size. */
export interface AvatarProps { initials: string; src?: string | null; src2x?: string | null; large?: boolean }

/** A file chooser (`v-model`: File or null); `accept` filters the picker. */
export interface FileInputProps { id: string; accept?: string; disabled?: boolean }

/**
 * The bar at the foot of a form (status, Cancel, submit; right-aligned; sticky). It reads Inertia's <Form> context, or `form` (a useForm() result). `submitLabel` is the verb
 * for the action, never "Submit"; `busyLabel` follows it. Slots: `status` (slot props `{ state }`), `actions` (before Cancel), `submit`. Delete never goes here.
 */
export interface FormActionsProps { form?: object; submitLabel?: string; busyLabel?: string; savedLabel?: string; cancelUrl?: string; cancel?: boolean; sticky?: boolean; warnOnLeave?: boolean }

/** A card for a form: a title, a six-column field grid, fieldsets, and the FormActions bar in its footer (same props). */
export interface FormCardProps extends FormActionsProps { title?: string; description?: string }

/** A group of fields inside a FormCard, with a legend and a line under it. */
export interface FormFieldsetProps { legend: string; description?: string }

/** The confirmation a destructive action goes through (Reka AlertDialog): the trigger is the default slot, `@confirm` runs the action; `v-model:open` opens it without a trigger. */
export interface ConfirmDialogProps { title: string; description?: string; confirmLabel?: string; cancelLabel?: string; destructive?: boolean }
