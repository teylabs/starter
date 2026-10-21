<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button, ConfirmDialog, Page } from '@kit/ui';
const props = defineProps<Tey.Kit.Features.Workspaces.ViewModels.IndexWorkspaceViewModel>();
// Each workspace opens at its first page; the shell's choices already carry the link and what the user may reach. Leave is there for everyone but the owner,
// who has to transfer ownership first; it asks before it acts.
const page = usePage<{ shell?: Tey.Kit.Features.SessionUi.Data.ShellData }>();
const choices = computed(() => page.props.shell?.switcher.choices ?? []);
const leaveUrl = (id: number) => props.workspaces.find((workspace) => workspace.id === id)?.leave_url ?? null;
</script>
<template>
    <Page sm>
    <p v-if="!choices.length">You have no active workspaces.</p>
    <ul v-else class="grid gap-2">
        <li v-for="choice in choices" :key="choice.id" class="flex items-center gap-3">
            <Link v-if="choice.url" :href="choice.url" class="text-sm font-medium underline-offset-4 hover:underline">{{ choice.name }}</Link>
            <span v-else class="text-sm text-muted-foreground">{{ choice.name }} · unavailable</span>
            <ConfirmDialog v-if="leaveUrl(choice.id)" :title="`Leave ${choice.name}?`" description="You lose access straight away. An admin can invite you again." confirm-label="Leave workspace" @confirm="router.delete(leaveUrl(choice.id)!)">
                <Button variant="secondary" type="button" data-test="leave-workspace">Leave</Button>
            </ConfirmDialog>
        </li>
    </ul>
    </Page>
</template>
