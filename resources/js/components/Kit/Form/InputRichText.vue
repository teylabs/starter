<script setup lang="ts">
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link,
    List,
    ListOrdered,
    Quote,
    Redo,
    Strikethrough,
    Undo,
} from '@lucide/vue';
import { mergeAttributes, Node } from '@tiptap/core';
import CodeMark from '@tiptap/extension-code';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import {
    Color,
    FontFamily,
    FontSize,
    TextStyle,
} from '@tiptap/extension-text-style';
import { history } from '@tiptap/pm/history';
import StarterKit from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import {
    type Component,
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    onUpdated,
    ref,
    shallowRef,
    useAttrs,
    useId,
    watch,
} from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import Popover from '@/components/Kit/Overlay/Popover.vue';
import { useFieldAria } from './fieldAria';
import { customProperties } from './inputSelect';
import {
    fileIntent,
    fromEditorDocument,
    isImageSource,
    isSafeLink,
    RICH_TEXT_TOOLS,
    splitImageFiles,
    toEditorDocument,
} from './richText';
import type {
    ContentError,
    FileIntent,
    RichDocument,
    RichTextAllow,
    RichTextTool,
} from './richText';

/*
 * A rich-text field on tiptap (InputRichText): `v-model` is the strict RichDocument (richText.ts), validated both ways, so the editor never silently drops what it
 * doesn't support. A stored document outside the subset locks the field and emits `content-error` instead of being rewritten. It sits in a kit Field like any
 * control: give it the Field's `id` and `label` (the textbox's accessible name; the toolbar is "{label} formatting"), and `described-by` for the Field's
 * description or error. `tools` picks and orders the toolbar's controls (all of RICH_TEXT_TOOLS by default); the link control opens a "Link settings" popover
 * (Ctrl/Cmd+K too) that refuses an unsafe link. Pasted or dropped files are never inserted: `file-intent` hands them to the host, which uploads them and adds its
 * own image blocks. `read-only` keeps the content focusable and selectable; `disabled` takes it out of the tab order. `allow` (read when the field mounts) admits
 * more than the strict subset for mirrored content: `textAlign` on paragraphs and headings, and the `textStyle` mark (colour, font family, font size), each value
 * checked strictly and round-tripped; `families` limits a style's font family to the host's own names. It adds no toolbar control. Utilities and tokens only.
 * Images (opt-in): with `allow` naming `image`, an `image-sources` pattern (the host's own references, such as /^media:[0-9a-f-]{36}$/) and an `upload-image`
 * callback, the toolbar's image control picks a file, and pasted or dropped image files are uploaded too. Each becomes an image block at the cursor (or the drop
 * point) with the `src` the callback returns. `image-url` maps a stored `src` to the address the editor shows, and `image-error` reports a failed upload. Any other
 * file is still the host's `file-intent`. The host's server enforces the same `image-sources` rule. Docs: docs/rich-text-images.md.
 */
// Caller attributes (`aria-describedby`, `required`, `data-*` and the rest) land on the editable textbox; its `aria-describedby` joins `described-by` and the
// content error's id, `required` becomes `aria-required`, and `invalid` sets `aria-invalid`. `class`, listeners and the custom properties (`--*`) of a `style` stay on the outer frame.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        modelValue: RichDocument | null;
        label: string;
        id?: string;
        placeholder?: string;
        disabled?: boolean;
        readOnly?: boolean;
        describedBy?: string;
        invalid?: boolean;
        tools?: readonly RichTextTool[];
        privateLinks?: RegExp | null;
        allow?: readonly RichTextAllow[];
        families?: readonly string[] | null;
        imageSources?: RegExp | null;
        uploadImage?:
            | ((
                  file: File,
              ) => Promise<string | { src: string; alt?: string | null }>)
            | null;
        imageUrl?: ((src: string) => string) | null;
    }>(),
    {
        id: undefined,
        placeholder: 'Start typing…',
        disabled: false,
        readOnly: false,
        describedBy: undefined,
        tools: () => RICH_TEXT_TOOLS,
        privateLinks: null,
        allow: () => [],
        families: null,
        imageSources: null,
        uploadImage: null,
        imageUrl: null,
    },
);
const emit = defineEmits<{
    'update:modelValue': [document: RichDocument];
    'file-intent': [intent: FileIntent];
    'content-error': [error: ContentError];
    'image-error': [error: unknown];
    focus: [];
    blur: [];
}>();
const uid = useId();
const controlId = computed(() => props.id ?? `rich-text-${uid}`);
const editor = shallowRef<Editor>();
const contentError = ref('');
const locked = computed(
    () => props.disabled || props.readOnly || Boolean(contentError.value),
);
const linkOpen = ref(false);
const linkValue = ref<string | number>('');
const linkError = ref('');
// Images are on only with all three: the allow flag, the host's source pattern, and its upload callback.
const imagesOn = computed(
    () =>
        props.allow.includes('image') &&
        props.imageSources instanceof RegExp &&
        typeof props.uploadImage === 'function',
);
const uploading = ref(0);
const imagePicker = ref<HTMLInputElement>();
// The stored `src` stays in the document (data-src); the editor shows the host's address for it.
const ImageBlock = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes: () => ({ src: { default: null }, alt: { default: null } }),
    parseHTML: () => [
        {
            tag: 'img[data-src]',
            getAttrs: (element) => ({
                src: (element as HTMLElement).getAttribute('data-src'),
                alt: (element as HTMLElement).getAttribute('alt'),
            }),
        },
    ],
    renderHTML: ({ node }) => [
        'img',
        mergeAttributes({
            'data-src': node.attrs.src,
            src: props.imageUrl
                ? props.imageUrl(String(node.attrs.src))
                : node.attrs.src,
            alt: node.attrs.alt ?? '',
            class: 'my-2 block h-auto max-w-full rounded-md',
            draggable: 'true',
            'data-rich-text-image': '',
        }),
    ],
});
// Whole class strings so Tailwind finds them.
const surface =
    'min-h-32 px-3 py-2 text-sm text-foreground outline-none [&_p]:min-h-5 [&_p+p]:mt-2 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:text-lg [&_h3]:font-semibold [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_code]:rounded-sm [&_code]:bg-muted [&_code]:px-1 [&_code]:font-mono [&_p.is-editor-empty:first-child]:before:pointer-events-none [&_p.is-editor-empty:first-child]:before:float-left [&_p.is-editor-empty:first-child]:before:h-0 [&_p.is-editor-empty:first-child]:before:text-muted-foreground [&_p.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]';

type Tool = {
    label: string;
    icon: Component;
    active?: () => boolean;
    run: () => void;
    can?: () => boolean;
};
const controls = computed<
    Record<Exclude<RichTextTool, 'link' | 'image'>, Tool>
>(() => {
    const chain = () => editor.value!.chain().focus();
    const is = (name: string, attrs?: Record<string, unknown>) => () =>
        editor.value?.isActive(name, attrs) ?? false;
    return {
        heading2: {
            label: 'Heading 2',
            icon: Heading2,
            active: is('heading', { level: 2 }),
            run: () => chain().toggleHeading({ level: 2 }).run(),
        },
        heading3: {
            label: 'Heading 3',
            icon: Heading3,
            active: is('heading', { level: 3 }),
            run: () => chain().toggleHeading({ level: 3 }).run(),
        },
        bold: {
            label: 'Bold',
            icon: Bold,
            active: is('bold'),
            run: () => chain().toggleBold().run(),
        },
        italic: {
            label: 'Italic',
            icon: Italic,
            active: is('italic'),
            run: () => chain().toggleItalic().run(),
        },
        strike: {
            label: 'Strikethrough',
            icon: Strikethrough,
            active: is('strike'),
            run: () => chain().toggleStrike().run(),
        },
        code: {
            label: 'Inline code',
            icon: Code,
            active: is('code'),
            run: () => chain().toggleCode().run(),
        },
        bulletList: {
            label: 'Bullet list',
            icon: List,
            active: is('bulletList'),
            run: () => chain().toggleBulletList().run(),
        },
        orderedList: {
            label: 'Ordered list',
            icon: ListOrdered,
            active: is('orderedList'),
            run: () => chain().toggleOrderedList().run(),
        },
        blockquote: {
            label: 'Blockquote',
            icon: Quote,
            active: is('blockquote'),
            run: () => chain().toggleBlockquote().run(),
        },
        undo: {
            label: 'Undo',
            icon: Undo,
            run: () => chain().undo().run(),
            can: () => editor.value?.can().undo() ?? false,
        },
        redo: {
            label: 'Redo',
            icon: Redo,
            run: () => chain().redo().run(),
            can: () => editor.value?.can().redo() ?? false,
        },
    };
});
// Toolbar state re-reads on every transaction.
const tick = ref(0);

function reportError(error: unknown): void {
    contentError.value =
        error instanceof Error
            ? error.message
            : 'Unsupported rich-text document.';
    emit('content-error', {
        code: 'invalid-document',
        message: contentError.value,
    });
}

const attrs = useAttrs();
const frameAttrs = computed(() =>
    Object.fromEntries(
        Object.entries(attrs).filter(
            ([name]) => name === 'class' || /^on[A-Z]/.test(name),
        ),
    ),
);
function callerAttributes(): Record<string, string> {
    const own: Record<string, string> = {};
    for (const [name, value] of Object.entries(attrs)) {
        if (
            name === 'class' ||
            name === 'style' ||
            /^on[A-Z]/.test(name) ||
            name === 'required' ||
            value === undefined ||
            value === null ||
            value === false
        )
            continue;
        own[name] = value === true ? '' : String(value);
    }
    if (attrs.required !== undefined && attrs.required !== false)
        own['aria-required'] = 'true';
    return own;
}

// Inside a Field: described only by what it renders (plus the caller's ids and `described-by`), and invalid while it has an error.
const fieldAria = useFieldAria(
    {
        get 'aria-describedby'() {
            return [callerAttributes()['aria-describedby'], props.describedBy]
                .filter(Boolean)
                .join(' ');
        },
        get 'aria-invalid'() {
            return callerAttributes()['aria-invalid'];
        },
    },
    () => props.invalid,
);

function attributes(): Record<string, string> {
    const caller = callerAttributes();
    delete caller['aria-describedby'];
    // Only ids of nodes that exist: none, and the attribute is left off.
    const describedBy = [
        ...new Set(
            [
                fieldAria.value['aria-describedby'],
                contentError.value ? `${controlId.value}-error` : '',
            ]
                .flatMap((ids) => (ids ?? '').split(/\s+/))
                .filter(Boolean),
        ),
    ].join(' ');
    return {
        ...caller,
        ...(describedBy ? { 'aria-describedby': describedBy } : {}),
        id: controlId.value,
        role: 'textbox',
        'aria-label': props.label,
        'aria-multiline': 'true',
        'aria-disabled': String(props.disabled),
        'aria-readonly': String(props.readOnly || Boolean(contentError.value)),
        'aria-invalid': String(
            Boolean(contentError.value) ||
                fieldAria.value['aria-invalid'] === 'true',
        ),
        tabindex: props.disabled ? '-1' : '0',
        class: surface,
        'data-rich-text-content': '',
    };
}

function syncState(): void {
    editor.value?.setEditable(!locked.value, false);
    editor.value?.setOptions({
        editorProps: {
            ...editor.value.options.editorProps,
            attributes: attributes(),
        },
    });
    if (locked.value) linkOpen.value = false;
}

function hydrate(): void {
    const current = editor.value;
    if (!current) return;
    try {
        const document = toEditorDocument(
            props.modelValue,
            props.privateLinks,
            props.allow,
            props.families,
            props.imageSources,
        );
        const node = current.schema.nodeFromJSON(document);
        node.check();
        contentError.value = '';
        if (!node.eq(current.state.doc)) {
            current.commands.setContent(document, {
                emitUpdate: false,
                errorOnInvalidContent: true,
            });
            // A document the host replaced starts a new history: undo must not bring back the previous one.
            current.unregisterPlugin('history');
            current.registerPlugin(history());
            linkOpen.value = false;
        }
    } catch (error) {
        reportError(error);
    }
    syncState();
}

function files(
    source: FileIntent['source'],
    list: FileList | undefined,
    position: number,
): boolean {
    const { handled, intent } = fileIntent(
        source,
        list,
        position,
        locked.value,
    );
    if (!intent) return handled;
    const { images, rest } = imagesOn.value
        ? splitImageFiles(intent.files)
        : { images: [], rest: intent.files };
    if (images.length) void insertImages(images, position);
    if (rest.length) emit('file-intent', { ...intent, files: rest });
    return handled;
}

/** Uploads each file through the host's callback and puts its image at `position` (the cursor when null), in order. A failed upload inserts nothing. */
async function insertImages(
    list: readonly File[],
    position: number | null,
): Promise<void> {
    if (!imagesOn.value || locked.value || !props.uploadImage) return;
    // Mapped through every change while the uploads run, so typing meanwhile doesn't misplace the images.
    let at = position;
    for (const file of list) {
        uploading.value++;
        try {
            const result = await props.uploadImage(file);
            const image =
                typeof result === 'string'
                    ? { src: result, alt: null }
                    : { src: result?.src, alt: result?.alt ?? null };
            if (!isImageSource(image.src, props.imageSources))
                throw new Error('The uploaded image has no allowed source.');
            const current = editor.value;
            if (!current || locked.value) return;
            const content = { type: 'image', attrs: image };
            const target =
                at === null
                    ? current.state.selection.to
                    : Math.min(at, current.state.doc.content.size);
            current.chain().focus().insertContentAt(target, content).run();
            at = current.state.selection.to;
        } catch (error) {
            emit('image-error', error);
        } finally {
            uploading.value--;
        }
    }
}
function pickImage(): void {
    if (imagesOn.value && !locked.value) imagePicker.value?.click();
}
function picked(event: Event): void {
    const input = event.target as HTMLInputElement;
    const chosen = Array.from(input.files ?? []);
    input.value = '';
    const { images } = splitImageFiles(chosen);
    if (images.length) void insertImages(images, null);
}

onMounted(() => {
    editor.value = new Editor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                codeBlock: false,
                code: false,
                horizontalRule: false,
                underline: false,
                trailingNode: false,
                link: {
                    openOnClick: false,
                    autolink: false,
                    linkOnPaste: false,
                    isAllowedUri: (href) =>
                        isSafeLink(href, props.privateLinks),
                    HTMLAttributes: {
                        target: null,
                        rel: 'noopener noreferrer',
                    },
                },
            }),
            CodeMark.extend({ excludes: '' }),
            Placeholder.configure({ placeholder: () => props.placeholder }),
            ...(props.allow.includes('textAlign')
                ? [
                      TextAlign.configure({
                          types: ['heading', 'paragraph'],
                          alignments: ['left', 'center', 'right', 'justify'],
                          defaultAlignment: null,
                      }),
                  ]
                : []),
            ...(props.allow.includes('textStyle')
                ? [TextStyle, Color, FontFamily, FontSize]
                : []),
            ...(props.allow.includes('image') ? [ImageBlock] : []),
        ],
        content: { type: 'doc', content: [{ type: 'paragraph' }] },
        editable: false,
        editorProps: {
            attributes: attributes(),
            handlePaste: (view, event) =>
                files(
                    'paste',
                    event.clipboardData?.files,
                    view.state.selection.from,
                ),
            handleDOMEvents: {
                drop: (view, event) => {
                    const handled = files(
                        'drop',
                        event.dataTransfer?.files,
                        view.posAtCoords({
                            left: event.clientX,
                            top: event.clientY,
                        })?.pos ?? view.state.selection.from,
                    );
                    if (handled) event.preventDefault();
                    return handled;
                },
            },
            handleKeyDown: (_view, event) => {
                if (
                    (event.ctrlKey || event.metaKey) &&
                    event.key.toLowerCase() === 'k' &&
                    props.tools.includes('link')
                ) {
                    event.preventDefault();
                    void openLink();
                    return true;
                }
                return false;
            },
        },
        onTransaction: () => {
            tick.value++;
        },
        onUpdate: ({ editor: current }) => {
            if (locked.value) return;
            try {
                emit(
                    'update:modelValue',
                    fromEditorDocument(
                        current.getJSON(),
                        props.privateLinks,
                        props.allow,
                        props.families,
                        props.imageSources,
                    ),
                );
            } catch (error) {
                reportError(error);
                syncState();
            }
        },
        onFocus: () => emit('focus'),
        onBlur: () => emit('blur'),
    });
    hydrate();
});
watch(
    [
        () => props.modelValue,
        () => props.privateLinks,
        () => props.allow,
        () => props.families,
        () => props.imageSources,
    ],
    hydrate,
    { deep: true },
);
watch(
    [
        locked,
        () => props.disabled,
        () => props.readOnly,
        () => props.label,
        controlId,
        fieldAria,
    ],
    syncState,
);
// Attributes aren't reactive: after a render, re-apply them to the editable only when the caller's have changed.
let lastCaller = '';
onUpdated(() => {
    const next = JSON.stringify(callerAttributes());
    if (next === lastCaller) return;
    lastCaller = next;
    syncState();
});
onBeforeUnmount(() => editor.value?.destroy());

async function openLink(): Promise<void> {
    if (locked.value || !editor.value) return;
    linkValue.value = editor.value.getAttributes('link').href ?? '';
    linkError.value = '';
    linkOpen.value = true;
    await nextTick();
    document.getElementById(`${controlId.value}-link`)?.focus();
}
function closeLink(): void {
    linkOpen.value = false;
    editor.value?.view.focus();
}
function applyLink(): void {
    if (locked.value || !editor.value) return;
    const href = String(linkValue.value ?? '').trim();
    if (!isSafeLink(href, props.privateLinks)) {
        linkError.value = 'Enter an http, https, mailto, tel or relative link.';
        return;
    }
    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href })
        .run();
    closeLink();
}
function removeLink(): void {
    if (locked.value) return;
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    closeLink();
}
const pressed = (tool: Tool) =>
    tick.value >= 0 && tool.active ? String(tool.active()) : undefined;
const off = (tool: Tool) =>
    tick.value >= 0 && (locked.value || (tool.can ? !tool.can() : false));
defineExpose({
    focus: () => {
        if (!props.disabled) editor.value?.view.focus();
    },
});
</script>
<template>
    <div
        v-bind="frameAttrs"
        :style="customProperties(attrs.style)"
        :class="[
            'border-input bg-background focus-within:ring-ring/50 min-w-0 rounded-md border shadow-xs focus-within:ring-[3px]',
            disabled && 'opacity-50',
            readOnly && 'bg-muted',
        ]"
        data-input-rich-text
        :data-locked="locked || undefined"
    >
        <div
            v-if="editor && tools.length && !readOnly"
            role="toolbar"
            :aria-label="`${label} formatting`"
            class="border-border flex flex-wrap gap-1 border-b p-1"
            data-rich-text-toolbar
        >
            <template v-for="name in tools" :key="name">
                <Popover
                    v-if="name === 'link'"
                    v-model:open="linkOpen"
                    label="Link settings"
                    width="sm"
                    :close-button="false"
                >
                    <template #trigger>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Edit link"
                            title="Edit link"
                            :aria-pressed="
                                tick >= 0 && editor.isActive('link')
                                    ? 'true'
                                    : 'false'
                            "
                            :disabled="locked"
                            data-rich-text-tool="link"
                            @mousedown.prevent
                            @click.prevent="openLink"
                        >
                            <Link class="size-4" aria-hidden="true" />
                        </Button>
                    </template>
                    <form
                        class="grid gap-3"
                        data-rich-text-link
                        @submit.prevent="applyLink"
                    >
                        <Field
                            :id="`${controlId}-link`"
                            label="Link address"
                            :error="linkError || undefined"
                        >
                            <Input
                                :id="`${controlId}-link`"
                                v-model="linkValue"
                                :invalid="Boolean(linkError)"
                                autocomplete="off"
                                inputmode="url"
                            />
                        </Field>
                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" size="sm">Apply link</Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="removeLink"
                                >Remove link</Button
                            >
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="closeLink"
                                >Cancel</Button
                            >
                        </div>
                    </form>
                </Popover>
                <template v-else-if="name === 'image'">
                    <template v-if="imagesOn">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Insert image"
                            title="Insert image"
                            :disabled="locked"
                            :aria-busy="uploading > 0 ? 'true' : undefined"
                            data-rich-text-tool="image"
                            @mousedown.prevent
                            @click="pickImage"
                        >
                            <ImagePlus class="size-4" aria-hidden="true" />
                        </Button>
                        <input
                            ref="imagePicker"
                            type="file"
                            accept="image/png,image/jpeg,image/gif,image/webp,image/avif"
                            multiple
                            class="sr-only"
                            tabindex="-1"
                            aria-hidden="true"
                            data-rich-text-image-picker
                            @change="picked"
                        />
                    </template>
                </template>
                <Button
                    v-else
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="controls[name].label"
                    :title="controls[name].label"
                    :aria-pressed="pressed(controls[name])"
                    :disabled="off(controls[name])"
                    :data-rich-text-tool="name"
                    @mousedown.prevent
                    @click="controls[name].run()"
                >
                    <component
                        :is="controls[name].icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                </Button>
            </template>
        </div>
        <EditorContent :editor="editor" />
        <p
            v-if="uploading > 0"
            role="status"
            class="border-border text-muted-foreground border-t px-3 py-2 text-sm"
            data-rich-text-uploading
        >
            Uploading image…
        </p>
        <p
            v-if="contentError"
            :id="`${controlId}-error`"
            role="alert"
            class="border-border text-destructive-text border-t px-3 py-2 text-sm"
            data-rich-text-error
        >
            {{ contentError }}
        </p>
    </div>
</template>
