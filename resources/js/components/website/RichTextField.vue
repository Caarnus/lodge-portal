<script setup lang="ts">
import { EditorContent, useEditor } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import {
    Bold,
    CodeXml,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Redo2,
    RemoveFormatting,
    Underline,
    Undo2,
} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { nextTick, ref, watch } from "vue";

const vTooltip = Tooltip;
const model = defineModel<string>({ default: "" });
const updatingModel = ref(false);
const editingHtml = ref(false);
const htmlSource = ref("");
const editor = useEditor({
    content: model.value || "<p></p>",
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: { openOnClick: false, defaultProtocol: "https" },
        }),
    ],
    editorProps: {
        attributes: { class: "min-h-56 p-3.5 outline-none" },
    },
    onUpdate: ({ editor: currentEditor }) => {
        updatingModel.value = true;
        model.value = currentEditor.getHTML();
        nextTick(() => {
            updatingModel.value = false;
        });
    },
});

watch(model, (value) => {
    if (
        !updatingModel.value &&
        editor.value &&
        editor.value.getHTML() !== value
    ) {
        editor.value.commands.setContent(value || "<p></p>", {
            emitUpdate: false,
        });
    }
});

const setLink = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes("link").href as
        string | undefined;
    const url = window.prompt("Link URL", previousUrl ?? "https://");
    if (url === null) return;
    if (url.trim() === "") {
        editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
        return;
    }
    editor.value
        .chain()
        .focus()
        .extendMarkRange("link")
        .setLink({ href: url.trim() })
        .run();
};

const openHtmlEditor = () => {
    htmlSource.value = editor.value?.getHTML() ?? model.value;
    editingHtml.value = true;
};

const applyHtml = () => {
    if (!editor.value) return;

    editor.value.commands.setContent(htmlSource.value || "<p></p>", {
        emitUpdate: false,
    });
    model.value = editor.value.getHTML();
    editingHtml.value = false;
};

const buttonClass = (active = false) => [
    "icon-button",
    active ? "bg-muted text-foreground" : "text-muted-foreground",
];
</script>

<template>
    <div
        class="block w-full min-w-0 max-w-none overflow-hidden rounded-md border border-border/80 bg-card"
        style="inline-size: 100%; box-sizing: border-box"
    >
        <div
            v-if="editor && !editingHtml"
            class="flex w-full min-w-0 flex-wrap gap-1 border-b border-border/80 bg-muted p-2"
            role="toolbar"
            aria-label="Rich text formatting"
        >
            <button
                type="button"
                :class="buttonClass(editor.isActive('heading', { level: 2 }))"
                aria-label="Heading"
                v-tooltip.top="{ value: 'Heading', showDelay: 2000 }"
                @mousedown.prevent
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
            >
                <Heading2 class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('heading', { level: 3 }))"
                aria-label="Subheading"
                v-tooltip.top="{ value: 'Subheading', showDelay: 2000 }"
                @mousedown.prevent
                @click="
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                "
            >
                <Heading3 class="size-4" />
            </button>
            <span
                class="mx-1 border-l border-border/80"
                aria-hidden="true"
            ></span>
            <button
                type="button"
                :class="buttonClass(editor.isActive('bold'))"
                aria-label="Bold"
                v-tooltip.top="{ value: 'Bold', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('italic'))"
                aria-label="Italic"
                v-tooltip.top="{ value: 'Italic', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('underline'))"
                aria-label="Underline"
                v-tooltip.top="{ value: 'Underline', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <Underline class="size-4" />
            </button>
            <span
                class="mx-1 border-l border-border/80"
                aria-hidden="true"
            ></span>
            <button
                type="button"
                :class="buttonClass(editor.isActive('orderedList'))"
                aria-label="Numbered list"
                v-tooltip.top="{ value: 'Numbered list', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('bulletList'))"
                aria-label="Bulleted list"
                v-tooltip.top="{ value: 'Bulleted list', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass(editor.isActive('link'))"
                aria-label="Link"
                v-tooltip.top="{ value: 'Link', showDelay: 2000 }"
                @mousedown.prevent
                @click="setLink"
            >
                <Link2 class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass()"
                aria-label="Edit HTML"
                v-tooltip.top="{ value: 'Edit HTML', showDelay: 2000 }"
                @mousedown.prevent
                @click="openHtmlEditor"
            >
                <CodeXml class="size-4" />
            </button>
            <button
                type="button"
                :class="buttonClass()"
                aria-label="Clear formatting"
                v-tooltip.top="{ value: 'Clear formatting', showDelay: 2000 }"
                @mousedown.prevent
                @click="
                    editor.chain().focus().unsetAllMarks().clearNodes().run()
                "
            >
                <RemoveFormatting class="size-4" />
            </button>
            <span
                class="mx-1 border-l border-border/80"
                aria-hidden="true"
            ></span>
            <button
                type="button"
                :disabled="!editor.can().chain().focus().undo().run()"
                :class="buttonClass()"
                aria-label="Undo"
                v-tooltip.top="{ value: 'Undo', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().undo().run()"
            >
                <Undo2 class="size-4" />
            </button>
            <button
                type="button"
                :disabled="!editor.can().chain().focus().redo().run()"
                :class="buttonClass()"
                aria-label="Redo"
                v-tooltip.top="{ value: 'Redo', showDelay: 2000 }"
                @mousedown.prevent
                @click="editor.chain().focus().redo().run()"
            >
                <Redo2 class="size-4" />
            </button>
        </div>
        <textarea
            v-if="editingHtml"
            v-model="htmlSource"
            class="field-input block min-h-56 w-full max-w-none resize-y rounded-none border-0 font-mono text-xs focus-visible:rounded-none"
            style="inline-size: 100%; box-sizing: border-box"
            aria-label="HTML source"
        ></textarea>
        <EditorContent
            v-else
            :editor="editor"
            class="rich-text-content block w-full min-w-0 max-w-none break-words focus-within:shadow-[inset_0_0_0_2px_rgb(15_23_42)]"
        />
        <div
            v-if="editingHtml"
            class="flex justify-end gap-2 border-t border-border/80 bg-muted p-2"
        >
            <button
                type="button"
                class="secondary-button"
                @click="editingHtml = false"
            >
                Cancel
            </button>
            <button type="button" class="primary-button" @click="applyHtml">
                Apply HTML
            </button>
        </div>
    </div>
</template>

<style scoped>
.rich-text-content :deep(h2) {
    margin: 0.75rem 0 0.4rem;
    font-size: 1.5rem;
    font-weight: 700;
}
.rich-text-content :deep(.ProseMirror) {
    display: block;
    inline-size: 100%;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-wrap: anywhere;
}
.rich-text-content :deep(h3) {
    margin: 0.65rem 0 0.35rem;
    font-size: 1.25rem;
    font-weight: 650;
}
.rich-text-content :deep(p) {
    margin: 0.45rem 0;
}
.rich-text-content :deep(ul) {
    margin: 0.5rem 0;
    list-style: disc;
    padding-left: 1.5rem;
}
.rich-text-content :deep(ol) {
    margin: 0.5rem 0;
    list-style: decimal;
    padding-left: 1.5rem;
}
.rich-text-content :deep(a) {
    color: rgb(29 78 216);
    text-decoration: underline;
}
</style>
