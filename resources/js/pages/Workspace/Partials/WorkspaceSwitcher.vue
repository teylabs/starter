<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@kit/ui';

// Sits beside the product mark, so a workspace reads as part of where you are. The shell hides it for a single workspace.
defineProps<{ switcher: Tey.Kit.Features.SessionUi.Data.ShellData['switcher']; allUrl: string }>();
</script>
<template>
    <DropdownMenu :modal="false">
        <DropdownMenuTrigger class="flex min-h-11 items-center gap-1.5 rounded-md px-2 text-sm font-medium text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-switcher>
            <span class="max-w-48 truncate">{{ switcher.current?.name ?? 'Workspaces' }}</span>
            <ChevronsUpDown class="size-3.5 text-muted-foreground" aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="min-w-56">
            <DropdownMenuLabel class="text-xs font-medium text-muted-foreground">Workspaces</DropdownMenuLabel>
            <template v-for="choice in switcher.choices" :key="choice.id">
                <DropdownMenuItem v-if="choice.url" as-child>
                    <Link :href="choice.url" :aria-current="choice.current ? 'true' : undefined" class="flex min-h-9 w-full items-center justify-between gap-3">
                        <span class="truncate">{{ choice.name }}</span>
                        <Check v-if="choice.current" class="size-4" aria-hidden="true" />
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem v-else disabled>{{ choice.name }} · unavailable</DropdownMenuItem>
            </template>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="allUrl" class="min-h-9 w-full text-muted-foreground">All workspaces</Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
