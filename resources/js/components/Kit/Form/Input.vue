<script setup lang="ts">
import { computed, onMounted, ref, useAttrs } from 'vue';
import { Input as BaseInput } from '@/components/ui/input';
import { useLocked } from './useLocked';

// The text input; `invalid` marks it for the error state, `autofocus` focuses it when the page arrives (an Inertia visit does not trigger the browser's own autofocus; a read-only input is never focused), and the other attributes pass through. A read-only input (the native `readonly`, so it stays focusable, selectable and
// announced) looks different from an editable one: a muted surface, the default cursor and no focus emphasis. Disabled keeps its own dimmed look.
// Inside a locked LockedForm it is read-only through the context (the LockedForm spec): native `readonly`, the `muted` fill, the `border` token, the value at full contrast, the focus ring kept and the default arrow pointer. A caller's own `disabled` still wins.
defineOptions({ inheritAttrs: false });
const props = defineProps<{ invalid?: boolean; autofocus?: boolean }>();
const model = defineModel<string | number>();
const attrs = useAttrs();
const lock = useLocked();
const set = (value: unknown) => value !== undefined && value !== false;
const locked = computed(() => lock.locked && !set(attrs.disabled));
const field = ref<{ $el?: HTMLElement } | HTMLElement | null>(null);
onMounted(() => {
    if (!props.autofocus || set(attrs.readonly) || locked.value) return;
    const el = field.value && '$el' in field.value ? field.value.$el : field.value;
    (el as HTMLElement | null | undefined)?.focus();
});
</script>
<template>
    <BaseInput ref="field" v-bind="$attrs" v-model="model" :readonly="locked || (attrs.readonly as boolean | undefined)" :aria-invalid="invalid || undefined" :data-locked="locked || undefined" :class="locked ? 'cursor-default border-border bg-muted text-foreground shadow-none dark:bg-muted' : 'read-only:not-disabled:cursor-default read-only:not-disabled:border-dashed read-only:not-disabled:bg-muted read-only:not-disabled:text-muted-foreground read-only:not-disabled:shadow-none read-only:not-disabled:focus-visible:border-input read-only:not-disabled:focus-visible:ring-0'" />
</template>
