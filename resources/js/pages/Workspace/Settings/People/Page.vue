<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ModalLink, visitModal } from '@inertiaui/modal-vue';
import { Avatar, BlockStack, Button, Dialog, DataTable, Field, Page, PasswordInput } from '@kit/ui';
import { computed, ref } from 'vue';

// Settings › People: the Members and Invitations tabs as one server-driven table. Concierge decides the rows, the counts and each row's actions (an admin has none on
// the owner); this page only renders them. Removing and revoking ask first (the action's own confirm text, in the table); changing a role and inviting open a dialog.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.PeopleViewModel>();
type Row = Record<string, any>;
const roleOptions = computed(() => props.roles.map((role) => ({ value: role.value, label: role.label })));
const date = (iso: unknown) => (typeof iso === 'string' && iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '');
// Server-driven modals over this page, from the descriptors' URLs (the same URL the change is sent to), opened in history so Back closes them. Invite is the package's ModalLink,
// which prefetches on hover and shows its loading state; a row action's modal opens through `visitModal`.
const opening = ref(false);
async function open(url: string) {
    opening.value = true;
    try { await visitModal(url, { navigate: true }); } finally { opening.value = false; }
}
type Confirm = { title: string; description: string; label: string };
const making = ref<{ row: Row; url: string; confirm: Confirm } | null>(null);
const makeOpen = computed({ get: () => making.value !== null, set: (open: boolean) => { if (!open) { making.value = null; password.value = ''; } } });
const password = ref('');
function act(row: Row, action: { key: string | null; url: string; confirm: Confirm | null }) {
    if (action.key === 'change-role') open(action.url);
    if (action.key === 'transfer-ownership' && action.confirm) making.value = { row, url: action.url, confirm: action.confirm };
}
// Make owner confirms with the owner's password; the server checks it again and refuses a wrong one.
function makeOwner() {
    if (!making.value) return;
    router.put(making.value.url, { membership: making.value.row.id, password: password.value }, { preserveScroll: true, onSuccess: () => { making.value = null; password.value = ''; } });
}
const passwordError = computed(() => (usePage().props.errors as Record<string, string>).password);
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
                <template #cell-roles="{ row }">{{ (row.roles as Row[]).map((role) => role.label).join(', ') }}</template>
                <template #cell-joined="{ row }">{{ date(row.joined) }}</template>
                <template #cell-sent="{ row }">{{ date(row.sent) }}</template>
                <template #cell-expires="{ row }"><span :class="row.status === 'expired' ? 'text-destructive' : ''">{{ row.status === 'expired' ? 'Expired ' : '' }}{{ date(row.expires) }}</span></template>
                <template #empty-first-use>
                    <p class="font-medium">No invitations yet</p>
                    <p class="text-sm text-muted-foreground">Invite someone and they show up here until they join.</p>
                </template>
                <template #empty-no-results><p class="font-medium">{{ members ? 'No members match' : 'No invitations match' }}</p></template>
            </DataTable>
        </BlockStack>
        <Dialog variant="danger" v-if="making" v-model:open="makeOpen" :title="making.confirm.title" :description="making.confirm.description" :confirm-label="making.confirm.label" @confirm="makeOwner">
            <template #body>
                <Field id="make-owner-password" label="Your password" :error="passwordError"><PasswordInput id="make-owner-password" v-model="password" autocomplete="current-password" /></Field>
            </template>
        </Dialog>
    </Page>
</template>
