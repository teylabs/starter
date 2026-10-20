<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Menu } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import UserAvatar from './UserAvatar.vue';

// Below `lg` the same items, in the same order: workspaces, pages, the account, then Sign out. Any navigation closes it.
defineProps<{ layout: Tey.Kit.Features.SessionUi.Data.ShellData }>();

const open = ref(false);
const stop = router.on('navigate', () => {
    open.value = false;
});

onBeforeUnmount(stop);
</script>
<template>
    <Sheet v-if="layout.user" v-model:open="open">
        <SheetTrigger as-child>
            <button type="button" class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-foreground ring-1 ring-border hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-menu-button>
                <Menu class="size-4" aria-hidden="true" />
                Menu
            </button>
        </SheetTrigger>
        <SheetContent side="right" class="w-80 max-w-[85vw] gap-0 overflow-y-auto p-0" data-shell-sheet>
            <div class="flex h-16 items-center border-b border-border px-4">
                <SheetTitle class="text-base font-semibold">Menu</SheetTitle>
                <SheetDescription class="sr-only">Navigation and your account</SheetDescription>
            </div>
            <nav v-if="layout.switcher.choices.length > 1" aria-label="Workspaces" class="grid gap-1 px-2 pt-4">
                <p class="px-3 text-xs font-medium text-muted-foreground">Workspaces</p>
                <template v-for="choice in layout.switcher.choices" :key="choice.id">
                    <Link v-if="choice.url" :href="choice.url" :aria-current="choice.current ? 'true' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', choice.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ choice.name }}</Link>
                    <span v-else class="flex min-h-11 items-center px-3 text-sm text-muted-foreground">{{ choice.name }} · unavailable</span>
                </template>
            </nav>
            <nav v-if="layout.navigation.length" :aria-label="layout.switcher.current?.name ?? layout.product.name" class="grid gap-1 px-2 pt-4">
                <Link v-for="item in layout.navigation" :key="item.url" :href="item.url" :aria-current="item.current ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', item.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ item.label }}</Link>
            </nav>
            <div class="mt-auto border-t border-border px-2 py-4">
                <div class="flex items-center gap-3 px-3 pb-3">
                    <UserAvatar :initials="layout.user.initials" />
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-medium">{{ layout.user.name }}</span>
                        <span class="truncate text-xs text-muted-foreground">{{ layout.user.email }}</span>
                    </span>
                </div>
                <Link v-for="item in layout.user.items" :key="item.url" :href="item.url" :aria-current="item.current ? 'page' : undefined" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ item.label }}</Link>
                <slot name="extra" />
                <Link v-if="layout.links.sign_out" :href="layout.links.sign_out" method="post" as="button" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-sign-out>
                    <LogOut class="size-4" aria-hidden="true" />
                    Sign out
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
