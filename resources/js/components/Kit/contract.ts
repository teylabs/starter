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

/** A checkbox (`v-model`: boolean) with its label in the default slot. In the shell it renders the page header (the `title` prop and an `actions` slot) above the content. */
export interface CheckboxProps { id: string; disabled?: boolean }

/** A button: `primary`, `secondary` or `destructive`; a submit button unless `type` says otherwise. The default slot is its label; other attributes (disabled, @click) pass through. */
export interface ButtonProps { variant?: 'primary' | 'secondary' | 'destructive'; type?: 'submit' | 'button' }

/** The page measure: a centred column at `xs` (42rem), `sm` (48rem), `md` (64rem), `lg` (80rem, the default) `xl` (96rem) or `full` (no cap, for fluid data pages), in the default slot. It adds no padding: the shell owns the gutters. */
export interface FocusPageProps { size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'full'; title?: string; heading?: boolean }

/** The row of buttons that ends a form (default slot). No props. */
export type FormActionsProps = Record<string, never>;

/** A card for a group of fields: optional `title` and `description` above, the content in the default slot. */
export interface SectionProps { title?: string; description?: string }

// The menu and sheet primitives are the shadcn-vue ones (`DropdownMenu*`, `Sheet*`): Reka UI-shaped, with their usual props and slots.
