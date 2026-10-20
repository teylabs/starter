<script setup lang="ts">
import { Bell, Building2, CreditCard, KeyRound, Mail, Palette, Plug, Settings, ShieldCheck, SlidersHorizontal, User, Users } from '@lucide/vue';

// A settings section's icon by its lucide name (kebab case), from the server's `icon`. The set is the kit's own; an unknown name is the generic Settings icon.
const icons = { bell: Bell, 'building-2': Building2, 'credit-card': CreditCard, 'key-round': KeyRound, mail: Mail, palette: Palette, plug: Plug, settings: Settings, 'shield-check': ShieldCheck, 'sliders-horizontal': SlidersHorizontal, user: User, users: Users } as const;
const props = defineProps<{ name?: string }>();
const icon = (props.name && icons[props.name as keyof typeof icons]) || Settings;
</script>
<template>
    <component :is="icon" class="size-5" aria-hidden="true" />
</template>
