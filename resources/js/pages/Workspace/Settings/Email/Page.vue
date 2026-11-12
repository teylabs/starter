<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Alert, Badge, Button, checkedAtLabel, CopyButton, Field, FormCard, fromLine, fromPreview, fullRecordName, Input, Page, RadioCardGroup, recordKind, useTimezone } from '@kit/ui';

// Email sending: who this workspace's mail is from, which address it goes from and where replies go. Inertia <Form>s only: Save is a PUT visit,
// Check now a POST visit; the server answers with the page again (pure Inertia). A custom domain shows its DNS records and status; until it is
// verified, mail keeps the platform address with this workspace's sender name, so nothing stops sending.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditMailIdentityViewModel>();
defineExpose({ props });

// The page's three choices. A stored `domain` row is the send. subdomain when its domain starts with send., else the domain itself (`root`, posted as such):
// an existing send. setup stays on its choice until someone picks another.
type FromChoice = 'platform' | 'root' | 'domain';
const fromMode = ref<FromChoice>(props.identity.from_mode === 'platform' ? 'platform' : props.identity.domain?.startsWith('send.') === false ? 'root' : 'domain');
const replyToMode = ref(props.identity.reply_to_mode);
const senderInput = ref(props.identity.sender_name ?? '');
const domainInput = ref(props.identity.domain?.replace(/^send\./, '') ?? '');
const localInput = ref(props.identity.from_local);
const replyInput = ref(props.identity.reply_to_address ?? '');
const advancedOpen = ref(fromMode.value === 'domain');
const statusTone = { none: 'neutral', pending: 'warning', verified: 'success', failed: 'danger' } as const;
const statusLabel = { none: 'Not added', pending: 'Waiting for DNS', verified: 'Verified', failed: 'Failed' } as const;

// "Mail will show as": the form's choices as typed.
const rootAddress = computed(() => fromPreview(localInput.value, domainInput.value, true));
const subdomainAddress = computed(() => fromPreview(localInput.value, domainInput.value));
const chosenAddress = computed(() => ({ root: rootAddress.value, domain: subdomainAddress.value, platform: props.identity.platform_address })[fromMode.value]);
const chosenFrom = computed(() => fromLine(senderInput.value.trim() || props.identity.sender_name_default, chosenAddress.value));
const chosenReplies = computed(() => ({ member: 'the member who sent it', fixed: props.identity.fixed_reply_to ?? 'no reply-to address', custom: replyInput.value.trim() || 'the address you enter below' })[replyToMode.value]);
// "Right now": only what the server resolved (effective_from), never the form.
const currentFrom = computed(() => fromLine(props.identity.sender_name ?? props.identity.sender_name_default, props.identity.effective_from));
const waitingFor = computed(() => (props.identity.from_mode === 'domain' && props.identity.domain_status !== 'verified' ? props.identity.domain : null));
const timezone = useTimezone();
const lastChecked = computed(() => (props.identity.checked_at ? checkedAtLabel(props.identity.checked_at, timezone.value) : null));
</script>
<template>
    <Page md>
        <Form :action="save?.url ?? ''" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
            <fieldset :disabled="!save" class="contents">
                <FormCard title="Email sending" description="How mail from this workspace names its sender, which address it comes from, and where replies go." busy-label="Saving…" :submit="save ?? undefined">
                    <Input type="hidden" name="expected_version" :model-value="String(identity.version)" />
                    <div class="grid gap-6">
                        <section class="bg-muted/40 rounded-lg border border-border p-4" aria-label="Mail will show as" data-from-summary>
                            <p class="text-muted-foreground text-xs">Mail will show as</p>
                            <p class="mt-1 font-semibold break-words" data-from-line>{{ chosenFrom }}</p>
                            <p class="text-muted-foreground mt-1 text-sm break-words" data-replies-line>Replies go to {{ chosenReplies }}</p>
                            <p class="text-muted-foreground mt-3 border-t border-border pt-3 text-sm break-words" data-current-sender>
                                <template v-if="waitingFor">Until {{ waitingFor }} is verified, mail goes from </template><template v-else>Right now, mail goes from </template><strong class="text-foreground font-medium">{{ currentFrom }}</strong>
                            </p>
                        </section>
                        <Field id="sender-name" name="sender_name" label="Sender name" :description="`Leave empty to use the workspace's name, ${identity.sender_name_default}.`">
                            <Input id="sender-name" v-model="senderInput" name="sender_name" :placeholder="identity.sender_name_default" maxlength="120" />
                        </Field>
                        <Field id="from-mode" name="from_mode" label="From address">
                            <div class="grid gap-3">
                                <RadioCardGroup
                                    id="from-mode"
                                    v-model="fromMode"
                                    name="from_mode"
                                    :options="[{ value: 'root', label: 'Your own domain (recommended)', description: `Mail goes from ${rootAddress} once its DNS records are verified. Your domain's other mail stays as it is.` }]"
                                />
                                <div v-if="fromMode === 'root'" class="grid gap-6 border-l-2 border-border pl-4" data-domain-fields>
                                    <Field id="domain" name="domain" label="Domain" description="For example example.com.">
                                        <Input id="domain" v-model="domainInput" name="domain" placeholder="example.com" />
                                    </Field>
                                    <Field id="from-local" name="from_local" label="Address name" description="The part before the @.">
                                        <Input id="from-local" v-model="localInput" name="from_local" maxlength="64" />
                                    </Field>
                                </div>
                                <RadioCardGroup
                                    id="from-mode-platform"
                                    v-model="fromMode"
                                    name="from_mode"
                                    label="Platform address"
                                    :options="[{ value: 'platform', label: 'Platform address', description: `${identity.platform_address}. Works right away, with no DNS records.` }]"
                                />
                                <details class="text-sm" :open="advancedOpen" data-from-advanced @toggle="advancedOpen = ($event.target as HTMLDetailsElement).open">
                                    <summary class="text-muted-foreground cursor-pointer">Advanced</summary>
                                    <div class="mt-3 grid gap-3">
                                        <RadioCardGroup
                                            id="from-mode-subdomain"
                                            v-model="fromMode"
                                            name="from_mode"
                                            label="A send. subdomain of your domain"
                                            :options="[{ value: 'domain', label: 'A send. subdomain of your domain', description: `Mail goes from ${subdomainAddress}, kept apart from your domain's other mail.` }]"
                                        />
                                        <div v-if="fromMode === 'domain'" class="grid gap-6 border-l-2 border-border pl-4" data-domain-fields>
                                            <Field id="domain" name="domain" label="Domain" description="For example example.com. Mail goes from its send. subdomain.">
                                                <Input id="domain" v-model="domainInput" name="domain" placeholder="example.com" />
                                            </Field>
                                            <Field id="from-local" name="from_local" label="Address name" description="The part before the @.">
                                                <Input id="from-local" v-model="localInput" name="from_local" maxlength="64" />
                                            </Field>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </Field>
                        <Field id="reply-to-mode" name="reply_to_mode" label="Replies go to">
                            <div class="grid gap-3">
                                <RadioCardGroup
                                    id="reply-to-mode"
                                    v-model="replyToMode"
                                    name="reply_to_mode"
                                    :options="[
                                        { value: 'member', label: 'The member who sent it', description: 'Mail sent by the platform itself replies to the fixed address.' },
                                        { value: 'fixed', label: 'The fixed address', description: identity.fixed_reply_to ?? 'The platform has no reply-to address.' },
                                        { value: 'custom', label: 'Another address' },
                                    ]"
                                />
                                <div v-if="replyToMode === 'custom'" class="border-l-2 border-border pl-4" data-custom-reply>
                                    <Field id="reply-to-address" name="reply_to_address" label="Reply-to address">
                                        <Input id="reply-to-address" v-model="replyInput" type="email" name="reply_to_address" />
                                    </Field>
                                </div>
                            </div>
                        </Field>
                    </div>
                    <template v-if="!save" #actions><p class="text-muted-foreground px-6 pb-6 text-sm" data-readonly>You can view these settings but not change them.</p></template>
                </FormCard>
            </fieldset>
        </Form>

        <section v-if="identity.domain" class="bg-card rounded-xl border border-border p-6" aria-labelledby="domain-heading" data-domain>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="domain-heading" class="text-base font-semibold break-all">{{ identity.domain }}</h2>
                <Badge :tone="statusTone[identity.domain_status]" data-domain-status>{{ statusLabel[identity.domain_status] }}</Badge>
            </div>
            <p v-if="identity.dns_records.length" class="text-muted-foreground mt-2 text-sm">Add these records at your domain's DNS host.</p>
            <ul v-if="identity.dns_records.length" class="mt-4 divide-y divide-border border-y border-border" aria-label="DNS records to add at your domain's DNS host" data-dns-records>
                <li v-for="record in identity.dns_records" :key="record.type + record.name + record.value" class="grid gap-3 py-4" data-dns-record>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium" data-record-kind>
                            {{ recordKind(record) }}<span class="text-muted-foreground font-normal">{{ (recordKind(record) !== record.type ? ` ${record.type}` : '') + (record.priority !== null ? `, priority ${record.priority}` : '') }}</span>
                        </p>
                        <Badge :tone="statusTone[record.status as keyof typeof statusTone] ?? 'neutral'" data-record-status>{{ statusLabel[record.status as keyof typeof statusLabel] ?? record.status }}</Badge>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1 text-sm" data-record-name-row>
                        <span class="text-muted-foreground col-span-2 text-xs">Name</span>
                        <span class="font-mono break-all select-all" :title="fullRecordName(record.name, identity.domain)" data-record-name>{{ record.name }}</span>
                        <CopyButton icon variant="ghost" :value="record.name" :label="`Copy ${recordKind(record)} name`" />
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1 text-sm" data-record-value-row>
                        <span class="text-muted-foreground col-span-2 text-xs">Value</span>
                        <span class="font-mono break-all select-all" data-record-value>{{ record.value }}</span>
                        <CopyButton icon variant="ghost" :value="record.value" :label="`Copy ${recordKind(record)} value`" />
                    </div>
                </li>
            </ul>
            <p v-if="identity.dns_records.length && identity.domain_status !== 'verified'" class="text-muted-foreground mt-3 text-xs">DNS can take a few hours to update after you add the records.</p>
            <Form v-if="check && identity.domain_status !== 'verified'" :action="check.url" method="post" :options="{ preserveScroll: true }" class="mt-4 grid justify-items-start gap-3" #default="{ errors, processing }">
                <Alert v-if="errors.domain" variant="danger" live="alert" data-domain-check-error>{{ errors.domain }}</Alert>
                <Button type="submit" variant="secondary" :disabled="processing">{{ processing ? 'Checking…' : check.label }}</Button>
            </Form>
            <p v-if="lastChecked" class="text-muted-foreground mt-2 text-xs" data-last-checked>Last checked {{ lastChecked }}</p>
        </section>
    </Page>
</template>
