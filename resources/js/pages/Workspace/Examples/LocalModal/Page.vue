<script setup lang="ts">
import { Button, Dialog, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, Modal, Page } from '@kit/ui';
import { ref } from 'vue';

// The same <Modal> and <Dialog> as in a server-driven page, used locally: declared in the template and opened with v-model:open. Reached only by the browser test (it registers the route).
const open = ref(false);
const asking = ref(false);
const edited = ref(false);
const picked = ref(0);
</script>
<template>
    <Page md>
        <Button type="button" data-open-local @click="open = true">Open the modal</Button>
        <Button variant="destructive" type="button" data-open-dialog @click="asking = true">Open the dialog</Button>
        <Modal v-model:open="open" title="A local modal" description="Content in a layer, opened with v-model:open." size="md" :dirty="edited">
            <p>Local content.</p>
            <Button variant="secondary" type="button" data-make-dirty @click="edited = true">Edit something</Button>
            <template #footer="{ close }"><Button type="button" data-local-done @click="close()">Close it</Button></template>
        </Modal>
        <DropdownMenu>
            <DropdownMenuTrigger as-child><Button variant="secondary" type="button" data-open-menu>Open the menu</Button></DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem data-enabled-item @select="picked++">Usable</DropdownMenuItem>
                <DropdownMenuItem disabled data-disabled-item @select="picked += 100">Not available</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <p data-picked>{{ picked }}</p>
        <Dialog v-model:open="asking" title="Delete it?" description="This cannot be undone." variant="danger" confirm-label="Delete" />
    </Page>
</template>
