<script setup lang="ts">
import { computed, onMounted, ref, useAttrs } from 'vue';
import { customProperties } from './inputSelect';
import { fileSize } from './locked';
import { useLocked } from './useLocked';
import { useFieldAria } from './fieldAria';

// A file chooser: a button that opens the browser's picker and reports the chosen file (`v-model`: File or null). `accept` filters the picker. `current` is the file the
// record already holds (name, size and, when the host gives one, a link to open or download it), shown in place of the picker while locked.
// Inside a locked LockedForm (the LockedForm spec) there is no picker, no choose, replace or remove: the current file shows as its name, size and link, or a muted "No file".
// Caller attributes (`aria-describedby`, `required`, `name`, `data-*` and the rest) land on the file input; `invalid` sets its `aria-invalid`. Only the custom properties (`--*`) of a `style` are kept. While locked, only `class` and those carry over, to the file line.
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    id: string;
    accept?: string;
    disabled?: boolean;
    invalid?: boolean;
    autofocus?: boolean;
    current?: {
        name: string;
        size?: number | null;
        url?: string | null;
    } | null;
}>();
const model = defineModel<File | null>();
const attrs = useAttrs();
// Inside a Field: described only by what it renders, and invalid while it has an error.
const aria = useFieldAria(attrs, () => props.invalid);
const inputAttrs = computed(() => {
    const rest: Record<string, unknown> = { ...attrs };
    delete rest.style;
    return rest;
});
const input = ref<HTMLInputElement | null>(null);
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const shown = computed(() =>
    model.value
        ? { name: model.value.name, size: model.value.size, url: null }
        : (props.current ?? null),
);
onMounted(() => props.autofocus && input.value?.focus());
function chosen(event: Event) {
    model.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}
defineExpose({
    clear: () => {
        model.value = null;
        if (input.value) input.value.value = '';
    },
});
</script>
<template>
    <p
        v-if="locked"
        :id="id"
        :style="customProperties(attrs.style)"
        :class="attrs.class as string | undefined"
        class="flex min-h-9 flex-wrap items-center gap-x-3 gap-y-1 text-sm"
        data-file-current
    >
        <template v-if="shown">
            <span class="text-foreground">{{ shown.name }}</span>
            <span v-if="shown.size != null" class="text-muted-foreground">{{
                fileSize(shown.size)
            }}</span>
            <a
                v-if="shown.url"
                :href="shown.url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary focus-visible:ring-ring underline underline-offset-4 focus-visible:ring-2 focus-visible:outline-none"
                >Open</a
            >
        </template>
        <span v-else class="text-muted-foreground">No file</span>
    </p>
    <input
        v-else
        v-bind="inputAttrs"
        :style="customProperties(attrs.style)"
        :id="id"
        ref="input"
        type="file"
        :accept="accept"
        :disabled="disabled"
        :aria-describedby="aria['aria-describedby']"
        :aria-invalid="aria['aria-invalid']"
        class="text-muted-foreground file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-2 file:text-sm file:font-medium"
        @change="chosen"
    />
</template>
