<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogOut, Monitor, Moon, Settings, Sun } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import type { ShellUser } from './types';
import UserAvatar from './UserAvatar.vue';

/*
 * Who is signed in, then Settings and Appearance, then Sign out: last, on its
 * own after a separator, and a POST (or DELETE) request rather than a link.
 * Nothing destructive lives here. `wide` is the sidebar's full-width trigger.
 */
const props = withDefaults(
    defineProps<{
        user: ShellUser;
        settingsUrl: string;
        signOutUrl: string;
        signOutMethod?: 'post' | 'delete';
        wide?: boolean;
        side?: 'top' | 'bottom';
        align?: 'start' | 'end';
    }>(),
    { signOutMethod: 'post', side: undefined, align: undefined },
);

const { appearance, updateAppearance } = useAppearance();

function chooseAppearance(value: unknown) {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value satisfies Appearance);
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex min-h-11 items-center gap-2 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    wide
                        ? 'w-full rounded-md px-2 hover:bg-sidebar-accent data-[state=open]:bg-sidebar-accent'
                        : 'min-w-11 justify-center rounded-full hover:bg-accent data-[state=open]:bg-accent'
                "
                data-shell-user-menu
            >
                <UserAvatar :initials="user.initials" />
                <span v-if="wide" class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-sm font-medium text-foreground">
                        {{ user.name }}
                    </span>
                    <span class="truncate text-xs text-muted-foreground">
                        {{ user.email }}
                    </span>
                </span>
                <span v-else class="sr-only">
                    Account menu for {{ user.name }}
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            :align="props.align ?? (wide ? 'start' : 'end')"
            :side="props.side ?? (wide ? 'top' : 'bottom')"
            :side-offset="6"
            class="w-64"
        >
            <DropdownMenuLabel class="flex items-center gap-3 font-normal">
                <UserAvatar :initials="user.initials" />
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-sm font-medium text-foreground">
                        {{ user.name }}
                    </span>
                    <span class="truncate text-xs text-muted-foreground">
                        {{ user.email }}
                    </span>
                </span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="settingsUrl" class="min-h-9 w-full">
                    <Settings aria-hidden="true" />
                    Settings
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSub>
                <DropdownMenuSubTrigger class="min-h-9 gap-2">
                    <Sun
                        aria-hidden="true"
                        class="size-4 text-muted-foreground"
                    />
                    Appearance
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent class="w-40">
                    <DropdownMenuRadioGroup
                        :model-value="appearance"
                        @update:model-value="chooseAppearance"
                    >
                        <DropdownMenuRadioItem value="light">
                            <Sun aria-hidden="true" />
                            Light
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="dark">
                            <Moon aria-hidden="true" />
                            Dark
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="system">
                            <Monitor aria-hidden="true" />
                            System
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuSubContent>
            </DropdownMenuSub>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link
                    :href="signOutUrl"
                    :method="signOutMethod"
                    as="button"
                    class="min-h-9 w-full"
                    data-shell-sign-out
                >
                    <LogOut aria-hidden="true" />
                    Sign out
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
