<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Menu } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import UserAvatar from './UserAvatar.vue';
import { loadWorkspaceLandings, workspaceLanding } from './workspaceLandings';
import { Button, Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@kit/ui';

// Below `lg` the same items, in the same order: workspaces, pages, the account, then Sign out. Any navigation closes it.
// `pages` false leaves the workspace's pages out: the Layout's `nav` slot shows them at every width instead. The `nav` slot replaces them either way.
const props = withDefaults(defineProps<{ shell: Tey.Kit.Features.Shell.Data.ShellData; pages?: boolean }>(), { pages: true });

const open = ref(false);
// The workspaces' pages, as the switcher has them: the bar's own from the server, the others loaded when the drawer opens.
const landing = (id: number) => (id === props.shell.workspace?.id ? props.shell.workspace.url : workspaceLanding(id));
watch(open, (opened) => {
    if (opened && (props.shell.switcher?.choices ?? []).some((choice) => choice.id !== props.shell.workspace?.id)) loadWorkspaceLandings(props.shell.switcher?.landings);
});
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
                    <Link v-if="landing(choice.id)" :href="landing(choice.id)!" :aria-current="choice.current ? 'true' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', choice.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ choice.name }}</Link>
                    <span v-else-if="landing(choice.id) === undefined" class="flex min-h-11 items-center px-3 text-sm text-muted-foreground" aria-busy="true" data-shell-switcher-loading>{{ choice.name }}</span>
                    <span v-else class="flex min-h-11 items-center px-3 text-sm text-muted-foreground">{{ choice.name }} · unavailable</span>
                </template>
            </nav>
            <!-- The nav slot (the Layout's drawer-nav) replaces the kit's pages with the app's own list. -->
            <div v-if="$slots.nav" class="px-2 pt-4" data-shell-drawer-nav><slot name="nav" /></div>
            <nav v-else-if="pages && shell.navigation.length" :aria-label="shell.workspace?.name ?? shell.product.name" class="grid gap-1 px-2 pt-4">
                <template v-for="item in shell.navigation" :key="item.url">
                    <Link :href="item.url" :aria-current="item.current && !(item.label === shell.subNavigation?.label && (shell.subNavigation.child || shell.subNavigation.items.some((tab) => tab.group))) ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', item.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ item.label }}</Link>
                    <!-- A sidebar module (pages with a `group`): its pages under the link, under their group headings. -->
                    <div v-if="item.current && shell.subNavigation && !shell.subNavigation.child && item.label === shell.subNavigation.label && shell.subNavigation.items.some((tab) => tab.group)" class="ml-4 grid gap-2 border-l border-border pl-2" data-shell-drawer-sidebar>
                        <ul v-for="group in [...new Set(shell.subNavigation.items.map((tab) => tab.group ?? ''))]" :key="group" :aria-label="group || shell.subNavigation.label" class="grid gap-1">
                            <li v-if="group" aria-hidden="true" class="px-3 pt-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ group }}</li>
                            <li v-for="tab in shell.subNavigation.items.filter((each) => (each.group ?? '') === group)" :key="tab.url">
                                <Link :href="tab.url" :aria-current="tab.current ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', tab.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ tab.label }}</Link>
                            </li>
                        </ul>
                    </div>
                    <!-- A section whose current tab is a nested module: the section's tabs under it, and that module's pages expanded in place under its tab. -->
                    <ul v-if="item.current && shell.subNavigation?.child && item.label === shell.subNavigation.label" :aria-label="shell.subNavigation.label" class="ml-4 grid gap-1 border-l border-border pl-2" data-shell-drawer-section>
                        <li v-for="tab in shell.subNavigation.items" :key="tab.url">
                            <Link :href="tab.url" :aria-current="tab.current && !shell.subNavigation.child ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', tab.current ? 'text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ tab.label }}</Link>
                            <ul v-if="tab.current" :aria-label="shell.subNavigation.child.label" class="ml-4 grid gap-1 border-l border-border pl-2" data-shell-drawer-child>
                                <li v-for="page in shell.subNavigation.child.items" :key="page.url">
                                    <Link :href="page.url" :aria-current="page.current ? 'page' : undefined" :class="['flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', page.current ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground']">{{ page.label }}</Link>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </template>
            </nav>
            <div class="mt-auto border-t border-border px-2 py-4">
                <div class="flex items-center gap-3 px-3 pb-3">
                    <UserAvatar :initials="shell.user.initials" :src="shell.user.avatar_url" :src2x="shell.user.avatar_url_2x" :color="shell.user.avatar_color" />
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
