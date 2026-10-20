<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Avatar, Button, Field, FormActions, Input, Page, Section, Select, SettingsFrame } from '@kit/ui';
import { ref } from 'vue';

// Settings › People: the members (avatar, name, email, role, joined), the invitations still open, and invite by email with a role. The list is the server's
// (search and sort are in the query string); every action is refused by the server too, so a hidden control is a convenience, never the gate.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.PeopleViewModel>();
const invite = useForm({ email: '', role: props.roles[0]?.value ?? '' });
const search = ref(props.search);
const confirming = ref<number | null>(null);
function sendInvite() { invite.post(props.invite_url, { preserveScroll: true, onSuccess: () => invite.reset('email') }); }
function find() { router.get(window.location.pathname, { filter: { search: search.value || undefined }, sort: props.sort || undefined }, { preserveState: true, replace: true }); }
function sortBy(key: string) { router.get(window.location.pathname, { filter: { search: search.value || undefined }, sort: props.sort === key ? `-${key}` : key }, { preserveState: true, replace: true }); }
function changeRole(url: string, role: string) { router.put(url, { role }, { preserveScroll: true }); }
function remove(url: string) { router.delete(url, { preserveScroll: true, onFinish: () => (confirming.value = null) }); }
function resend(url: string) { router.put(url, {}, { preserveScroll: true }); }
function revoke(url: string) { router.delete(url, { preserveScroll: true }); }
const joined = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '');
</script>
<template>
    <Page lg>
        <SettingsFrame>
            <div class="grid gap-6">
                <Section title="Invite someone" description="They get a link to join this workspace with the role you choose.">
                    <p v-if="!can_manage" class="text-sm text-muted-foreground">You don't have permission to invite people.</p>
                    <form class="grid gap-4 sm:grid-cols-[1fr_12rem]" @submit.prevent="sendInvite">
                        <Field id="invite-email" label="Email address" :error="invite.errors.email">
                            <Input id="invite-email" v-model="invite.email" type="email" required :disabled="!can_manage" :invalid="Boolean(invite.errors.email)" aria-describedby="invite-email-error" />
                        </Field>
                        <Field id="invite-role" label="Role" :error="invite.errors.role">
                            <Select id="invite-role" v-model="invite.role" :options="roles.map((role) => ({ value: role.value, label: role.label }))" :disabled="!can_manage" />
                        </Field>
                        <FormActions class="sm:col-span-2"><Button :disabled="!can_manage || invite.processing || !invite.email">{{ invite.processing ? 'Inviting…' : 'Send invitation' }}</Button></FormActions>
                    </form>
                </Section>
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
                                <Select v-if="member.manageable && roles.length" :id="`role-${member.id}`" :model-value="member.role" :options="roles" @update:model-value="changeRole(member.update_url, String($event))" />
                                <span v-else class="text-sm">{{ member.role_label }}</span>
                            </span>
                            <span class="w-28 text-sm text-muted-foreground">{{ joined(member.joined_at) }}</span>
                            <span v-if="member.manageable" class="flex gap-2">
                                <Button v-if="confirming !== member.id" variant="secondary" type="button" @click="confirming = member.id">Remove</Button>
                                <template v-else>
                                    <Button variant="destructive" type="button" @click="remove(member.destroy_url)">Confirm remove</Button>
                                    <Button variant="secondary" type="button" @click="confirming = null">Keep</Button>
                                </template>
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
                            <span v-if="can_manage" class="flex gap-2">
                                <Button variant="secondary" type="button" @click="resend(invitation.resend_url)">Resend</Button>
                                <Button variant="destructive" type="button" @click="revoke(invitation.revoke_url)">Revoke</Button>
                            </span>
                        </li>
                    </ul>
                </Section>
            </div>
        </SettingsFrame>
    </Page>
</template>
