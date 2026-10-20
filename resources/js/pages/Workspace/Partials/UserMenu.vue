<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserAvatar from './UserAvatar.vue';

// Who is signed in, the account pages, whatever the app adds in the `extra` slot, then Sign out last as a POST.
defineProps<{ user: NonNullable<Tey.Kit.Features.SessionUi.Data.ShellData['user']>; signOutUrl: string }>();
</script>
<template>
    <DropdownMenu :modal="false">
        <DropdownMenuTrigger class="flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-user-menu>
            <UserAvatar :initials="user.initials" />
            <span class="sr-only">Account menu for {{ user.name }}</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="6" class="w-64">
            <DropdownMenuLabel class="flex items-center gap-3 font-normal">
                <UserAvatar :initials="user.initials" />
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-sm font-medium text-foreground">{{ user.name }}</span>
                    <span class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
                </span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-for="item in user.items" :key="item.url" as-child>
                <Link :href="item.url" :aria-current="item.current ? 'page' : undefined" class="min-h-9 w-full">{{ item.label }}</Link>
            </DropdownMenuItem>
            <slot name="extra" />
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="signOutUrl" method="post" as="button" class="min-h-9 w-full" data-shell-sign-out>
                    <LogOut aria-hidden="true" />
                    Sign out
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
