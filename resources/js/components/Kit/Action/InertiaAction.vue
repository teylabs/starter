<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import {
    normalAction,
    type SingleAction,
} from '@/components/Kit/Action/singleAction';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import { confirmationData } from '@/components/Kit/Overlay/confirmText';

/*
 * One server action as a button (docs/single-actions.md), the twin of the menu's DropdownAction. It takes the kit's Action (or the older keyed-link shape):
 * with a `confirm` it asks first in the kit's Dialog (typed when it has `typed`, sent as `confirmation`), then visits; a GET without one is an Inertia link,
 * and `newTab` opens a plain link in a new tab with rel="noopener noreferrer". `disabled` shows it unusable with its `reason`. The default slot replaces the
 * label; a null action renders nothing, so `:action="actions.edit"` follows the server's "absent means not allowed". It lays nothing out: place it yourself. It is Button's size by default (36px, a ButtonGroup segment's height), so it lines up beside them; pass `size="sm"` for a dense row.
 */
const props = withDefaults(
    defineProps<{
        action: SingleAction | null | undefined;
        size?: 'default' | 'sm' | 'lg';
        variant?: 'primary' | 'secondary' | 'destructive' | 'ghost' | 'link';
    }>(),
    { size: 'default', variant: undefined },
);

const normal = computed(() =>
    props.action ? normalAction(props.action) : null,
);
const look = computed(
    () => props.variant ?? (normal.value?.danger ? 'destructive' : 'secondary'),
);
const asking = ref(false);
const busy = ref(false);

function visit(confirmation?: string) {
    const action = normal.value;
    if (!action || busy.value) return;

    busy.value = true;
    router.visit(action.url, {
        method: action.method,
        data: confirmationData(confirmation),
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
    });
}

function choose() {
    if (!normal.value || normal.value.disabled || busy.value) return;
    if (normal.value.confirm) {
        asking.value = true;

        return;
    }
    visit();
}
</script>
<template>
    <template v-if="normal">
        <Button
            v-if="
                normal.method === 'get' && !normal.confirm && !normal.disabled
            "
            :href="normal.url"
            :external="normal.newTab"
            :target="normal.newTab ? '_blank' : undefined"
            :rel="normal.newTab ? 'noopener noreferrer' : undefined"
            :variant="look"
            :size="size"
            :title="normal.caption ?? undefined"
            data-inertia-action
        >
            <i
                v-if="normal.icon"
                :class="normal.icon"
                aria-hidden="true"
            /><slot>{{ normal.label }}</slot>
        </Button>
        <Button
            v-else
            type="button"
            :variant="look"
            :size="size"
            :aria-disabled="normal.disabled ? 'true' : undefined"
            :aria-busy="busy ? 'true' : undefined"
            :title="
                (normal.disabled ? normal.reason : normal.caption) ?? undefined
            "
            data-inertia-action
            @click="choose"
        >
            <i
                v-if="normal.icon"
                :class="normal.icon"
                aria-hidden="true"
            /><slot>{{ normal.label }}</slot>
        </Button>
        <Dialog
            v-if="normal.confirm"
            v-model:open="asking"
            :variant="normal.danger ? 'danger' : 'neutral'"
            :title="normal.confirm.title"
            :description="normal.confirm.description"
            :confirm-label="normal.confirm.label"
            :confirm-text="normal.confirm.typed"
            @confirm="(confirmation) => visit(confirmation)"
        />
    </template>
</template>
