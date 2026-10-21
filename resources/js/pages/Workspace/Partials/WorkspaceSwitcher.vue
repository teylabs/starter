<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Dialog, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@kit/ui';

// The workspace in the bar, beside the product symbol: its name (a link home) and a caret (the menu). The server sends `workspace` (the current one, or the only one) and `switcher` (only with two or more);
// this renders what is there: the workspaces to switch to, "Workspace settings" when the server sends that action, and the server's `all` action (the chooser; null with one workspace). The trigger is one truncated line,
// the full name in `title`.
type Shell = Tey.Kit.Features.SessionUi.Data.ShellData;
defineOptions({ inheritAttrs: false });
const props = defineProps<{ workspace: Shell['workspace']; switcher: Shell['switcher']; account: Shell['account'] }>();
// Leave workspace is the server's action, there only for a member who can leave; it asks first (opened after the menu has closed, or the menu's own dismissal swallows it).
const asking = ref(false);
const ask = () => setTimeout(() => (asking.value = true), 0);
const leave = computed(() => props.workspace?.leave ?? null);
const name = () => props.workspace?.name ?? props.switcher?.current?.name ?? 'Workspaces';
</script>
<template>
    <!-- A split control: the name goes to the workspace's home (the server's URL), the caret opens the menu. Two focus targets in one group with no line between them: each half has its own hover background, so the split shows only on interaction; the name truncates, the caret never shrinks. -->
    <div v-bind="$attrs" class="flex max-w-full min-w-0 items-center rounded-md" data-shell-switcher-group>
        <component :is="workspace?.url ? Link : 'span'" :href="workspace?.url ?? undefined" class="flex min-h-11 min-w-0 items-center rounded-l-md px-2 text-sm font-medium text-shell-bar-foreground focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none" :class="workspace?.url ? 'hover:bg-shell-bar-foreground/10' : ''" :title="name()" data-shell-home>
            <span class="min-w-0 truncate" data-shell-workspace>{{ name() }}</span>
        </component>
        <DropdownMenu :modal="false">
            <DropdownMenuTrigger class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-r-md text-shell-bar-foreground/70 hover:bg-shell-bar-foreground/10 hover:text-shell-bar-foreground focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none" :aria-label="'Switch workspace'" aria-haspopup="menu" data-shell-switcher>
                <ChevronsUpDown class="size-3.5" aria-hidden="true" />
            </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="min-w-56">
            <!-- One list, always: the workspaces (the current one checked), then Workspace settings, then Your account (checked on an account page). All of it is the server's. -->
            <!-- The "Workspaces" heading is a real menu item drawn as a text link (an underline on hover and focus, no chevron: nothing opens from it): it goes to the chooser (the server's `all` action), so the keyboard reaches it too. -->
            <DropdownMenuItem v-if="workspace?.all" as-child class="min-h-8 focus:bg-transparent data-[highlighted]:bg-transparent">
                <Link :href="workspace.all.url" :aria-label="workspace.all.label" class="w-full text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:text-foreground focus-visible:underline" data-shell-all-workspaces>Workspaces</Link>
            </DropdownMenuItem>
            <DropdownMenuLabel v-else class="text-xs font-medium text-muted-foreground">Workspaces</DropdownMenuLabel>
            <template v-for="choice in switcher?.choices ?? []" :key="choice.id">
                <DropdownMenuItem v-if="choice.url" as-child>
                    <Link :href="choice.url" :aria-current="choice.current ? 'true' : undefined" class="flex min-h-9 w-full items-center justify-between gap-3">
                        <span class="truncate">{{ choice.name }}</span>
                        <Check v-if="choice.current" class="size-4" aria-hidden="true" />
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem v-else disabled>{{ choice.name }} · unavailable</DropdownMenuItem>
            </template>
            <template v-if="workspace?.settings || leave">
                <DropdownMenuSeparator />
                <DropdownMenuItem v-if="workspace?.settings" as-child>
                    <Link :href="workspace.settings.url" class="min-h-9 w-full">{{ workspace.settings.label }}</Link>
                </DropdownMenuItem>
                <DropdownMenuItem v-if="leave" class="text-destructive focus:text-destructive" data-shell-leave @select="ask">{{ leave.label }}</DropdownMenuItem>
            </template>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="account.url" :aria-current="account.current ? 'page' : undefined" class="flex min-h-9 w-full items-center justify-between gap-3" data-shell-account>
                    <span class="truncate">{{ account.label }}</span>
                    <Check v-if="account.current" class="size-4" aria-hidden="true" />
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
        </DropdownMenu>
    </div>
    <Dialog variant="danger" v-if="leave?.confirm" v-model:open="asking" :title="leave.confirm.title" :description="leave.confirm.description" :confirm-label="leave.confirm.label" @confirm="router.delete(leave.url)" />
</template>
