<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Button, Field, Input, Page } from '@kit/ui';
import { ref } from 'vue';

// The second step of signing in: a code from the authenticator app, or a recovery code. A guest card: one full-width button.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChallengeViewModel>();
const recovery = ref(false);
</script>
<template>
    <Head :title="title" />
    <Page xs :heading="false">
        <div class="space-y-2">
            <h1 class="text-xl font-medium">Two-factor verification</h1>
            <p class="text-sm text-muted-foreground">{{ recovery ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.' }}</p>
        </div>
        <Form v-slot="{ processing }" action="/two-factor-challenge" method="post" :reset-on-success="['code', 'recovery_code']" class="mt-6 grid gap-4">
            <Field v-if="!recovery" id="code" name="code" label="Authentication code"><Input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required aria-describedby="code-error" /></Field>
            <Field v-else id="recovery_code" name="recovery_code" label="Recovery code"><Input id="recovery_code" name="recovery_code" autocomplete="one-time-code" required aria-describedby="recovery_code-error" /></Field>
            <Button class="w-full" :disabled="processing" data-test="two-factor-submit">{{ processing ? 'Verifying…' : 'Verify' }}</Button>
            <Button variant="secondary" type="button" class="w-full" @click="recovery = !recovery">{{ recovery ? 'Use an authentication code' : 'Use a recovery code' }}</Button>
        </Form>
    </Page>
</template>
