<script setup lang="ts">
import type { ExplanationSource } from './explanation.generated';
import { safeLink } from './working';
defineProps<{ source: ExplanationSource }>();
</script>
<template><span>{{ source.label }}<template v-if="source.kind === 'manual'"> — Manual entry; {{ source.author ?? 'Author unavailable' }}; {{ source.reason }}</template><template v-if="source.kind === 'config'"> — Config version {{ source.version }}</template> — <a v-if="source.link && safeLink(source.link.url)" :href="safeLink(source.link.url)" class="focus-visible:ring-2 focus-visible:ring-ring text-link underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ source.link.label }}</a><span v-else>Source unavailable</span></span></template>
