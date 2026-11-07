<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Camera, Check, ChevronRight, CircleDashed, Globe } from '@lucide/vue';
import { Button, Page } from '@kit/ui';
import { computed } from 'vue';
import WidgetGrid from './Partials/WidgetGrid.vue';
import type { WidgetFrame } from './Partials/types';
// The workspace home. The greeting is the page heading; below it the welcome card (the one welcome: a greeting for a new member, then the "Finish setting up"
// checklist of skipped steps), then the widget grid (`Registry::widget()`), or the empty state when the viewer has no widgets. An app adds widgets rather than forking
// this page; it can still replace it by registering its own dashboard component, which receives the same props and can render <WidgetGrid> where it likes.
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel>();
// The page heading greets by name; while the welcome card is up it greets the person itself, so the heading drops the name (no double greeting).
const heading = computed(() => (props.welcome ? props.greeting.split(',')[0] : props.greeting));
const icons: Record<string, unknown> = { timezone: Globe, photo: Camera };
const percent = computed(() => (props.welcome && props.welcome.total > 0 ? Math.round((props.welcome.done / props.welcome.total) * 100) : 0));
</script>
<template>
    <Page :title="heading">
    <section v-if="welcome" aria-labelledby="welcome-title" class="mb-8 max-w-xl overflow-hidden rounded-xl border border-l-4 border-l-primary bg-card text-card-foreground shadow-sm" data-welcome-card>
        <div class="grid gap-1 p-5 pb-4 sm:p-6 sm:pb-4">
            <h2 id="welcome-title" class="text-lg font-semibold tracking-tight" data-welcome-title>{{ welcome.title }}</h2>
            <p class="text-sm text-muted-foreground">{{ welcome.body }}</p>
        </div>
        <div v-if="welcome.checklist.length" class="grid gap-3 px-5 pb-5 sm:px-6 sm:pb-6" data-welcome-checklist>
            <div class="grid gap-1.5">
                <p class="text-xs font-medium text-muted-foreground" data-welcome-progress>{{ welcome.done }} of {{ welcome.total }} done</p>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted" role="progressbar" aria-label="Setup progress" aria-valuemin="0" :aria-valuemax="welcome.total" :aria-valuenow="welcome.done">
                    <div class="h-full w-(--kit-meter) rounded-full bg-primary transition-[width] motion-reduce:transition-none" :style="{ '--kit-meter': percent + '%' }" />
                </div>
            </div>
            <ul class="grid gap-2">
                <li v-for="item in welcome.checklist" :key="item.url" :data-state="item.state">
                    <Link :href="item.url" class="flex min-h-14 items-center gap-3 rounded-lg border bg-background px-3 py-2 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full" :class="item.state === 'done' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'" aria-hidden="true">
                            <Check v-if="item.state === 'done'" class="size-4" />
                            <component :is="icons[item.id] ?? CircleDashed" v-else class="size-4" />
                        </span>
                        <span class="grid min-w-0 flex-1">
                            <span class="text-sm font-medium" :class="item.state === 'done' ? 'text-muted-foreground' : ''">{{ item.label }}</span>
                            <span class="truncate text-xs text-muted-foreground">{{ item.hint }}</span>
                        </span>
                        <span v-if="item.state === 'skipped'" class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">Skipped</span>
                        <span v-else-if="item.state === 'done'" class="sr-only">Done</span>
                        <ChevronRight v-if="item.state !== 'done'" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </div>
        <div v-if="welcome.dismiss" class="flex justify-end border-t bg-muted/30 px-5 py-2 sm:px-6">
            <Button type="button" variant="secondary" data-welcome-dismiss @click="router.delete(welcome.dismiss.url, { preserveScroll: true })">{{ welcome.dismiss.label }}</Button>
        </div>
    </section>
    <WidgetGrid v-if="widgets.length" :widgets="widgets as WidgetFrame[]" />
    <section v-else class="grid max-w-xl justify-items-start gap-3 rounded-xl border border-dashed p-8" data-dashboard-empty>
        <h2 class="text-lg font-semibold">Nothing here yet</h2>
        <p class="text-sm text-muted-foreground">This is the home of {{ workspace }}. What your team works on will show up here.</p>
        <Link :href="next_url" class="mt-2 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ next_label }}</Link>
    </section>
    </Page>
</template>
