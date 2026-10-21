<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button, Dialog, Page } from '@kit/ui';
const props = defineProps<Tey.Kit.Features.Workspaces.ViewModels.IndexWorkspaceViewModel>();
// Each workspace opens at its first page; the shell already carries the link and what the user may reach (the switcher's choices, or the one workspace the bar names). Leave is there for everyone but the owner,
// who has to transfer ownership first; it asks before it acts.
const page = usePage<{ shell?: Tey.Kit.Features.SessionUi.Data.ShellData }>();
const choices = computed(() => page.props.shell?.switcher?.choices ?? (page.props.shell?.workspace ? [{ ...page.props.shell.workspace, current: true }] : []));
const leaveFor = (id: number) => props.workspaces.find((workspace) => workspace.id === id)?.leave ?? null;
</script>
<template>
    <Page sm>
    <p v-if="!choices.length">You have no active workspaces.</p>
    <ul v-else class="grid gap-2">
        <li v-for="choice in choices" :key="choice.id" class="flex items-center gap-3">
            <Link v-if="choice.url" :href="choice.url" class="text-sm font-medium underline-offset-4 hover:underline">{{ choice.name }}</Link>
            <span v-else class="text-sm text-muted-foreground">{{ choice.name }} · unavailable</span>
            <Dialog variant="danger" v-if="leaveFor(choice.id)?.confirm && !leaveFor(choice.id)?.disabled" :title="leaveFor(choice.id)!.confirm!.title" :description="leaveFor(choice.id)!.confirm!.description" :confirm-label="leaveFor(choice.id)!.confirm!.label" @confirm="router.delete(leaveFor(choice.id)!.url)">
                <Button variant="secondary" type="button" data-test="leave-workspace">{{ leaveFor(choice.id)!.label }}</Button>
            </Dialog>
        </li>
    </ul>
    </Page>
</template>
