<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Avatar, Button, DataTable, Dialog, Field, FormActions, Input, Page, Select } from '@kit/ui';
import { computed, ref } from 'vue';

// Settings › People: the Members and Invitations tabs as one server-driven table. Concierge decides the rows, the counts and each row's actions (an admin has none on
// the owner); this page only renders them. Removing and revoking ask first (the action's own confirm text, in the table); changing a role and inviting open a dialog.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.PeopleViewModel>();
type Row = Record<string, any>;
const roleOptions = computed(() => props.roles.map((role) => ({ value: role.value, label: role.label })));
const date = (iso: unknown) => (typeof iso === 'string' && iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '');
const inviting = ref(false);
const changing = ref<{ row: Row; url: string } | null>(null);
const changeOpen = computed({ get: () => changing.value !== null, set: (open: boolean) => { if (!open) changing.value = null; } });
function act(row: Row, action: { key: string | null; url: string }) {
    if (action.key === 'change-role') changing.value = { row, url: action.url };
}
const members = computed(() => props.tab === 'members');
</script>
<template>
    <Page>
        <template v-if="invite" #actions>
            <Dialog v-model:open="inviting" title="Invite member" description="They get a link to join this workspace with the role you choose.">
                <template #trigger><Button type="button" data-invite>{{ invite.label }}</Button></template>
                <Form :action="invite.url" method="post" :options="{ preserveScroll: true }" reset-on-success @success="inviting = false" class="grid gap-4">
                    <Field id="invite-email" name="email" label="Email address"><Input id="invite-email" name="email" type="email" required aria-describedby="invite-email-error" /></Field>
                    <Field id="invite-role" name="role" label="Role"><Select id="invite-role" name="role" :model-value="roles[0]?.value" :options="roleOptions" /></Field>
                    <FormActions submit-label="Send invitation" busy-label="Sending…" :sticky="false" :warn-on-leave="false" :cancel="false"><template #actions><Button variant="secondary" type="button" @click="inviting = false">Cancel</Button></template></FormActions>
                </Form>
            </Dialog>
        </template>
        <p v-if="invite_link" class="mb-4 text-sm" role="status" data-invite-link>Invitation link (send it to them; it is shown once): <code class="break-all">{{ invite_link }}</code></p>
        <DataTable :table="table as never" :intercept="['change-role']" :search-label="members ? 'Search members' : 'Search invitations'" @act="act">
            <template #cell-member="{ row }">
                <span class="flex items-center gap-3" data-person>
                    <Avatar :initials="row.member.initials" :src="row.member.avatar?.url" :src2x="row.member.avatar?.url_2x" />
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate font-medium">{{ row.member.name }}<span v-if="row.member.is_you" class="font-normal text-muted-foreground"> (you)</span></span>
                        <span class="truncate text-muted-foreground">{{ row.member.email }}</span>
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
        <Dialog v-model:open="changeOpen" :title="changing ? `Change role for ${changing.row.member.name}` : 'Change role'">
            <Form v-if="changing" :action="changing.url" method="put" :options="{ preserveScroll: true }" class="grid gap-4" @success="changing = null">
                <Field id="change-role" name="role" label="Role"><Select id="change-role" name="role" :model-value="changing.row.role" :options="roleOptions" /></Field>
                <FormActions submit-label="Save" busy-label="Saving…" :sticky="false" :warn-on-leave="false" :cancel="false"><template #actions><Button variant="secondary" type="button" @click="changing = null">Cancel</Button></template></FormActions>
            </Form>
        </Dialog>
    </Page>
</template>
