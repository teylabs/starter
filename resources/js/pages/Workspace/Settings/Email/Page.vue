<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge, Button, Field, FormCard, Input, Page, RadioCardGroup } from '@kit/ui';

// Email sending: who this workspace's mail is from, which address it goes from and where replies go. Inertia <Form>s only: Save is a PUT visit,
// Check now a POST visit; the server answers with the page again (pure Inertia). A custom domain shows its DNS records and status; until it is
// verified, mail keeps the platform address with this workspace's sender name, so nothing stops sending.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.EditMailIdentityViewModel>();
defineExpose({ props });

const fromMode = ref(props.identity.from_mode);
const replyToMode = ref(props.identity.reply_to_mode);
const statusTone = { none: 'neutral', pending: 'warning', verified: 'success', failed: 'danger' } as const;
const statusLabel = { none: 'Not added', pending: 'Waiting for DNS', verified: 'Verified', failed: 'Failed' } as const;
</script>
<template>
    <Page md>
        <Form :action="save?.url ?? ''" method="put" :options="{ preserveScroll: true }" set-defaults-on-success>
            <fieldset :disabled="!save" class="contents">
                <FormCard title="Email sending" description="How mail from this workspace names its sender, which address it comes from, and where replies go." busy-label="Saving…" :submit="save ?? undefined">
                    <Input type="hidden" name="expected_version" :model-value="String(identity.version)" />
                    <Field id="sender-name" name="sender_name" label="Sender name" :description="`Leave empty to use the workspace's name, ${identity.sender_name_default}.`" :span="4">
                        <Input id="sender-name" name="sender_name" :model-value="identity.sender_name ?? ''" :placeholder="identity.sender_name_default" maxlength="120" />
                    </Field>
                    <Field id="from-mode" name="from_mode" label="From address" :span="4">
                        <RadioCardGroup
                            id="from-mode"
                            v-model="fromMode"
                            name="from_mode"
                            :options="[
                                { value: 'platform', label: 'Platform address', description: identity.platform_address },
                                { value: 'domain', label: 'Your own domain', description: 'Mail comes from an address on a send. subdomain of your domain, once its DNS records are verified.' },
                            ]"
                        />
                    </Field>
                    <template v-if="fromMode === 'domain'">
                        <Field id="domain" name="domain" label="Your domain" description="For example example.com. Mail goes from the send. subdomain, so your existing mail setup is untouched." :span="2">
                            <Input id="domain" name="domain" :model-value="identity.domain?.replace(/^send\./, '') ?? ''" placeholder="example.com" />
                        </Field>
                        <Field id="from-local" name="from_local" label="Address name" description="The part before the @." :span="2">
                            <Input id="from-local" name="from_local" :model-value="identity.from_local" maxlength="64" />
                        </Field>
                    </template>
                    <Field id="reply-to-mode" name="reply_to_mode" label="Replies go to" :span="4">
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
                    </Field>
                    <Field v-if="replyToMode === 'custom'" id="reply-to-address" name="reply_to_address" label="Reply-to address" :span="4">
                        <Input id="reply-to-address" type="email" name="reply_to_address" :model-value="identity.reply_to_address ?? ''" />
                    </Field>
                    <template v-if="!save" #actions><p class="px-6 pb-6 text-sm text-muted-foreground" data-readonly>You can view these settings but not change them.</p></template>
                </FormCard>
            </fieldset>
        </Form>

        <section v-if="identity.domain" class="mt-6 rounded-xl border border-border bg-card p-6" aria-labelledby="domain-heading" data-domain>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="domain-heading" class="text-base font-semibold">{{ identity.domain }}</h2>
                <Badge :tone="statusTone[identity.domain_status]" data-domain-status>{{ statusLabel[identity.domain_status] }}</Badge>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
                Mail goes from <strong class="text-foreground">{{ identity.effective_from }}</strong>.
                <template v-if="identity.domain_status !== 'verified'"> Until the domain is verified, mail keeps the platform address with your sender name.</template>
            </p>
            <div v-if="identity.dns_records.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">DNS records to add at your domain's DNS host</caption>
                    <thead class="text-muted-foreground"><tr><th class="py-2 pr-4 font-medium">Type</th><th class="py-2 pr-4 font-medium">Name</th><th class="py-2 pr-4 font-medium">Value</th><th class="py-2 font-medium">Status</th></tr></thead>
                    <tbody>
                        <tr v-for="record in identity.dns_records" :key="record.type + record.name + record.value" class="border-t border-border align-top">
                            <td class="py-2 pr-4 font-mono">{{ record.type }}<span v-if="record.priority !== null"> {{ record.priority }}</span></td>
                            <td class="py-2 pr-4 font-mono break-all select-all">{{ record.name }}</td>
                            <td class="py-2 pr-4 font-mono break-all select-all">{{ record.value }}</td>
                            <td class="py-2"><Badge :tone="statusTone[record.status as keyof typeof statusTone] ?? 'neutral'">{{ statusLabel[record.status as keyof typeof statusLabel] ?? record.status }}</Badge></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Form v-if="check && identity.domain_status !== 'verified'" :action="check.url" method="post" :options="{ preserveScroll: true }" class="mt-4" #default="{ processing }">
                <Button type="submit" variant="secondary" :disabled="processing">{{ processing ? 'Checking…' : check.label }}</Button>
            </Form>
            <p v-if="identity.checked_at" class="mt-2 text-xs text-muted-foreground">Last checked {{ identity.checked_at }}.</p>
        </section>
    </Page>
</template>
