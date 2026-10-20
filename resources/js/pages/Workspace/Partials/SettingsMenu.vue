<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

// The settings pages as one menu after the nav; it reads as current while one of its pages is.
defineProps<{ settings: NonNullable<Tey.Kit.Features.SessionUi.Data.ShellData['settings']> }>();
</script>
<template>
    <DropdownMenu :modal="false">
        <DropdownMenuTrigger :class="['flex min-h-11 items-center gap-1 rounded-md px-3 text-sm font-medium whitespace-nowrap hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', settings.current ? 'text-foreground' : 'text-muted-foreground']" data-shell-settings>
            {{ settings.label }}
            <ChevronDown class="size-4" aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="min-w-44">
            <DropdownMenuItem v-for="item in settings.items" :key="item.url" as-child>
                <Link :href="item.url" :aria-current="item.current ? 'page' : undefined" class="min-h-9 w-full">{{ item.label }}</Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
