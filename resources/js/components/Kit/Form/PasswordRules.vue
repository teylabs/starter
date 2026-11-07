<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';

/*
 * The password rules, shown under a new-password field before anything is typed: the length (ticked as soon as it is met) and, when the policy checks for it,
 * that the password isn't in a known data breach, which only the server can check on submit. The numbers come from the rule in force (`kitPassword`, shared
 * by session-ui from Password::defaults()), so the field and the server never disagree; without it, the kit's default (8, breach-checked) is shown.
 */
const props = defineProps<{ id: string; value?: string | null }>();
const policy = computed(() => {
    const shared = (
        usePage().props as {
            kitPassword?: { min?: number; uncompromised?: boolean };
        }
    ).kitPassword;
    return {
        min: shared?.min ?? 8,
        uncompromised: shared?.uncompromised ?? true,
    };
});
const long = computed(
    () => [...(props.value ?? '')].length >= policy.value.min,
);
</script>
<template>
    <ul
        :id="id"
        class="text-muted-foreground mt-1.5 grid gap-1 text-sm"
        data-password-rules
    >
        <li
            class="flex items-center gap-1.5"
            :data-met="long || undefined"
            data-password-rule="length"
        >
            <Check
                v-if="long"
                class="text-primary size-3.5"
                aria-hidden="true"
            /><Circle v-else class="size-3.5" aria-hidden="true" />
            <span
                >At least {{ policy.min }} characters<span class="sr-only">{{
                    long ? ' (met)' : ' (not yet met)'
                }}</span></span
            >
        </li>
        <li
            v-if="policy.uncompromised"
            class="flex items-center gap-1.5"
            data-password-rule="breach"
        >
            <Circle class="size-3.5" aria-hidden="true" /><span
                >Not found in a known data breach (checked when you
                submit)</span
            >
        </li>
    </ul>
</template>
