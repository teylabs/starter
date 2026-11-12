import { Comment, Fragment, Text, type Slot, type VNode } from 'vue';

// Whether a slot renders anything: fragments (v-for, v-if groups) opened up, comments (a false v-if) and blank text ignored. A card part uses it to leave out
// its wrapper, rule and gutters when the caller passed a slot that renders nothing.
function filled(nodes: VNode[]): boolean {
    return nodes.some((node) => {
        if (node.type === Comment) return false;
        if (node.type === Fragment)
            return filled((node.children as VNode[] | null) ?? []);
        if (node.type === Text) return String(node.children).trim() !== '';

        return true;
    });
}

export function hasSlotContent(slot: Slot | undefined): boolean {
    return slot ? filled(slot()) : false;
}
