<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Button, Checkbox, ConfirmDialog, Field, FormCard, Input, Page, Section, Select, SettingsFrame } from '@kit/ui';
import { computed } from 'vue';

// Delegated MCP access: approve an authorised client token for this workspace with the scopes it may use, and revoke connections you no longer want (after a confirmation).
const props = defineProps<Tey.Kit.Features.Mcp.ViewModels.ConnectionsViewModel>();
const options = computed(() => props.tokens.map((token) => ({ value: token.id, label: token.label })));
function revoke(url: string) { router.delete(url, { preserveScroll: true }); }
</script>
<template>
    <Page lg>
        <SettingsFrame>
            <div class="grid gap-6">
                <p v-if="!tokens.length" class="text-sm text-muted-foreground">No authorised client tokens yet. Connect a client first, then approve it here.</p>
                <Form v-else :action="create_url" method="post" :options="{ preserveScroll: true }" reset-on-success>
                    <FormCard title="Approve workspace access" description="Approve a client token for this workspace after completing its OAuth consent. A refreshed token needs new workspace approval; revoke the old connection when replacing it." submit-label="Approve workspace access" busy-label="Approving…" :cancel="false" :sticky="false">
                        <Input type="hidden" name="intent_key" :model-value="intent_key" />
                        <Field id="token" name="token_id" label="Authorised client token" :span="6">
                            <Select id="token" name="token_id" :options="options" placeholder="Choose an authorised token" />
                        </Field>
                        <div class="col-span-6 grid gap-3">
                            <template v-for="scope in scopes" :key="scope.key">
                                <template v-if="scope.required"><Input type="hidden" name="scopes[]" :model-value="scope.key" /><p class="text-sm text-muted-foreground">{{ scope.description }} (always included)</p></template>
                                <Checkbox v-else :id="`scope-${scope.key}`" name="scopes[]" :value="scope.key">{{ scope.description }}</Checkbox>
                            </template>
                        </div>
                    </FormCard>
                </Form>
                <Section title="Connections">
                    <p v-if="!connections.length" class="text-sm text-muted-foreground">No delegated connections.</p>
                    <ul v-else class="grid gap-3">
                        <li v-for="connection in connections" :key="connection.id" class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span>Connection {{ connection.id }}: <template v-if="connection.revoked">revoked</template><template v-else>expires {{ connection.expires_at }} · <code>{{ connection.endpoint }}</code></template></span>
                            <ConfirmDialog v-if="!connection.revoked" title="Revoke this connection?" description="The client loses access to this workspace straight away. It can be approved again later." confirm-label="Revoke connection" @confirm="revoke(connection.revoke_url)">
                                <Button variant="destructive" type="button">Revoke connection {{ connection.id }}</Button>
                            </ConfirmDialog>
                        </li>
                    </ul>
                </Section>
            </div>
        </SettingsFrame>
    </Page>
</template>
