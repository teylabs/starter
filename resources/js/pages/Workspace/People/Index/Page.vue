<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Avatar, Button, ConfirmDialog, Field, FormCard, Input, Page, PasswordInput, Section, Select, SettingsFrame } from '@kit/ui';
import { computed, ref } from 'vue';

// Settings › People: the members (avatar, name, email, role, joined), the invitations still open, invite by email with a role, and transfer ownership. The list is
// the server's (search and sort are in the query string); every action is refused by the server too, so a hidden control is a convenience, never the gate.
// Removing a member, revoking an invitation and transferring ownership each ask first.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.PeopleViewModel>();
const search = ref(props.search);
function find() { router.get(window.location.pathname, { filter: { search: search.value || undefined }, sort: props.sort || undefined }, { preserveState: true, replace: true }); }
function sortBy(key: string) { router.get(window.location.pathname, { filter: { search: search.value || undefined }, sort: props.sort === key ? `-${key}` : key }, { preserveState: true, replace: true }); }
function changeRole(url: string, role: string) { router.put(url, { role }, { preserveScroll: true }); }
function remove(url: string) { router.delete(url, { preserveScroll: true }); }
function resend(url: string) { router.put(url, {}, { preserveScroll: true }); }
function revoke(url: string) { router.delete(url, { preserveScroll: true }); }
const roleOptions = computed(() => props.roles.map((role) => ({ value: role.value, label: role.label })));
const heirs = computed(() => props.members.filter((member) => member.transferable).map((member) => ({ value: String(member.id), label: `${member.name} (${member.email})` })));
const joined = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '');
</script>
<template>
    <Page lg>
        <SettingsFrame>
            <div class="grid gap-6">
                <Form :action="invite_url" method="post" :options="{ preserveScroll: true }" reset-on-success>
                    <fieldset :disabled="!can_manage" class="contents">
                        <FormCard title="Invite someone" description="They get a link to join this workspace with the role you choose." submit-label="Send invitation" busy-label="Sending…" :cancel="false" :sticky="false">
                            <p v-if="!can_manage" class="col-span-6 text-sm text-muted-foreground">You don't have permission to invite people.</p>
                            <p v-if="invite_link" class="col-span-6 text-sm" role="status" data-invite-link>Invitation link (send it to them; it is shown once): <code class="break-all">{{ invite_link }}</code></p>
                            <Field id="invite-email" name="email" label="Email address" :span="4"><Input id="invite-email" name="email" type="email" required aria-describedby="invite-email-error" /></Field>
                            <Field id="invite-role" name="role" label="Role" :span="2"><Select id="invite-role" name="role" :model-value="roles[0]?.value" :options="roleOptions" /></Field>
                        </FormCard>
                    </fieldset>
                </Form>
                <Section title="Members">
                    <form class="flex flex-wrap items-center gap-2" role="search" @submit.prevent="find">
                        <Input id="people-search" v-model="search" type="search" placeholder="Search name or email" class="max-w-xs" aria-label="Search people" />
                        <Button>Search</Button>
                        <Button variant="secondary" type="button" @click="sortBy('name')">Sort by name</Button>
                        <Button variant="secondary" type="button" @click="sortBy('joined')">Sort by joined</Button>
                    </form>
                    <p v-if="!members.length" class="text-sm text-muted-foreground">No one matches.</p>
                    <ul class="grid divide-y divide-border">
                        <li v-for="member in members" :key="member.id" class="flex flex-wrap items-center gap-3 py-3" data-person>
                            <Avatar :initials="member.initials" :src="member.avatar?.url" :src2x="member.avatar?.url_2x" />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate text-sm font-medium">{{ member.name }}<span v-if="member.is_you" class="font-normal text-muted-foreground"> (you)</span></span>
                                <span class="truncate text-sm text-muted-foreground">{{ member.email }}</span>
                            </span>
                            <span class="w-36">
                                <Select v-if="member.manageable && roles.length" :id="`role-${member.id}`" :model-value="member.role" :options="roleOptions" @update:model-value="changeRole(member.update_url, String($event))" />
                                <span v-else class="text-sm">{{ member.role_label }}</span>
                            </span>
                            <span class="w-28 text-sm text-muted-foreground">{{ joined(member.joined_at) }}</span>
                            <span v-if="member.manageable">
                                <ConfirmDialog :title="`Remove ${member.name}?`" description="They lose access to this workspace straight away. You can invite them again later." confirm-label="Remove member" @confirm="remove(member.destroy_url)">
                                    <Button variant="destructive" type="button">Remove</Button>
                                </ConfirmDialog>
                            </span>
                        </li>
                    </ul>
                </Section>
                <Form v-if="can_transfer" v-slot="{ submit }" :action="transfer_url" method="put" :options="{ preserveScroll: true }" reset-on-success>
                    <FormCard title="Transfer ownership" description="Pass ownership to another member. They become the owner and you become an admin. Confirm with your password." submit-label="Transfer ownership" :cancel="false" :sticky="false">
                        <p v-if="!heirs.length" class="col-span-6 text-sm text-muted-foreground">There is no other verified member to pass ownership to yet.</p>
                        <template v-else>
                            <Field id="transfer-member" name="membership" label="New owner" :span="3"><Select id="transfer-member" name="membership" :options="heirs" placeholder="Choose a member" /></Field>
                            <Field id="transfer-password" name="password" label="Your password" :span="3"><PasswordInput id="transfer-password" name="password" required autocomplete="current-password" aria-describedby="transfer-password-error" /></Field>
                        </template>
                        <template v-if="heirs.length" #submit>
                            <ConfirmDialog title="Transfer ownership?" description="They become the owner and you become an admin. Only the new owner can undo this." confirm-label="Transfer ownership" @confirm="submit()">
                                <Button variant="destructive" type="button" data-test="transfer-ownership">Transfer ownership</Button>
                            </ConfirmDialog>
                        </template>
                    </FormCard>
                </Form>
                <Section title="Pending invitations">
                    <p v-if="!invitations.length" class="text-sm text-muted-foreground">No pending invitations.</p>
                    <ul v-else class="grid divide-y divide-border">
                        <li v-for="invitation in invitations" :key="invitation.id" class="flex flex-wrap items-center gap-3 py-3" data-invitation>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate text-sm font-medium">{{ invitation.email }}</span>
                                <span class="text-sm text-muted-foreground">{{ invitation.role_label }} · {{ invitation.expired ? 'expired' : 'expires' }} {{ joined(invitation.expires_at) }}<template v-if="invitation.invited_by"> · invited by {{ invitation.invited_by }}</template></span>
                            </span>
                            <span v-if="can_manage" class="flex gap-2">
                                <Button variant="secondary" type="button" @click="resend(invitation.resend_url)">Resend</Button>
                                <ConfirmDialog :title="`Revoke the invitation to ${invitation.email}?`" description="The link stops working. You can invite them again later." confirm-label="Revoke invitation" @confirm="revoke(invitation.revoke_url)">
                                    <Button variant="destructive" type="button">Revoke</Button>
                                </ConfirmDialog>
                            </span>
                        </li>
                    </ul>
                </Section>
            </div>
        </SettingsFrame>
    </Page>
</template>
