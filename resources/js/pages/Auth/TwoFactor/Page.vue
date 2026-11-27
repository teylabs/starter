<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ActionGroup, Button, Field, Input, Page, Stack } from '@kit/ui';
import { ref } from 'vue';

// The second step of signing in: a code from the authenticator app, or a recovery code. A guest card: its actions stack full-width (ActionGroup's column), Verify first.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChallengeViewModel>();
const recovery = ref(false);
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <Stack class="gap-2">
            <h1 class="font-display text-2xl font-semibold tracking-tight">Two-factor verification</h1>
            <p class="text-sm text-muted-foreground">{{ recovery ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.' }}</p>
        </Stack>
        <Form v-slot="{ processing }" action="/two-factor-challenge" method="post" :reset-on-success="['code', 'recovery_code']" class="grid gap-4">
            <Field v-if="!recovery" id="code" name="code" label="Authentication code"><Input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required /></Field>
            <Field v-else id="recovery_code" name="recovery_code" label="Recovery code"><Input id="recovery_code" name="recovery_code" autocomplete="one-time-code" required /></Field>
            <ActionGroup direction="column">
                <Button type="submit" :disabled="processing" data-test="two-factor-submit">{{ processing ? 'Verifying…' : 'Verify' }}</Button>
                <Button variant="secondary" type="button" @click="recovery = !recovery">{{ recovery ? 'Use an authentication code' : 'Use a recovery code' }}</Button>
            </ActionGroup>
        </Form>
    </Page>
</template>
