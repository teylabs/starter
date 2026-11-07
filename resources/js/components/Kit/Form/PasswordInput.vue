<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { Toggle } from 'reka-ui';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useAttrs,
    useId,
} from 'vue';
import Input from './Input.vue';
import PasswordRules from './PasswordRules.vue';

// A password input with a show/hide toggle (Reka's Toggle holds the pressed state); the other attributes pass to the input. `rules` (for a new password) shows
// the password rules under it (PasswordRules) and joins them to the input's description.
// It follows its form's `reset` (a native reset, or Inertia's whole-form reset): the value it holds becomes the input's reset value, so a re-render never
// writes the old password back. A reset of named fields only (Inertia's `reset-on-error` list) fires no event: the page clears its v-model.
defineOptions({ inheritAttrs: false });
const props = defineProps<{ invalid?: boolean; rules?: boolean }>();
const model = defineModel<string>();
const shown = ref(false);
const attrs = useAttrs();
const rulesId = `password-rules-${useId()}`;
const describedBy = computed(() =>
    props.rules
        ? [attrs['aria-describedby'], rulesId].filter(Boolean).join(' ')
        : (attrs['aria-describedby'] as string | undefined),
);
const root = ref<HTMLElement | null>(null);
let owner: HTMLFormElement | null = null;
// The reset event fires before the browser resets the fields, so the value is read after it.
const followReset = () =>
    nextTick(
        () => (model.value = root.value?.querySelector('input')?.value ?? ''),
    );
onMounted(() => {
    owner = root.value?.closest('form') ?? null;
    owner?.addEventListener('reset', followReset);
});
onBeforeUnmount(() => owner?.removeEventListener('reset', followReset));
</script>
<template>
    <div ref="root" class="relative">
        <Input
            v-bind="$attrs"
            v-model="model"
            :aria-describedby="describedBy"
            :type="shown ? 'text' : 'password'"
            :invalid="invalid"
            class="pr-10"
        />
        <Toggle
            v-model="shown"
            :aria-label="shown ? 'Hide password' : 'Show password'"
            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 focus-visible:ring-[3px] focus-visible:outline-none"
            :tabindex="-1"
        >
            <EyeOff v-if="shown" class="size-4" aria-hidden="true" />
            <Eye v-else class="size-4" aria-hidden="true" />
        </Toggle>
    </div>
    <PasswordRules v-if="rules" :id="rulesId" :value="model" />
</template>
