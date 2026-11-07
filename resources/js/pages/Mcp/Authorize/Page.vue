<script setup lang="ts">
import { Button, Field, InputCheckbox, InputSelect, Page } from '@kit/ui';
import { computed, onMounted, onUnmounted, ref } from 'vue';

// Passport's consent screen for an AI assistant: who is asking, what it asks for in plain words, and the workspace its connection is bound to. Approve and Deny are
// plain form posts, not Inertia visits: each ends in a redirect to the assistant's own address, which an XHR visit cannot follow.
const props = defineProps<Tey.Kit.Features.Mcp.ViewModels.AuthorizeViewModel>();
const workspace = ref<number | null>(props.workspace);
const options = computed(() => props.workspaces.map((choice) => ({ value: choice.id, label: choice.name })));
// Show which choice is on its way, block a second submit, and reset when the browser restores this page from the back-forward cache.
const sent = ref<'approve' | 'deny' | null>(null);
function submit(event: Event, choice: 'approve' | 'deny') {
    if (sent.value !== null) {
        event.preventDefault();
        return;
    }
    sent.value = choice;
}
const restore = (event: PageTransitionEvent) => {
    if (event.persisted) sent.value = null;
};
onMounted(() => window.addEventListener('pageshow', restore));
onUnmounted(() => window.removeEventListener('pageshow', restore));
</script>
<template>
    <Page xs :heading="false">
        <div class="space-y-2">
            <h1 class="text-xl font-medium" data-consent-title>Connect {{ client }}</h1>
            <p class="text-sm text-muted-foreground" data-consent-summary>{{ client }} is asking to work with your account. It can only do what your role allows, and you can revoke it at any time.</p>
            <p v-if="redirect_host" class="text-sm" data-consent-redirect-host>After you approve, you will be sent to <strong>{{ redirect_host }}</strong>.</p>
            <p v-if="registered" class="text-sm text-muted-foreground" data-consent-unverified>Unverified app: "{{ client }}" is the name it gave itself. Check that <strong>{{ redirect_host }}</strong> is where you expect to go; if not, deny.</p>
        </div>
        <ul class="mt-4 grid list-disc gap-1 pl-5 text-sm" data-consent-scopes>
            <li v-for="scope in scopes" :key="scope.key" :data-scope="scope.key">{{ scope.label }}</li>
        </ul>
        <form v-if="workspaces.length" :action="approve_url" method="post" class="mt-6 grid gap-4" data-kit-consent="approve" @submit="submit($event, 'approve')">
            <input type="hidden" name="_token" :value="csrf_token" />
            <input type="hidden" name="auth_token" :value="auth_token" />
            <Field id="consent-workspace" label="Workspace" description="The assistant works in this workspace only.">
                <InputSelect id="consent-workspace" v-model="workspace" name="workspace" :options="options" />
            </Field>
            <fieldset v-if="grants.length" class="grid gap-2" data-consent-grants>
                <legend class="text-sm font-medium">Also allow</legend>
                <p class="text-sm text-muted-foreground">Off unless you tick it. A blanket approval never includes these.</p>
                <InputCheckbox v-for="grant in grants" :id="`grant-${grant.scope}`" :key="grant.scope" name="grants[]" :value="grant.scope" :checked="grant.checked" :data-grant="grant.scope">{{ grant.label }}: {{ grant.explains }}</InputCheckbox>
            </fieldset>
            <Button type="submit" class="w-full" :disabled="sent !== null || workspace === null" :aria-busy="sent === 'approve'" data-test="consent-approve">{{ sent === 'approve' ? 'Connecting…' : 'Approve' }}</Button>
        </form>
        <p v-else class="mt-6 text-sm text-muted-foreground" data-consent-no-workspace>You have no workspace you can connect an assistant to. Ask a workspace owner for access, then try again.</p>
        <form :action="deny_url" method="post" class="mt-3 grid" data-kit-consent="deny" @submit="submit($event, 'deny')">
            <input type="hidden" name="_token" :value="csrf_token" />
            <input type="hidden" name="_method" value="DELETE" />
            <input type="hidden" name="auth_token" :value="auth_token" />
            <Button type="submit" variant="secondary" class="w-full" :disabled="sent !== null" :aria-busy="sent === 'deny'" data-test="consent-deny">{{ sent === 'deny' ? 'Cancelling…' : 'Deny' }}</Button>
        </form>
    </Page>
</template>
