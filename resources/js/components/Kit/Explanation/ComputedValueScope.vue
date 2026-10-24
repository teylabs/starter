<script setup lang="ts">
import { computed, onMounted, provide, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { scopeKey as injectionKey, type WorkingMember, type WorkingScope } from './scope';
const props = withDefaults(defineProps<{ scopeKey: string; remember?: boolean; expanded?: boolean; preference?: { url: string; method: 'post' } }>(), { remember: false, expanded: undefined });
const emit = defineEmits<{ 'update:expanded': [value: boolean] }>();
const intent = ref(props.expanded ?? false);
const version = ref(0);
const members = new Map<symbol, WorkingMember>();
const status = ref('');
const storageKey = () => `kit:working:${props.scopeKey}`;
function fallback(value: boolean) { try { localStorage.setItem(storageKey(), value ? '1' : '0'); } catch { /* Preference loss is harmless. */ } }
const scope: WorkingScope = {
    members, intent, version, status,
    register(key, member) { members.set(key, member); version.value++; if (intent.value) member.request(true, true); },
    unregister(key) { members.delete(key); version.value++; },
    request(value, toggle) {
        if (!value && [...members.values()].some(m => m.containsFocus())) toggle?.focus();
        emit('update:expanded', value);
        if (props.expanded !== undefined) return;
        apply(value);
        status.value = value ? 'All working shown' : 'All working hidden';
        if (props.remember) {
            if (props.preference) router.post(props.preference.url, { key: props.scopeKey, expanded: value }, { preserveScroll: true, onError: () => fallback(value) });
            else fallback(value);
        }
    },
    all: computed(() => { version.value; return members.size > 0 && [...members.values()].every(m => m.open.value); }),
    some: computed(() => { version.value; return [...members.values()].some(m => m.open.value); }),
};
function apply(value: boolean) { intent.value = value; members.forEach(m => m.request(value, true)); }
watch(() => props.expanded, value => { if (value !== undefined) apply(value); });
onMounted(() => { if (props.remember && !props.preference && props.expanded === undefined) { try { apply(localStorage.getItem(storageKey()) === '1'); } catch { /* Storage may be blocked. */ } } });
provide(injectionKey, scope);
</script>
<template><slot /><p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ status }}</p></template>
