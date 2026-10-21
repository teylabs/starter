<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Menu } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import UserAvatar from './UserAvatar.vue';
import { Button, Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@kit/ui';

// Below `lg` the same items, in the same order: workspaces, pages, the account, then Sign out. Any navigation closes it.
defineProps<{ shell: Tey.Kit.Features.SessionUi.Data.ShellData }>();

const open = ref(false);
const stop = router.on('navigate', () => {
    open.value = false;
});

onBeforeUnmount(stop);
</script>
<template>
    <Sheet v-if="shell.user" v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="bar" type="button" class="min-h-11 shrink-0 gap-2 px-3" data-shell-menu-button>
                <Menu class="size-4" aria-hidden="true" />
                Menu
            </Button>
        </SheetTrigger>
        <SheetContent side="right" class="w-80 max-w-[85vw] gap-0 overflow-y-auto p-0" data-shell-sheet>
            <div class="flex h-16 items-center border-b border-border px-4">
                <SheetTitle class="text-base font-semibold">Menu</SheetTitle>
                <SheetDescription class="sr-only">Navigation and your account</SheetDescription>
            </div>
            <nav v-if="shell.switcher" aria-label="Workspaces" class="grid gap-1 px-2 pt-4">
                <p class="px-3 text-xs font-medium text-muted-foreground">Workspaces</p>
                <template v-for="choice in shell.switcher.choices" :key="choice.id">
                    <Link v-if="choice.url" :href="choice.url" :aria-current="choice.current ? 'true' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', choice.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ choice.name }}</Link>
                    <span v-else class="flex min-h-11 items-center px-3 text-sm text-muted-foreground">{{ choice.name }} · unavailable</span>
                </template>
            </nav>
            <nav v-if="shell.navigation.length" :aria-label="shell.workspace?.name ?? shell.product.name" class="grid gap-1 px-2 pt-4">
                <Link v-for="item in shell.navigation" :key="item.url" :href="item.url" :aria-current="item.current ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', item.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ item.label }}</Link>
            </nav>
            <div class="mt-auto border-t border-border px-2 py-4">
                <div class="flex items-center gap-3 px-3 pb-3">
                    <UserAvatar :initials="shell.user.initials" :src="shell.user.avatar_url" :src2x="shell.user.avatar_url_2x" />
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-medium">{{ shell.user.name }}</span>
                        <span class="truncate text-xs text-muted-foreground">{{ shell.user.email }}</span>
                    </span>
                </div>
                <Link v-for="item in shell.user.items" :key="item.url" :href="item.url" :aria-current="item.current ? 'page' : undefined" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ item.label }}</Link>
                <div class="flex min-h-11 items-center justify-between gap-2 px-3 text-sm font-medium text-muted-foreground"><slot name="actions" /></div>
                <slot name="extra" />
                <Link v-if="shell.links.sign_out" :href="shell.links.sign_out" method="post" as="button" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-sign-out>
                    <LogOut class="size-4" aria-hidden="true" />
                    Sign out
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
