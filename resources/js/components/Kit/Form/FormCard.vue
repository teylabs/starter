<script setup lang="ts">
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import { computed, ref } from 'vue';
import FormActions from './FormActions.vue';
import { focusFirstInvalid } from './formFrame';
import { provideFormSubmit } from './formSubmit';
import { provideCard } from '@/components/Kit/Surface/surface';
import { isSubmitForm } from './submitForm';
import { provideNoticeOwner, useLockedNotice } from './useLocked';

// A card for a form: an optional title and description, a six-column grid for the fields (each Field takes a `span`), fieldsets inside it, and the
// FormActions bar in its footer. The submit and Cancel wording pass through (`submit-label`, `busy-label`, `cancel`, `cancel-url`); `actions` replaces the bar.
// Inside a locked LockedForm the footer is the notice (and the reason) instead: no submit, no Cancel, no status, no sticky bar and no leave-page warning.
// Given a useSubmitForm() result as `form` (and no Inertia <Form> around it), the card is the <form> itself: novalidate, Enter and Save call form.submit()
// with no visit, a failure moves focus to the first invalid field, and the form's `_form` message shows above the bar. `submit` stays the action descriptor.
const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
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
        form?: object;
        submit?: { label: string; disabled?: boolean; reason?: string | null };
    }>(),
    {
        title: undefined,
        description: undefined,
        submitLabel: 'Save',
        busyLabel: 'Saving…',
        savedLabel: 'Saved',
        cancelUrl: undefined,
        cancel: true,
        sticky: true,
        warnOnLeave: true,
        form: undefined,
        submit: undefined,
    },
);
provideNoticeOwner();
// A Button in the card submits its form unless it says otherwise.
provideFormSubmit();
const lock = useLockedNotice();
const context = useOptionalFormContext();
const own = computed(() => !context && isSubmitForm(props.form));
const card = ref<HTMLElement>();
const formError = computed(() =>
    own.value && isSubmitForm(props.form)
        ? (props.form.errors._form ?? '')
        : '',
);
async function onSubmit(event: Event) {
    if (!own.value || !isSubmitForm(props.form)) return;
    event.preventDefault();
    const form = props.form;
    if (await form.submit()) return;
    if (form.hasErrors && !form.conflict)
        await focusFirstInvalid(() => [card.value], form.errors);
}
// A FormCard is a card on any ground: nothing inside draws a ground card.
provideCard();
</script>
<template>
    <component
        :is="own ? 'form' : 'section'"
        ref="card"
        :novalidate="own || undefined"
        class="bg-card text-card-foreground rounded-xl border shadow-xs"
        data-form-card
        @submit="onSubmit"
    >
        <header v-if="title" class="px-6 pt-6">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p v-if="description" class="text-muted-foreground mt-1 text-sm">
                {{ description }}
            </p>
        </header>
        <div
            :class="[
                'grid grid-cols-6 gap-x-4 gap-y-6 p-6 *:col-span-6',
                title ? 'pt-5' : '',
            ]"
        >
            <slot />
        </div>
        <div
            v-if="lock.locked"
            :id="lock.id"
            class="text-muted-foreground border-t px-4 py-3 text-sm sm:px-6"
            data-form-notice
        >
            <p>{{ lock.notice }}</p>
            <p v-if="lock.reason" class="mt-1" data-form-notice-reason>
                {{ lock.reason }}
            </p>
        </div>
        <p
            v-if="formError && !lock.locked"
            role="alert"
            class="text-destructive-text px-6 text-sm"
            data-form-error
        >
            {{ formError }}
        </p>
        <slot v-if="!lock.locked" name="actions">
            <FormActions
                :form="form as never"
                :submit="submit"
                :submit-label="submitLabel"
                :busy-label="busyLabel"
                :saved-label="savedLabel"
                :dirty-label="dirtyLabel"
                :errors-label="errorsLabel"
                :up-to-date-label="upToDateLabel"
                :cancel-url="cancelUrl"
                :cancel="cancel"
                :sticky="sticky"
                :warn-on-leave="warnOnLeave"
                ><template v-if="$slots.status" #status="slotProps"
                    ><slot name="status" v-bind="slotProps" /></template
                ><template v-if="$slots.submit" #submit
                    ><slot name="submit" /></template
            ></FormActions>
        </slot>
    </component>
</template>
