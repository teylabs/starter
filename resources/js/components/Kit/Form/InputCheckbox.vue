<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox as BaseCheckbox } from '@/components/ui/checkbox';
import { useLocked } from './useLocked';

// A checkbox with its label beside it, in the default slot.
// Inside a locked LockedForm it is read-only through the context (the LockedForm spec): `aria-readonly`, the checked state and colours kept at full contrast, still in the Tab order, and click, Space and the label leave it unchanged (the click is stopped before the control sees it). A caller's own `disabled` still wins.
const props = defineProps<{ id: string; name?: string; value?: string; disabled?: boolean }>();
const model = defineModel<boolean>();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
function guard(event: Event) {
    if (!locked.value) return;
    event.preventDefault();
    event.stopPropagation();
}
</script>
<template>
    <div class="flex items-center gap-2" @click.capture="guard">
        <BaseCheckbox :id="id" v-model="model" :name="name" :value="value" :disabled="disabled" :aria-readonly="locked || undefined" :data-locked="locked || undefined" :class="locked && 'cursor-default'" />
        <label :for="id" :class="['text-sm leading-none', locked && 'cursor-default']"><slot /></label>
    </div>
</template>
