<script setup lang="ts">
import { Avatar } from '@kit/ui';
import type { WidgetFrame } from './types';

// The "You" card: the viewer, their role and the workspace.
type Person = { name: string; email: string; initials: string; avatar: { url: string; url_2x: string } | null; role: string | null; workspace: string };
defineProps<{ widget: WidgetFrame & { person: Person } }>();
</script>
<template>
    <div class="flex items-center gap-4" data-widget-you>
        <Avatar :initials="widget.person.initials" :src="widget.person.avatar?.url" :src2x="widget.person.avatar?.url_2x" large />
        <div class="grid min-w-0 gap-0.5">
            <p class="truncate font-semibold">{{ widget.person.name }}</p>
            <p class="truncate text-sm text-muted-foreground">{{ widget.person.email }}</p>
            <p class="text-sm"><span v-if="widget.person.role">{{ widget.person.role }} · </span>{{ widget.person.workspace }}</p>
        </div>
    </div>
</template>
