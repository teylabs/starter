<script setup lang="ts">
import { Field, Modal, Select } from '@kit/ui';

// Change a member's role, a server-driven modal over People (Concierge decided the roles the viewer may grant).
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChangeRoleViewModel>();
</script>
<template>
    <Modal :title="title" size="sm" :submit="submit" busy-label="Saving…">
        <Field id="change-role" name="role" label="Role"><Select id="change-role" name="role" :model-value="role ?? undefined" :options="roles" /></Field>
    </Modal>
</template>
