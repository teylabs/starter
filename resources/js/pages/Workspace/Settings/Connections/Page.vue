<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Stack, Button, DataTable, Field, FormCard, Input, InputCheckbox, Page, Section, InputSelect } from '@kit/ui';
import { computed, ref, watch } from 'vue';

// AI assistants: connect assistants like Claude or ChatGPT to this workspace over MCP. The server sends the address and the "add an assistant" cards (a one-click link, a
// command, or steps); an assistant that has signed in shows up under "Waiting for your approval" until you approve it, and the connected ones are a table with Revoke.
const props = defineProps<Tey.Kit.Features.Mcp.ViewModels.ConnectionsViewModel>();
const options = computed(() => props.pending.map((token) => ({ value: token.id, label: token.label })));
// InputSelect is controlled: the chosen assistant lives here, and its hidden input submits it.
const chosenToken = ref<string | null>(null);
// An approval refreshes `pending` without a remount: a choice that left the list is dropped, so the next approval can't resubmit it.
watch(options, (list) => {
    if (chosenToken.value !== null && !list.some((option) => option.value === chosenToken.value)) chosenToken.value = null;
});
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
</script>
<template>
    <Page md>
        <Form v-if="approve" :action="approve.url" method="post" :options="{ preserveScroll: true }" reset-on-success>
            <FormCard title="Waiting for your approval" description="An agent signed in and is asking to use this workspace. Choose it and what it may do." :submit="approve" busy-label="Approving…" :cancel="false" :sticky="false">
                <Input type="hidden" name="intent_key" :model-value="intent_key" />
                <Field id="token" name="token_id" label="Agent" :span="6">
                    <InputSelect id="token" v-model="chosenToken" name="token_id" :options="options" placeholder="Choose an agent" />
                </Field>
                <div class="col-span-6 grid gap-3">
                    <template v-for="scope in scopes" :key="scope.key">
                        <template v-if="scope.required"><Input type="hidden" name="scopes[]" :model-value="scope.key" /><p class="text-sm text-muted-foreground">{{ scope.description }} (always included)</p></template>
                        <InputCheckbox v-else :id="`scope-${scope.key}`" name="scopes[]" :value="scope.key">{{ scope.description }}</InputCheckbox>
                    </template>
                </div>
            </FormCard>
        </Form>
        <DataTable v-if="table.rows.length" :table="table as never" :toolbar="false" @act="() => undefined" />
        <Section :title="empty ? 'Connect an agent' : 'Add an agent'" :description="empty ? 'Let agents like Claude or ChatGPT work with this workspace over MCP. They only see what you approve, and you can revoke access at any time.' : undefined">
            <Stack>
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
            </Stack>
        </Section>
    </Page>
</template>
