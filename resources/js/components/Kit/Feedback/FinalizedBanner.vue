<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, useId } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import DateTime from '@/components/Kit/Data/DateTime.vue';
import Button from '@/components/Kit/Form/Button.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';
import Alert from './Alert.vue';

/*
 * The banner a finalized record shows (kit FinalizedBanner): "Finalized by {by} on {date}", and, when the server sends an `unfinalize` action, an Un-finalize
 * button that asks for a reason first and posts it. The server decides who may un-finalize; the action is absent for everyone else. Render it with
 * `v-if="gate.finalization"`. `channel` "mcp" adds "through an assistant", so a finalization made in a chat reads as such.
 */
const props = withDefaults(
    defineProps<{
        finalization: {
            by: string;
            at: string;
            channel: string;
            unfinalize?: ActionDescriptor | null;
        };
        title?: string;
        reasonLabel?: string;
    }>(),
    { title: undefined, reasonLabel: 'Why un-finalize?' },
);
const id = `unfinalize-reason-${useId()}`;
const asking = ref(false);
const reason = ref('');
const busy = ref(false);

function submit(): void {
    const action = props.finalization.unfinalize;
    if (!action || !reason.value.trim() || busy.value) return;
    busy.value = true;
    router.visit(action.url, {
        method: 'post',
        data: { reason: reason.value },
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
        onSuccess: () => {
            asking.value = false;
            reason.value = '';
        },
    });
}
</script>
<template>
    <Alert
        variant="success"
        :title="title ?? 'Finalized'"
        data-finalized-banner
    >
        <p class="text-sm">
            Finalized by {{ finalization.by }} on
            <DateTime :value="finalization.at" format="date" /><template
                v-if="finalization.channel === 'mcp'"
            >
                through an assistant</template
            >.
        </p>
        <div
            v-if="finalization.unfinalize"
            class="mt-3 grid gap-2"
            data-finalized-unfinalize
        >
            <Button
                v-if="!asking"
                type="button"
                size="sm"
                variant="secondary"
                class="justify-self-start"
                :disabled="finalization.unfinalize.disabled"
                @click="asking = true"
                >{{ finalization.unfinalize.label }}</Button
            >
            <form v-else class="grid gap-2" @submit.prevent="submit">
                <label :for="id" class="text-sm font-medium">{{
                    reasonLabel
                }}</label>
                <Textarea :id="id" v-model="reason" rows="2" />
                <div class="flex gap-2">
                    <Button
                        type="submit"
                        size="sm"
                        variant="destructive"
                        :disabled="!reason.trim() || busy"
                        >{{ finalization.unfinalize.label }}</Button
                    >
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="asking = false"
                        >Cancel</Button
                    >
                </div>
            </form>
        </div>
    </Alert>
</template>
