<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, Badge, Button, Dialog, EmptyState, Page, PageSection, Surface } from '@kit/ui';
const props = defineProps<Tey.Kit.Features.Workspaces.ViewModels.IndexWorkspaceViewModel>();
// The person's workspaces, each on its own card: the mark, their role, the member count and a Current marker on the one they are in or used last. The whole card
// is the workspace's link (its name stretched over the card, so the focus ring is the card's); a workspace with no page they may open is shown, not linked.
// Leave is there for everyone but the owner, who has to transfer ownership first; it asks before it acts. New workspace is a header action, sent only when
// provisioning allows it (with no workspace, the empty state offers it instead, so it is there once). Open invitations come first: each is accepted from the
// link in its email.
// The link into each workspace is the shell's: its switcher choices, or the one workspace the bar names. No link means no page there the person may open.
const page = usePage<{ shell?: Tey.Kit.Features.Shell.Data.ShellData }>();
const urls = computed(() => new Map((page.props.shell?.switcher?.choices ?? (page.props.shell?.workspace ? [page.props.shell.workspace] : [])).map((choice) => [choice.id, choice.url ?? null])));
const actions = computed(() => (props.create ? [props.create] : []));
const members = (count: number) => `${count} ${count === 1 ? 'member' : 'members'}`;
</script>
<template>
    <Page :actions="workspaces.length ? actions : []">
        <PageSection v-if="invitations.length" title="Invitations" description="Open the link in the email you were sent to join." surface="none" data-workspace-invitations>
            <ul class="grid gap-3 sm:grid-cols-2">
                <li v-for="invitation in invitations" :key="invitation.workspace + invitation.expires">
                    <Surface padding="sm" class="grid h-full gap-1 text-sm" data-workspace-invitation>
                        <p class="font-medium break-words">{{ invitation.workspace }}</p>
                        <p class="text-muted-foreground">{{ invitation.inviter ?? 'Someone' }} invited you.</p>
                        <p v-if="invitation.message" class="text-muted-foreground break-words">“{{ invitation.message }}”</p>
                    </Surface>
                </li>
            </ul>
        </PageSection>
        <EmptyState v-if="!workspaces.length" title="No workspaces yet" :body="create ? 'Start a workspace, or ask an admin to invite you to theirs.' : 'Ask an admin to invite you to their workspace. It shows here once you join.'" :actions="actions" :level="2" data-workspaces-empty />
        <PageSection v-else :title="invitations.length ? 'Workspaces' : undefined" :aria-label="invitations.length ? undefined : 'Workspaces'" surface="none">
            <ul class="grid gap-3 sm:grid-cols-2" data-workspace-list>
                <li v-for="workspace in workspaces" :key="workspace.id">
                    <Surface padding="sm" class="relative flex h-full items-center gap-3 transition-colors has-[a:hover]:bg-accent/40 has-[a:focus-visible]:ring-2 has-[a:focus-visible]:ring-ring has-[a:focus-visible]:ring-offset-2 has-[a:focus-visible]:ring-offset-background" :data-workspace="workspace.id" :data-current="workspace.current || undefined">
                        <Avatar :initials="workspace.initials" class="size-10 rounded-lg text-sm" />
                        <div class="grid min-w-0 flex-1 gap-0.5">
                            <Link v-if="urls.get(workspace.id)" :href="urls.get(workspace.id)!" class="font-medium break-words outline-none after:absolute after:inset-0" data-workspace-link>{{ workspace.name }}</Link>
                            <span v-else class="font-medium break-words">{{ workspace.name }} <span class="font-normal text-muted-foreground">· unavailable</span></span>
                            <p class="text-sm text-muted-foreground"><span v-if="workspace.role" data-workspace-role>{{ workspace.role }} · </span><span data-workspace-members>{{ members(workspace.members) }}</span></p>
                        </div>
                        <Badge v-if="workspace.current" tone="info" data-workspace-current>Current</Badge>
                        <Dialog variant="danger" v-if="workspace.leave?.confirm && !workspace.leave.disabled" :title="workspace.leave.confirm.title ?? `${workspace.leave.label}?`" :description="workspace.leave.confirm.description" :confirm-label="workspace.leave.confirm.label" @confirm="router.delete(workspace.leave!.url)">
                            <Button variant="secondary" size="sm" type="button" class="relative z-10" data-test="leave-workspace">{{ workspace.leave.label }}</Button>
                        </Dialog>
                    </Surface>
                </li>
            </ul>
        </PageSection>
    </Page>
</template>
