<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { BlockStack, Button, Checkbox, DataTable, Field, FormCard, Input, Page, Section, Select } from '@kit/ui';
import { computed, ref } from 'vue';

// AI assistants: connect assistants like Claude or ChatGPT to this workspace over MCP. The server sends the address and the "add an assistant" cards (a one-click link, a
// command, or steps); an assistant that has signed in shows up under "Waiting for your approval" until you approve it, and the connected ones are a table with Revoke.
const props = defineProps<Tey.Kit.Features.Mcp.ViewModels.ConnectionsViewModel>();
const options = computed(() => props.pending.map((token) => ({ value: token.id, label: token.label })));
const copied = ref<string | null>(null);
async function copy(text: string, key: string) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        copied.value = null;
    }
}
const empty = computed(() => !props.approve && props.table.rows.length === 0);
const date = (iso: unknown) => (typeof iso === 'string' && iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '');
</script>
<template>
    <Page md>
        <BlockStack class="gap-6">
            <Form v-if="approve" :action="approve.url" method="post" :options="{ preserveScroll: true }" reset-on-success>
                <FormCard title="Waiting for your approval" description="An assistant signed in and is asking to use this workspace. Choose it and what it may do." :submit="approve" busy-label="Approving…" :cancel="false" :sticky="false">
                    <Input type="hidden" name="intent_key" :model-value="intent_key" />
                    <Field id="token" name="token_id" label="Assistant" :span="6">
                        <Select id="token" name="token_id" :options="options" placeholder="Choose an assistant" />
                    </Field>
                    <div class="col-span-6 grid gap-3">
                        <template v-for="scope in scopes" :key="scope.key">
                            <template v-if="scope.required"><Input type="hidden" name="scopes[]" :model-value="scope.key" /><p class="text-sm text-muted-foreground">{{ scope.description }} (always included)</p></template>
                            <Checkbox v-else :id="`scope-${scope.key}`" name="scopes[]" :value="scope.key">{{ scope.description }}</Checkbox>
                        </template>
                    </div>
                </FormCard>
            </Form>
            <DataTable v-if="table.rows.length" :table="table as never" :toolbar="false" @act="() => undefined" />
            <Section :title="empty ? 'Connect an AI assistant' : 'Add an assistant'" :description="empty ? 'Let assistants like Claude or ChatGPT work with this workspace over MCP. They only see what you approve, and you can revoke access at any time.' : undefined">
                <BlockStack>
                    <div class="flex flex-wrap items-center gap-2" data-mcp-address>
                        <code class="min-w-0 flex-1 rounded-md bg-muted px-3 py-2 text-sm break-all">{{ server_url }}</code>
                        <Button variant="secondary" type="button" data-copy="address" @click="copy(server_url, 'address')">{{ copied === 'address' ? 'Copied' : 'Copy address' }}</Button>
                    </div>
                    <ul class="grid gap-4 sm:grid-cols-2" data-assistant-cards>
                        <li v-for="client in assistants" :key="client.key" class="grid content-start gap-3 rounded-lg border border-border p-4" :data-assistant="client.key">
                            <div>
                                <h3 class="text-sm font-semibold">{{ client.label }}</h3>
                                <p class="text-sm text-muted-foreground">{{ client.summary }}</p>
                            </div>
                            <a v-if="client.install" :href="client.install.url" class="inline-flex h-9 items-center justify-center self-start rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-install>{{ client.install.label }}</a>
                            <div v-if="client.command" class="grid gap-2">
                                <code class="rounded-md bg-muted px-3 py-2 text-xs break-all">{{ client.command }}</code>
                                <Button variant="secondary" type="button" class="self-start" :data-copy="client.key" @click="copy(client.command, client.key)">{{ copied === client.key ? 'Copied' : 'Copy command' }}</Button>
                            </div>
                            <ol class="grid list-decimal gap-1 pl-5 text-sm text-muted-foreground">
                                <li v-for="step in client.steps" :key="step">{{ step }}</li>
                            </ol>
                        </li>
                    </ul>
                </BlockStack>
            </Section>
        </BlockStack>
    </Page>
</template>
