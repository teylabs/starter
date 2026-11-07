<script setup lang="ts">
import { Check, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Collapsible from '@/components/Kit/Layout/Collapsible.vue';
import Input from '@/components/Kit/Form/Input.vue';
import {
    contrastRatio,
    markColour,
    normaliseHex,
    swatchList,
} from '@/components/Kit/Form/swatch';

/*
 * A colour picker for people who don't think in hex codes (docs/swatch-picker.md): suggested swatches from the server (say, colours from the
 * logo), a short palette, and a hex field folded into a Collapsible. One `role="radiogroup"` of `role="radio"` swatches, named by `label`, with
 * one tab stop (a roving tabindex on the picked swatch); the arrow keys move and pick (wrapping), Home and End go to the first and last. `v-model`
 * is "#RRGGBB", or '' for "no colour" (the host's default), which the swatch whose hex is `defaultValue` stands for. The check mark is black or
 * white, whichever contrasts with the swatch. A colour under `minContrast` against `background` shows a hint. With `name`, a hidden input posts
 * the value; a typed value that is not a colour is posted as typed, so the save is refused with the field's error rather than silently keeping
 * the last pick. The `preview` slot gets the colour shown (the pick, or `defaultValue`).
 */
const props = withDefaults(
    defineProps<{
        label: string;
        palette: string[];
        suggested?: string[];
        suggestedLabel?: string;
        paletteLabel?: string;
        defaultValue?: string;
        background?: string;
        minContrast?: number;
        contrastHint?: string;
        name?: string;
        hexLabel?: string;
        // The hex field's heading level; one below the heading this picker sits under.
        hexLevel?: 2 | 3 | 4 | 5 | 6;
        id?: string;
    }>(),
    {
        suggested: () => [],
        suggestedLabel: 'Suggested',
        paletteLabel: 'Pick a colour',
        defaultValue: '',
        background: '#FFFFFF',
        minContrast: 3,
        contrastHint:
            'This colour is faint against the background, so it may be hard to see. A darker shade shows more clearly.',
        name: undefined,
        hexLabel: 'Enter a hex code',
        hexLevel: 3,
        id: undefined,
    },
);
const model = defineModel<string>({ default: '' });
const fallback = computed(() => normaliseHex(props.defaultValue));
const picked = computed(() => normaliseHex(model.value ?? '') ?? '');
const swatches = computed(() => swatchList(props.suggested, props.palette));
const groups = computed(() =>
    [
        {
            key: 'suggested',
            label: props.suggestedLabel,
            items: swatches.value
                .map((swatch, index) => ({ ...swatch, index }))
                .filter((swatch) => swatch.suggested),
        },
        {
            key: 'palette',
            label: props.paletteLabel,
            items: swatches.value
                .map((swatch, index) => ({ ...swatch, index }))
                .filter((swatch) => !swatch.suggested),
        },
    ].filter((group) => group.items.length),
);
const isPicked = (hex: string): boolean =>
    picked.value === hex || (picked.value === '' && hex === fallback.value);
const stop = computed(() =>
    Math.max(
        0,
        swatches.value.findIndex((swatch) => isPicked(swatch.hex)),
    ),
);
const buttons = ref<HTMLElement[]>([]);
const typed = ref(model.value ?? '');
// An outside change to the value (a reset, another control) shows in the hex field; a value being typed is left alone.
const shown = computed(() => picked.value || fallback.value || '');
const faint = computed(
    () =>
        shown.value !== '' &&
        normaliseHex(props.background) !== null &&
        contrastRatio(shown.value, normaliseHex(props.background) as string) <
            props.minContrast,
);
const typedInvalid = computed(
    () => typed.value.trim() !== '' && normaliseHex(typed.value) === null,
);
watch(model, (value) => {
    if (
        !typedInvalid.value &&
        (normaliseHex(typed.value) ?? '') !== (normaliseHex(value ?? '') ?? '')
    )
        typed.value = value ?? '';
});
const posted = computed(() =>
    typedInvalid.value ? typed.value.trim() : picked.value,
);
const hexOpen = computed(
    () =>
        picked.value !== '' &&
        !swatches.value.some((swatch) => swatch.hex === picked.value),
);
const inputId = computed(() => (props.id ? `${props.id}-hex` : undefined));

function choose(index: number): void {
    const swatch = swatches.value[index];
    if (!swatch) return;
    model.value = swatch.hex === fallback.value ? '' : swatch.hex;
    typed.value = model.value;
    buttons.value[index]?.focus();
}
function key(event: KeyboardEvent, index: number): void {
    const count = swatches.value.length;
    if (!count) return;
    const next = {
        ArrowRight: (index + 1) % count,
        ArrowDown: (index + 1) % count,
        ArrowLeft: (index - 1 + count) % count,
        ArrowUp: (index - 1 + count) % count,
        Home: 0,
        End: count - 1,
    }[event.key];
    if (next === undefined) return;
    event.preventDefault();
    choose(next);
}
function type(raw: string): void {
    typed.value = raw;
    if (raw.trim() === '') model.value = '';
    else {
        const hex = normaliseHex(raw);
        if (hex !== null) model.value = hex;
    }
}
</script>
<template>
    <div :id="id" class="grid gap-4" data-swatch-picker>
        <input v-if="name" type="hidden" :name="name" :value="posted" />
        <div role="radiogroup" :aria-label="label" class="grid gap-3">
            <div
                v-for="group in groups"
                :key="group.key"
                role="group"
                :aria-label="group.label"
                class="grid gap-2"
                :data-swatch-group="group.key"
            >
                <p class="text-sm font-medium" aria-hidden="true">
                    {{ group.label }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="swatch in group.items"
                        :key="swatch.hex"
                        :ref="
                            (el: unknown) => {
                                if (el)
                                    buttons[swatch.index] = el as HTMLElement;
                            }
                        "
                        type="button"
                        role="radio"
                        :aria-checked="isPicked(swatch.hex) ? 'true' : 'false'"
                        :aria-label="
                            swatch.hex === fallback
                                ? `${swatch.hex} (default)`
                                : swatch.hex
                        "
                        :title="swatch.hex"
                        :tabindex="swatch.index === stop ? 0 : -1"
                        :data-swatch="swatch.hex"
                        :style="{
                            '--swatch': swatch.hex,
                            '--swatch-mark': markColour(swatch.hex),
                        }"
                        :class="[
                            group.key === 'suggested' ? 'size-10' : 'size-8',
                            'border-border ring-offset-background focus-visible:outline-ring aria-checked:ring-foreground inline-flex items-center justify-center rounded-full border bg-(--swatch) text-(--swatch-mark) ring-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 aria-checked:ring-2',
                        ]"
                        @click="choose(swatch.index)"
                        @keydown="key($event, swatch.index)"
                    >
                        <Check
                            v-if="isPicked(swatch.hex)"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </div>
        </div>
        <slot name="preview" :colour="shown" />
        <p
            v-if="faint"
            class="text-warning-text flex items-start gap-2 text-sm"
            role="status"
            data-swatch-contrast
        >
            <TriangleAlert
                class="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />{{ contrastHint }}
        </p>
        <Collapsible
            :title="hexLabel"
            :level="hexLevel"
            :default-open="hexOpen"
        >
            <div class="grid max-w-48 gap-1 pt-2">
                <label :for="inputId" class="sr-only">Hex colour</label>
                <Input
                    :id="inputId"
                    :model-value="typed"
                    :placeholder="fallback || '#000000'"
                    maxlength="7"
                    autocomplete="off"
                    spellcheck="false"
                    :invalid="typedInvalid"
                    data-swatch-hex
                    @update:model-value="
                        (raw: string | number | undefined) =>
                            type(String(raw ?? ''))
                    "
                />
                <p class="text-muted-foreground text-xs">
                    Six digits, e.g. {{ fallback || '#1D4ED8' }}.<template
                        v-if="fallback"
                    >
                        Leave empty for the default.</template
                    >
                </p>
            </div>
        </Collapsible>
    </div>
</template>
