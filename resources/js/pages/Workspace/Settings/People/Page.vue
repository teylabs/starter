<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Avatar, Button, ConfirmDialog, Field, FormCard, Input, Page, Section, Select } from '@kit/ui';
import { computed, ref } from 'vue';

// Settings › People: the members (avatar, name, email, role, joined), the invitations still open, invite by email with a role. The list is
// the server's (search and sort are in the query string); every action is refused by the server too, so a hidden control is a convenience, never the gate.
// Removing a member and revoking an invitation each ask first.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.PeopleViewModel>();
const search = ref(props.search);
function find() { router.get(window.location.pathname, { filter: { search: search.value || undefined }, sort: props.sort || undefined }, { preserveState: true, replace: true }); }
function sortBy(key: string) { router.get(window.location.pathname, { filter: { search: search.value || undefined }, sort: props.sort === key ? `-${key}` : key }, { preserveState: true, replace: true }); }
function changeRole(url: string, role: string) { router.put(url, { role }, { preserveScroll: true }); }
function remove(url: string) { router.delete(url, { preserveScroll: true }); }
function resend(url: string) { router.put(url, {}, { preserveScroll: true }); }
function revoke(url: string) { router.delete(url, { preserveScroll: true }); }
const roleOptions = computed(() => props.roles.map((role) => ({ value: role.value, label: role.label })));
const joined = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '');
</script>
<template>
    <Page lg>
            <div class="grid gap-6">
                <Form :action="invite.url" method="post" :options="{ preserveScroll: true }" reset-on-success>
                    <fieldset :disabled="invite.disabled" class="contents">
                        <FormCard title="Invite someone" description="They get a link to join this workspace with the role you choose." submit-label="Send invitation" busy-label="Sending…" :cancel="false" :sticky="false">
                            <p v-if="invite.reason" class="col-span-6 text-sm text-muted-foreground">{{ invite.reason }}</p>
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
                                <Select v-if="member.change_role" :id="`role-${member.id}`" :model-value="member.role" :options="roleOptions" @update:model-value="changeRole(member.change_role.url, String($event))" />
                                <span v-else class="text-sm">{{ member.role_label }}</span>
                            </span>
                            <span class="w-28 text-sm text-muted-foreground">{{ joined(member.joined_at) }}</span>
                            <span v-if="member.remove?.confirm">
                                <ConfirmDialog :title="member.remove.confirm.title" :description="member.remove.confirm.description" :confirm-label="member.remove.confirm.label" @confirm="remove(member.remove.url)">
                                    <Button variant="destructive" type="button">{{ member.remove.label }}</Button>
                                </ConfirmDialog>
                            </span>
                        </li>
                    </ul>
                </Section>
                <Section title="Pending invitations">
                    <p v-if="!invitations.length" class="text-sm text-muted-foreground">No pending invitations.</p>
                    <ul v-else class="grid divide-y divide-border">
                        <li v-for="invitation in invitations" :key="invitation.id" class="flex flex-wrap items-center gap-3 py-3" data-invitation>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate text-sm font-medium">{{ invitation.email }}</span>
                                <span class="text-sm text-muted-foreground">{{ invitation.role_label }} · {{ invitation.expired ? 'expired' : 'expires' }} {{ joined(invitation.expires_at) }}<template v-if="invitation.invited_by"> · invited by {{ invitation.invited_by }}</template></span>
                            </span>
                            <span v-if="invitation.resend && invitation.revoke?.confirm" class="flex gap-2">
                                <Button variant="secondary" type="button" @click="resend(invitation.resend.url)">{{ invitation.resend.label }}</Button>
                                <ConfirmDialog :title="invitation.revoke.confirm.title" :description="invitation.revoke.confirm.description" :confirm-label="invitation.revoke.confirm.label" @confirm="revoke(invitation.revoke.url)">
                                    <Button variant="destructive" type="button">{{ invitation.revoke.label }}</Button>
                                </ConfirmDialog>
                            </span>
                        </li>
                    </ul>
                </Section>
            </div>
    </Page>
</template>
