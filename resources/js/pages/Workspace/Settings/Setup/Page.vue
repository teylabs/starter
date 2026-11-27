<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge, Button, Page } from '@kit/ui';

// Setup: each registered setup row (Registry::setupRow), grouped as registered, with its state and its fix. Read-only, apart from the test mail, which is
// a POST visit; the server answers with the page again.
const props = defineProps<Tey.Kit.Features.Settings.ViewModels.SetupViewModel>();
defineExpose({ props });
type Row = (typeof props.rows)[number];
const tone = { ok: 'success', info: 'neutral', warning: 'warning', failing: 'danger', missing: 'warning', held: 'warning', overridden: 'neutral', manual: 'neutral', na: 'neutral' } as const;
const label = { ok: 'OK', info: 'Info', warning: 'Warning', failing: 'Failing', missing: 'Missing', held: 'Held back', overridden: 'Set by env', manual: 'By hand', na: 'n/a' } as const;
const groups = computed(() => {
    const out: { name: string; rows: Row[] }[] = [];
    for (const row of props.rows) {
        const last = out[out.length - 1];
        last && last.name === row.group ? last.rows.push(row) : out.push({ name: row.group, rows: [row] });
    }
    return out;
});
</script>
<template>
    <Page md>
        <section class="bg-card rounded-xl border border-border p-6" aria-labelledby="setup-heading" data-setup>
            <h2 id="setup-heading" class="text-base font-semibold">Setup</h2>
            <p class="text-muted-foreground mt-1 text-sm">What is set up, what is missing, and how to fix each.</p>
            <p v-if="!appRows" class="text-muted-foreground mt-1 text-sm" data-app-rows-hidden>The app-wide rows are shown to operators only.</p>
            <div v-for="group in groups" :key="group.name" class="mt-6" :data-setup-group="group.name">
                <h3 class="text-sm font-semibold">{{ group.name }}</h3>
                <ul class="mt-2 divide-y divide-border border-y border-border">
                    <li v-for="row in group.rows" :key="row.key" class="grid gap-1 py-3" :data-setup-row="row.key" :data-state="row.state">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-medium">{{ row.label }}</p>
                            <Badge :tone="tone[row.state]">{{ label[row.state] }}</Badge>
                        </div>
                        <p class="text-muted-foreground text-sm break-words">{{ row.detail }}</p>
                        <p v-if="row.fix" class="text-sm break-words" data-fix>{{ row.fix }}</p>
                        <a v-if="row.link" :href="row.link.url" class="text-sm underline underline-offset-4" data-link>{{ row.link.label }}</a>
                    </li>
                </ul>
                <Form v-if="group.name === 'Mail' && testMail" :action="testMail.url" method="post" :options="{ preserveScroll: true }" class="mt-3" #default="{ processing }">
                    <Button type="submit" variant="secondary" :disabled="processing" data-test-mail>{{ processing ? 'Sending…' : testMail.label }}</Button>
                </Form>
            </div>
        </section>
    </Page>
</template>
