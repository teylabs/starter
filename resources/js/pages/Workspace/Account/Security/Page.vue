<script setup lang="ts">
import { Form, http, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Alert, Button, DateTime, Dialog, Field, FormCard, Input, Page, PasswordInput, ScriptForm, Section, confirmPasskeyPassword, passkeyName, passkeysSupported, registerPasskey } from '@kit/ui';

// Security: passkeys, two-factor authentication (Fortify's actions; the password is asked for again, and a code from the authenticator app turns it on), the devices
// you are signed in on, and your own security log. Turning two-factor off, regenerating the recovery codes and signing out other sessions each ask first.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.SecurityViewModel>();

// Passkeys (`passkeys`, null without Fortify's passkeys feature): add one with the password (confirmed with Fortify first, as its registration requires, then
// Fortify's registration; no password asked when the host turns that confirmation off and `confirm_url` is null), rename one, or remove one with the password.
const canAdd = ref(false);
onMounted(() => { canAdd.value = passkeysSupported(); });
const adding = ref(false);
const addFailed = ref(false);
const addPassword = ref('');
const addError = ref<string | null>(null);
async function addPasskey(): Promise<void> {
    if (!props.passkeys || adding.value) return;
    adding.value = true;
    addFailed.value = false;
    addError.value = null;
    const client = http.getClient() as never;
    try {
        // Fortify's password confirmation first, unless the host turned it off (no confirm_url).
        if (props.passkeys.confirm_url) {
            addError.value = await confirmPasskeyPassword(client, props.passkeys.confirm_url, addPassword.value);
            if (addError.value) return;
        }
        await registerPasskey(client, props.passkeys, passkeyName((navigator as { userAgentData?: { platform?: string } }).userAgentData?.platform));
        addPassword.value = '';
        router.reload({ only: ['passkeys', 'events'] });
    } catch {
        addFailed.value = true;
    } finally {
        adding.value = false;
    }
}
</script>
<template>
    <Page md>
            <Alert v-if="notice" variant="info" live="status" data-account-notice>{{ notice }}</Alert>

            <Section v-if="two_factor.recovery_codes.length" title="Your recovery codes" description="Keep these somewhere safe. Each works once if you lose your authenticator app. They are shown only now.">
                <ul class="grid gap-1 font-mono text-sm sm:grid-cols-2" data-recovery-codes>
                    <li v-for="code in two_factor.recovery_codes" :key="code">{{ code }}</li>
                </ul>
            </Section>

            <Section v-if="two_factor.state === 'unavailable'" title="Two-factor authentication" description="This app's user model does not support two-factor authentication yet." />

            <Form v-else-if="two_factor.state === 'off'" :action="two_factor_url" method="post" :options="{ preserveScroll: true }" reset-on-success>
                <FormCard title="Two-factor authentication" description="Add a second step to signing in: a code from an authenticator app. You confirm it with a code before it is on." submit-label="Turn on" busy-label="Turning on…" :cancel="false" :sticky="false">
                    <Field id="two-factor-password" name="password" label="Your password"><PasswordInput id="two-factor-password" name="password" required autocomplete="current-password" /></Field>
                </FormCard>
            </Form>

            <template v-else-if="two_factor.state === 'pending'">
                <Form :action="two_factor_url" method="put" :options="{ preserveScroll: true }" reset-on-success>
                    <FormCard title="Finish setting up two-factor authentication" description="Scan the QR code with an authenticator app, or enter the key by hand, then type the code the app shows." submit-label="Confirm and turn on" busy-label="Confirming…" :cancel="false" :sticky="false">
                        <div class="col-span-6 grid gap-3">
                            <div v-if="two_factor.qr_svg" class="w-48 rounded-md bg-white p-2" aria-label="QR code for your authenticator app" role="img" data-two-factor-qr v-html="two_factor.qr_svg" />
                            <p class="text-sm text-muted-foreground">Setup key: <code class="font-mono text-foreground" data-two-factor-key>{{ two_factor.secret_key }}</code></p>
                        </div>
                        <Field id="two-factor-code" name="code" label="Code from the app" :span="3"><Input id="two-factor-code" name="code" required inputmode="numeric" autocomplete="one-time-code" /></Field>
                    </FormCard>
                </Form>
                <Form v-slot="{ submit }" :action="two_factor_url" method="delete" :options="{ preserveScroll: true }" reset-on-success>
                    <FormCard title="Cancel setup" description="Turn it off again without finishing." submit-label="Cancel setup" :cancel="false" :sticky="false">
                        <Field id="cancel-password" name="password" label="Your password" :span="3"><PasswordInput id="cancel-password" name="password" required autocomplete="current-password" /></Field>
                        <template #submit>
                            <Dialog variant="danger" title="Cancel two-factor setup?" description="The secret is discarded. You can start again any time." confirm-label="Cancel setup" @confirm="submit()">
                                <Button variant="destructive" type="button">Cancel setup</Button>
                            </Dialog>
                        </template>
                    </FormCard>
                </Form>
            </template>

            <template v-else>
                <Section title="Two-factor authentication is on" :description="`You sign in with a code from your authenticator app. ${two_factor.codes_left} recovery ${two_factor.codes_left === 1 ? 'code' : 'codes'} left.`" />
                <Form v-slot="{ submit }" :action="recovery_codes_url" method="post" :options="{ preserveScroll: true }" reset-on-success>
                    <FormCard title="Recovery codes" description="Make a new set. The old codes stop working." submit-label="Regenerate recovery codes" :cancel="false" :sticky="false">
                        <Field id="codes-password" name="password" label="Your password" :span="3"><PasswordInput id="codes-password" name="password" required autocomplete="current-password" /></Field>
                        <template #submit>
                            <Dialog variant="danger" title="Regenerate recovery codes?" description="Your current recovery codes stop working. The new ones are shown once." confirm-label="Regenerate recovery codes" @confirm="submit()">
                                <Button variant="destructive" type="button">Regenerate recovery codes</Button>
                            </Dialog>
                        </template>
                    </FormCard>
                </Form>
                <Form v-slot="{ submit }" :action="two_factor_url" method="delete" :options="{ preserveScroll: true }" reset-on-success>
                    <FormCard title="Turn off two-factor authentication" description="Signing in goes back to your password alone." submit-label="Turn off" :cancel="false" :sticky="false">
                        <Field id="off-password" name="password" label="Your password" :span="3"><PasswordInput id="off-password" name="password" required autocomplete="current-password" /></Field>
                        <template #submit>
                            <Dialog variant="danger" title="Turn off two-factor authentication?" description="Your account is protected by your password alone until you turn it on again." confirm-label="Turn off" @confirm="submit()">
                                <Button variant="destructive" type="button">Turn off</Button>
                            </Dialog>
                        </template>
                    </FormCard>
                </Form>
            </template>

            <Section v-if="passkeys" title="Passkeys" description="Sign in with your fingerprint, face or device PIN instead of your password." data-passkeys>
                <p v-if="!passkeys.items.length" class="text-sm text-muted-foreground" data-passkeys-empty>No passkeys yet.</p>
                <ul v-else class="grid divide-y divide-border">
                    <li v-for="passkey in passkeys.items" :key="passkey.id" class="grid gap-3 py-4 text-sm" :data-passkey="passkey.id">
                        <span class="flex flex-col"><span class="font-medium">{{ passkey.name }}</span><span class="text-muted-foreground">Added <DateTime :value="passkey.created" /> · <template v-if="passkey.last_used">Last used <DateTime :value="passkey.last_used" /></template><template v-else>Not used yet</template></span></span>
                        <Form v-slot="{ errors, processing }" :action="`${passkeys.url}/${passkey.id}`" method="patch" :options="{ preserveScroll: true }" class="flex flex-wrap items-end gap-2" data-passkey-rename>
                            <Field :id="`passkey-name-${passkey.id}`" name="name" label="Name" class="min-w-48 flex-1"><Input :id="`passkey-name-${passkey.id}`" name="name" :model-value="passkey.name" required maxlength="60" :invalid="Boolean(errors.name)" /></Field>
                            <Button type="submit" variant="secondary" :disabled="processing">Rename</Button>
                        </Form>
                        <Form v-slot="{ submit, processing }" :action="`${passkeys.url}/${passkey.id}`" method="delete" :options="{ preserveScroll: true }" reset-on-success class="flex flex-wrap items-end gap-2" data-passkey-remove>
                            <Field :id="`passkey-password-${passkey.id}`" name="password" label="Your password" class="min-w-48 flex-1"><PasswordInput :id="`passkey-password-${passkey.id}`" name="password" required autocomplete="current-password" /></Field>
                            <Dialog variant="danger" :title="`Remove ${passkey.name}?`" description="You can't sign in with it any more. Your password and other passkeys still work." confirm-label="Remove passkey" @confirm="submit()">
                                <Button variant="destructive" type="button" :disabled="processing">Remove</Button>
                            </Dialog>
                        </Form>
                    </li>
                </ul>
                <div v-if="canAdd" class="grid gap-2 pt-2">
                    <ScriptForm class="flex flex-wrap items-end gap-2" data-passkey-add-form :busy="adding" @submit="addPasskey">
                        <Field v-if="passkeys?.confirm_url" id="passkey-add-password" name="password" label="Your password" class="min-w-48 flex-1" :error="addError ?? undefined"><PasswordInput id="passkey-add-password" v-model="addPassword" name="password" required autocomplete="current-password" /></Field>
                        <Button type="submit" variant="secondary" :disabled="adding" data-passkey-add>{{ adding ? 'Waiting for your device…' : 'Add a passkey' }}</Button>
                    </ScriptForm>
                    <Alert v-if="addFailed" variant="danger" live="alert" data-passkey-add-failed>No passkey was added. Try again, or check that your device supports passkeys.</Alert>
                </div>
            </Section>

            <Section title="Signed-in devices" description="Where you are signed in now.">
                <p v-if="!sessions.supported" class="text-sm text-muted-foreground" data-sessions-unsupported>Signed-in devices can only be listed and ended when sessions are stored in the database. Set SESSION_DRIVER=database to see them here.</p>
                <template v-else>
                    <ul class="grid divide-y divide-border" data-sessions>
                        <li v-for="(session, index) in sessions.items" :key="index" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                            <span class="flex flex-col"><span class="font-medium">{{ session.agent }}<span v-if="session.current" class="font-normal text-muted-foreground"> · this device</span></span><span class="text-muted-foreground">{{ session.ip ?? 'Unknown address' }}</span></span>
                            <span class="text-muted-foreground"><DateTime :value="session.last_active" /></span>
                        </li>
                    </ul>
                    <Form v-slot="{ submit }" :action="sessions_url" method="delete" :options="{ preserveScroll: true }" reset-on-success>
                        <FormCard title="Sign out other sessions" description="Every other device is signed out. This one stays." submit-label="Sign out other sessions" :cancel="false" :sticky="false">
                            <Field id="sessions-password" name="password" label="Your password" :span="3"><PasswordInput id="sessions-password" name="password" required autocomplete="current-password" /></Field>
                            <template #submit>
                                <Dialog variant="danger" title="Sign out your other sessions?" description="Every other device is signed out straight away. This one stays." confirm-label="Sign out other sessions" @confirm="submit()">
                                    <Button variant="destructive" type="button">Sign out other sessions</Button>
                                </Dialog>
                            </template>
                        </FormCard>
                    </Form>
                </template>
            </Section>

            <Section title="Security log" description="Your recent sign-ins and security changes. Only you can see this.">
                <p v-if="!events.length" class="text-sm text-muted-foreground">Nothing yet.</p>
                <ul v-else class="grid divide-y divide-border" data-security-log>
                    <li v-for="(event, index) in events" :key="index" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm" :data-event="event.type">
                        <span class="flex flex-col"><span class="font-medium">{{ event.label }}</span><span class="text-muted-foreground">{{ event.agent }} · {{ event.ip ?? 'Unknown address' }}</span></span>
                        <span class="text-muted-foreground"><DateTime :value="event.at" /></span>
                    </li>
                </ul>
            </Section>
    </Page>
</template>
