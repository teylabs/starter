<script setup lang="ts">
import { computed } from 'vue';
import {
    normalAction,
    type SingleAction,
} from '@/components/Kit/Action/singleAction';
import DropdownConfirm from '@/components/Kit/Menu/DropdownConfirm.vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';

/*
 * A menu entry from one server action (DropdownAction.md, docs/single-actions.md), the twin of InertiaAction. It takes the kit's Action or the older keyed-link
 * shape: a DropdownConfirm when it has a confirmation, else an Inertia DropdownItem; `newTab` opens a plain link. `icon` is a CSS class for an `<i>`. The
 * default slot replaces the label; a null action renders nothing. A typed confirmation can't be asked from a menu, so such an action is shown disabled with
 * that reason (place it as an InertiaAction instead): it never runs without the typing.
 */
const props = defineProps<{ action: SingleAction | null | undefined }>();
const normal = computed(() =>
    props.action ? normalAction(props.action) : null,
);
const iconClass = computed(() =>
    normal.value?.danger ? 'text-destructive-text' : '',
);
const blocked = computed(
    () =>
        normal.value !== null &&
        (normal.value.disabled || Boolean(normal.value.confirm?.typed)),
);
const blockedReason = computed(() =>
    normal.value?.confirm?.typed
        ? 'Needs a typed confirmation: use the button.'
        : (normal.value?.reason ?? null),
);
</script>
<template>
    <template v-if="normal">
        <DropdownItem
            v-if="blocked"
            as="button"
            disabled
            aria-disabled="true"
            :title="blockedReason ?? undefined"
        >
            <template #icon
                ><i aria-hidden="true" :class="[normal.icon, iconClass]"
            /></template>
            <slot>{{ normal.label }}</slot>
            <template v-if="blockedReason" #caption>{{
                blockedReason
            }}</template>
        </DropdownItem>
        <DropdownConfirm
            v-else-if="normal.confirm"
            :href="normal.url"
            :method="normal.method"
            :variant="normal.danger ? 'danger' : undefined"
            :confirm-label="normal.confirm.label"
        >
            <template #icon
                ><i aria-hidden="true" :class="[normal.icon, iconClass]"
            /></template>
            <slot>{{ normal.label }}</slot>
            <template v-if="normal.caption" #caption>{{
                normal.caption
            }}</template>
            <template #title>{{ normal.confirm.title }}</template>
            <template #confirmation
                ><p>{{ normal.confirm.description }}</p></template
            >
        </DropdownConfirm>
        <DropdownItem
            v-else-if="normal.newTab"
            :href="normal.url"
            target="_blank"
            rel="noopener noreferrer"
        >
            <template #icon
                ><i aria-hidden="true" :class="[normal.icon, iconClass]"
            /></template>
            <slot>{{ normal.label }}</slot>
            <template v-if="normal.caption" #caption>{{
                normal.caption
            }}</template>
        </DropdownItem>
        <DropdownItem
            v-else
            as="button"
            :href="normal.url"
            inertia
            :method="normal.method"
        >
            <template #icon
                ><i aria-hidden="true" :class="[normal.icon, iconClass]"
            /></template>
            <slot>{{ normal.label }}</slot>
            <template v-if="normal.caption" #caption>{{
                normal.caption
            }}</template>
        </DropdownItem>
    </template>
</template>
