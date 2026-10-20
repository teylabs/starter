<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FocusPage } from '@kit/ui';
// The empty state: what this is, in one line, and the one obvious next step, in a FocusPage as fixed content is. An app replaces this
// page by registering its own dashboard component (a fluid data page goes full width), which receives the same props.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.DashboardViewModel>();
</script>
<template>
    <FocusPage size="sm">
    <section class="grid max-w-xl justify-items-start gap-3 rounded-xl border border-dashed p-8" data-dashboard-empty>
        <h2 class="text-lg font-semibold">Nothing here yet</h2>
        <p class="text-sm text-muted-foreground">This is the home of {{ workspace }}. What your team works on will show up here.</p>
        <Link :href="next_url" class="mt-2 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ next_label }}</Link>
    </section>
    </FocusPage>
</template>
