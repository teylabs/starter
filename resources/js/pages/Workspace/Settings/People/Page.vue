<script setup lang="ts">
import { ModalLink, visitModal } from '@inertiaui/modal-vue';
import { Avatar, BlockStack, DataTable, DateTime, Page } from '@kit/ui';
import { computed, ref } from 'vue';

// Settings › People: the Members and Invitations tabs as one server-driven table. Concierge decides the rows, the counts and each row's actions (an admin has none on
// the owner); this page only renders them. Removing and revoking ask first (the action's own confirm text, in the table); changing a role, making someone owner and inviting open a dialog.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.PeopleViewModel>();
type Row = Record<string, any>;
// Named, not inline: vue-tsc 2 can't parse an inline object type in a template expression (TS1005).
type RoleLabel = { label: string };
// Server-driven modals over this page, from the descriptors' URLs (the same URL the change is sent to), opened in history so Back closes them. Invite is the package's ModalLink,
// which prefetches on hover and shows its loading state; a row action's modal opens through `visitModal`.
const opening = ref(false);
async function open(url: string) {
    opening.value = true;
    try { await visitModal(url, { navigate: true }); } finally { opening.value = false; }
}
function act(_row: Row, action: { key: string | null; url: string }) {
    // Change role and Make owner open their server-driven modals (Make owner's asks for the workspace's name and your password; the server checks both).
    if (action.key === 'change-role' || action.key === 'transfer-ownership') open(action.url);
}
const members = computed(() => props.tab === 'members');
</script>
<template>
    <Page md>
        <template v-if="invite" #actions>
            <ModalLink :href="invite.url" as="a" navigate prefetch :cache-for="30000" class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md bg-primary px-4 text-sm font-medium whitespace-nowrap text-primary-foreground hover:bg-primary/90 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none" data-invite>{{ invite.label }}</ModalLink>
        </template>
        <BlockStack>
            <p v-if="invite_link" class="text-sm" role="status" data-invite-link>Invitation link (send it to them; it is shown once): <code class="break-all">{{ invite_link }}</code></p>
            <DataTable :table="table as never" :intercept="['change-role', 'transfer-ownership']" :search-label="members ? 'Search members' : 'Search invitations'" @act="act">
                <template #cell-member="{ row }">
                    <span class="flex items-center gap-3" data-person>
                        <Avatar :initials="row.member.initials" :src="row.member.avatar?.url" :src2x="row.member.avatar?.url_2x" />
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate font-medium">{{ row.member.name }}<span v-if="row.member.is_you" class="font-normal text-muted-foreground"> (you)</span></span>
                            <span class="truncate text-muted-foreground">{{ row.member.email }}</span>
                            <span v-if="row.member.invited_as" class="truncate text-xs text-muted-foreground" data-invited-as>Invited as {{ row.member.invited_as }}</span>
                        </span>
                    </span>
                </template>
                <!-- The server's role labels, as sent: the page shows them and checks nothing. -->
                <template #cell-roles="{ value }">{{ ((value ?? []) as RoleLabel[]).map((granted) => granted.label).join(', ') }}</template>
                                                <template #cell-expires="{ row }"><span :class="row.status === 'expired' ? 'text-destructive-text' : ''">{{ row.status === 'expired' ? 'Expired ' : '' }}<DateTime v-if="row.expires" :value="row.expires" format="date" /></span></template>
                <template #empty-first-use>
                    <p class="font-medium">No invitations yet</p>
                    <p class="text-sm text-muted-foreground">Invite someone and they show up here until they join.</p>
                </template>
                <template #empty-no-results><p class="font-medium">{{ members ? 'No members match' : 'No invitations match' }}</p></template>
            </DataTable>
        </BlockStack>
    </Page>
</template>
