<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
defineProps<Tey.Kit.Features.Workspaces.ViewModels.IndexWorkspaceViewModel>();
// Each workspace opens at its first page; the shell's choices already carry the link and what the user may reach.
const page = usePage<{ layout?: Tey.Kit.Features.SessionUi.Data.ShellData }>();
const choices = computed(() => page.props.layout?.switcher.choices ?? []);
</script>
<template>
    <p v-if="!choices.length">You have no active workspaces.</p>
    <ul v-else class="grid gap-2">
        <li v-for="choice in choices" :key="choice.id">
            <Link v-if="choice.url" :href="choice.url" class="text-sm font-medium underline-offset-4 hover:underline">{{ choice.name }}</Link>
            <span v-else class="text-sm text-muted-foreground">{{ choice.name }} · unavailable</span>
        </li>
    </ul>
</template>
