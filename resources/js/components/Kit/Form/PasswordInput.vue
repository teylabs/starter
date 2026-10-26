<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { Toggle } from 'reka-ui';
import { ref } from 'vue';
import Input from './Input.vue';

// A password input with a show/hide toggle (Reka's Toggle holds the pressed state); the other attributes pass to the input.
defineOptions({ inheritAttrs: false });
defineProps<{ invalid?: boolean }>();
const model = defineModel<string>();
const shown = ref(false);
</script>
<template>
    <div class="relative">
        <Input v-bind="$attrs" v-model="model" :type="shown ? 'text' : 'password'" :invalid="invalid" class="pr-10" />
        <Toggle v-model="shown" :aria-label="shown ? 'Hide password' : 'Show password'" class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none" :tabindex="-1">
            <EyeOff v-if="shown" class="size-4" aria-hidden="true" />
            <Eye v-else class="size-4" aria-hidden="true" />
        </Toggle>
    </div>
</template>
