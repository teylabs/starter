<script setup lang="ts">
import { computed } from 'vue';
import type { ExplanationNode } from './explanation.generated';
import { operationText, ruleText, settlementText } from './working';
import WorkingSource from './WorkingSource.vue';
const props = defineProps<{ node: ExplanationNode }>();
const ordered = computed(() => {
    const keys = [...(props.node.operation?.operandOrder ?? []), ...props.node.inputs.map(i => i.key), ...props.node.children.map(c => c.id)];
    return [...new Set(keys)].map(key => ({ key, input: props.node.inputs.find(i => i.key === key), child: props.node.children.find(c => c.id === key) }));
});
</script>
<template>
    <div class="grid min-w-0 gap-3 break-words text-sm">
        <p class="font-medium">{{ node.label }}: <span class="tabular-nums">{{ node.value.display }}</span></p>
        <p>{{ operationText(node) }}</p>
        <ol v-if="ordered.length" class="grid gap-3 border-l border-border pl-4">
            <li v-for="operand in ordered" :key="operand.key">
                <template v-if="operand.input"><p>{{ operand.input.label }}: {{ operand.input.value.display }}; exact {{ operand.input.exact }}</p><WorkingSource :source="operand.input.source" /></template>
                <WorkingTechnicalTree v-if="operand.child" :node="operand.child" />
            </li>
        </ol>
        <p v-if="node.rule">Rule: {{ ruleText(node) }}</p>
        <ul v-if="node.exclusions.length" class="grid gap-2"><li v-for="excluded in node.exclusions" :key="excluded.key">Excluded: {{ excluded.label }} — {{ excluded.reason }}<template v-if="excluded.source">; <WorkingSource :source="excluded.source" /></template></li></ul>
        <p v-for="line in settlementText(node)" :key="line">{{ line }}</p>
        <p v-if="node.note">{{ node.note }}</p>
    </div>
</template>
