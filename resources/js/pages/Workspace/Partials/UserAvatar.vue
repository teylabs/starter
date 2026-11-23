<script setup lang="ts">
import { Avatar } from '@kit/ui';

// The signed-in user's photo in the shell, or their initials on their colour, with the gap ring in the colour of the bar, menu or drawer it sits on (the Layout's bar sets `--avatar-ring`).
defineProps<{ initials: string; src?: string | null; src2x?: string | null; color?: string | null }>();
</script>
<template>
    <Avatar :initials="initials" :src="src" :src2x="src2x" :color="color" />
</template>
