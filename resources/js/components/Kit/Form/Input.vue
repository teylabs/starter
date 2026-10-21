<script setup lang="ts">
import { onMounted, ref, useAttrs } from 'vue';
import { Input as BaseInput } from '@/components/ui/input';

// The text input; `invalid` marks it for the error state, `autofocus` focuses it when the page arrives (an Inertia visit does not trigger the browser's own autofocus; a read-only input is never focused), and the other attributes pass through. A read-only input (the native `readonly`, so it stays focusable, selectable and
// announced) looks different from an editable one: a muted surface, the default cursor and no focus emphasis. Disabled keeps its own dimmed look.
defineOptions({ inheritAttrs: false });
const props = defineProps<{ invalid?: boolean; autofocus?: boolean }>();
const model = defineModel<string | number>();
const attrs = useAttrs();
const field = ref<{ $el?: HTMLElement } | HTMLElement | null>(null);
onMounted(() => {
    if (!props.autofocus || attrs.readonly !== undefined && attrs.readonly !== false) return;
    const el = field.value && '$el' in field.value ? field.value.$el : field.value;
    (el as HTMLElement | null | undefined)?.focus();
});
</script>
<template>
    <BaseInput ref="field" v-bind="$attrs" v-model="model" :aria-invalid="invalid || undefined" class="read-only:not-disabled:cursor-default read-only:not-disabled:border-dashed read-only:not-disabled:bg-muted read-only:not-disabled:text-muted-foreground read-only:not-disabled:shadow-none read-only:not-disabled:focus-visible:border-input read-only:not-disabled:focus-visible:ring-0" />
</template>
