<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import FocusLayout from '@/layouts/FocusLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

// The server picks the shell per route and names it in `shell.kind`; this only renders that one.
defineOptions({ inheritAttrs: false });

const page = usePage<{
    shell?: { kind?: 'app' | 'guest' | 'focus' };
    title?: string;
}>();
// The kit's error page (Workspace/Error) relies on the shell's Page heading for its h1, which a guest page outside the shell does not draw;
// the guest card shows the error's title as the h1 instead, so a guest 404 (an invitation link the route cannot match, say) still names itself.
const guestHeading = computed(() =>
    page.component === 'Workspace/Error/Page' ? (page.props.title ?? '') : '',
);
</script>

<template>
    <FocusLayout v-if="page.props.shell?.kind === 'focus'">
        <slot />
    </FocusLayout>
    <GuestLayout
        v-else-if="page.props.shell?.kind === 'guest'"
        :title="guestHeading"
        :show-heading="guestHeading !== ''"
    >
        <slot />
    </GuestLayout>
    <AppLayout v-else><slot /></AppLayout>
</template>
