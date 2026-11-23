<script setup lang="ts">
import { Field, Modal, InputSelect } from '@kit/ui';
import { ref } from 'vue';

// Change a member's role, a server-driven modal over People (Concierge decided the roles the viewer may grant).
const props = defineProps<Tey.Kit.Features.SessionUi.ViewModels.ChangeRoleViewModel>();
// InputSelect is controlled: the chosen role lives here, and its hidden input submits it.
const choice = ref(props.role ?? null);
</script>
<template>
    <Modal :title="title" size="sm" :submit="submit" busy-label="Saving…">
        <Field id="change-role" name="role" label="Role"><InputSelect id="change-role" name="role" v-model="choice" :options="roles" /></Field>
    </Modal>
</template>
