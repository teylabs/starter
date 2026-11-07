<script setup lang="ts">
import { provideSurface } from './surface';

// Renders its slot on the default surface (default tone and shape). An overlay wraps its portalled content in it, so a dialog or popover opened from a
// pill or inverse container (a FloatingToolbar) gets ordinary buttons, while its trigger keeps the container's look. Renders no element.
provideSurface(() => ({ tone: 'default', shape: 'default' }));
</script>
<template><slot /></template>
