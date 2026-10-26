<script setup lang="ts">
import { Avatar } from '@kit/ui';

// The signed-in user's photo in the shell, or their initials.
defineProps<{ initials: string; src?: string | null; src2x?: string | null }>();
</script>
<template>
    <Avatar :initials="initials" :src="src" :src2x="src2x" />
</template>
