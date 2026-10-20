<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Menu, Settings } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import AppearanceChoice from './AppearanceChoice.vue';
import SidebarNav from './SidebarNav.vue';
import type { ShellNavGroup, ShellUser } from './types';
import UserAvatar from './UserAvatar.vue';

/*
 * Below `lg` the bar keeps the mark, the current context and this one
 * labelled button. It opens a sheet with the same nav, in the same order,
 * then the user's items with Sign out last. The sheet is a dialog: focus is
 * trapped inside it, Escape closes it, and any navigation closes it.
 */
withDefaults(
    defineProps<{
        navigation: ShellNavGroup[];
        user: ShellUser;
        settingsUrl: string;
        signOutUrl: string;
        signOutMethod?: 'post' | 'delete';
    }>(),
    { signOutMethod: 'post' },
);

const open = ref(false);
const stop = router.on('navigate', () => {
    open.value = false;
});

onBeforeUnmount(stop);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <button
                type="button"
                class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-foreground ring-1 ring-border hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                data-shell-menu-button
            >
                <Menu class="size-4" aria-hidden="true" />
                Menu
            </button>
        </SheetTrigger>
        <SheetContent
            side="right"
            class="w-80 max-w-[85vw] gap-0 overflow-y-auto p-0"
            data-shell-sheet
        >
            <div class="flex h-16 items-center border-b border-border px-4">
                <SheetTitle class="text-base font-semibold">Menu</SheetTitle>
                <SheetDescription class="sr-only">
                    Navigation and your account
                </SheetDescription>
            </div>
            <div class="px-2 py-4">
                <SidebarNav :navigation="navigation" @navigate="open = false" />
            </div>
            <div class="mt-auto border-t border-border px-2 py-4">
                <div class="flex items-center gap-3 px-3 pb-3">
                    <UserAvatar :initials="user.initials" />
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-medium">
                            {{ user.name }}
                        </span>
                        <span class="truncate text-xs text-muted-foreground">
                            {{ user.email }}
                        </span>
                    </span>
                </div>
                <Link
                    :href="settingsUrl"
                    class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Settings class="size-4" aria-hidden="true" />
                    Settings
                </Link>
                <div
                    class="flex min-h-11 items-center justify-between gap-2 px-3 text-sm font-medium text-muted-foreground"
                >
                    Appearance
                    <AppearanceChoice />
                </div>
                <div class="my-2 h-px bg-border" aria-hidden="true" />
                <Link
                    :href="signOutUrl"
                    :method="signOutMethod"
                    as="button"
                    class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    data-shell-sign-out
                >
                    <LogOut class="size-4" aria-hidden="true" />
                    Sign out
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
