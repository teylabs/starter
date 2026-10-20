<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Field, FormActions, Page, Section, Select, SettingsFrame } from '@kit/ui';
import { computed } from 'vue';

// Delegated MCP access: approve an authorised client token for this workspace with the scopes it may use, and revoke connections you no longer want.
const props = defineProps<Tey.Kit.Features.Mcp.ViewModels.ConnectionsViewModel>();
const form = useForm({ token_id: '', scopes: props.scopes.filter((scope) => scope.required).map((scope) => scope.key), intent_key: props.intent_key });
const options = computed(() => props.tokens.map((token) => ({ value: token.id, label: token.label })));
function toggle(key: string, on: boolean) { form.scopes = on ? [...new Set([...form.scopes, key])] : form.scopes.filter((scope) => scope !== key); }
function submit() { form.post(props.create_url, { preserveScroll: true, onSuccess: () => { form.reset('token_id'); form.intent_key = props.intent_key; } }); }
const revoking = useForm({});
function revoke(url: string) { revoking.delete(url, { preserveScroll: true }); }
</script>
<template>
    <Page lg>
        <SettingsFrame>
            <div class="grid gap-6">
                <Section title="Approve workspace access" description="Approve a client token for this workspace after completing its OAuth consent. A refreshed token needs new workspace approval; revoke the old connection when replacing it.">
                    <p v-if="!tokens.length" class="text-sm text-muted-foreground">No authorised client tokens yet. Connect a client first, then approve it here.</p>
                    <form v-else class="grid gap-6" @submit.prevent="submit">
                        <Field id="token" label="Authorised client token" :error="form.errors.token_id">
                            <Select id="token" v-model="form.token_id" :options="options" placeholder="Choose an authorised token" :invalid="Boolean(form.errors.token_id)" />
                        </Field>
                        <div class="grid gap-3">
                            <template v-for="scope in scopes" :key="scope.key">
                                <p v-if="scope.required" class="text-sm text-muted-foreground">{{ scope.description }} (always included)</p>
                                <Checkbox v-else :id="`scope-${scope.key}`" :model-value="form.scopes.includes(scope.key)" @update:model-value="toggle(scope.key, Boolean($event))">{{ scope.description }}</Checkbox>
                            </template>
                        </div>
                        <p v-if="form.errors.scopes" role="alert" class="text-sm text-destructive">{{ form.errors.scopes }}</p>
                        <FormActions><Button :disabled="form.processing || !form.token_id">{{ form.processing ? 'Approving…' : 'Approve workspace access' }}</Button></FormActions>
                    </form>
                </Section>
                <Section title="Connections">
                    <p v-if="!connections.length" class="text-sm text-muted-foreground">No delegated connections.</p>
                    <ul v-else class="grid gap-3">
                        <li v-for="connection in connections" :key="connection.id" class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span>Connection {{ connection.id }}: <template v-if="connection.revoked">revoked</template><template v-else>expires {{ connection.expires_at }} · <code>{{ connection.endpoint }}</code></template></span>
                            <Button v-if="!connection.revoked" variant="destructive" type="button" :disabled="revoking.processing" @click="revoke(connection.revoke_url)">Revoke connection {{ connection.id }}</Button>
                        </li>
                    </ul>
                </Section>
            </div>
        </SettingsFrame>
    </Page>
</template>
