// The contract of the UI set behind `@kit/ui`: the props each component takes. An app's own module exports components of these
// names that accept these props (and the v-model and slots noted here), and kit pages render them unchanged.

/** A labelled field: `label` above the control in the default slot, then `description` and `error` (an alert). The control takes the same `id`. */
export interface FieldProps { id: string; label: string; description?: string; error?: string }

/** A text input (`v-model`: string | number). `invalid` sets the error state; other attributes (id, readonly, required, maxlength, aria-*) pass through. */
export interface InputProps { invalid?: boolean }

/** A multi-line input (`v-model`: string), as Input. */
export interface TextareaProps { invalid?: boolean }

/** A select (`v-model`: string): `options` are values with labels; `id` goes on the trigger. */
export interface SelectProps { id?: string; options: { value: string; label: string }[]; placeholder?: string; disabled?: boolean; invalid?: boolean }

/** A timezone select (`v-model`: an IANA zone name): Select over the zones the browser knows. */
export interface TimezoneSelectProps { id?: string; disabled?: boolean; invalid?: boolean }

/** A checkbox (`v-model`: boolean) with its label in the default slot. */
export interface CheckboxProps { id: string; disabled?: boolean }

/** A button: `primary`, `secondary` or `destructive`; a submit button unless `type` says otherwise. The default slot is its label; other attributes (disabled, @click) pass through. */
export interface ButtonProps { variant?: 'primary' | 'secondary' | 'destructive'; type?: 'submit' | 'button' }

/** The root of every page: the header (the `title` prop, or `title` here, and an `actions` slot) and the content in one column. the width is a boolean prop (`<Page sm>`: `xs`, `sm`, `md`, `lg`, `xl`, or `full`); none is full (the default: the whole shell, for lists and tables), and fixed-content pages declare a focused one. `state` swaps the content for the `loading`, `empty` or `error` slot. FocusPage is internal to it. */
export interface PageProps { xs?: boolean; sm?: boolean; md?: boolean; lg?: boolean; xl?: boolean; full?: boolean; title?: string; heading?: boolean; state?: 'ready' | 'loading' | 'empty' | 'error' }

/** The row of buttons that ends a form (default slot). No props. */
export type FormActionsProps = Record<string, never>;

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
