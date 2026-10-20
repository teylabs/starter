<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<Tey.Kit.Features.Notes.ViewModels.ShowNoteViewModel>();
</script>
<template>
    <p class="whitespace-pre-wrap">{{ note.body }}</p>
    <Link :href="list_url" class="text-sm underline underline-offset-4">All notes</Link>
</template>
