<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Heading2, Heading3, Italic, Link2, List, ListOrdered, Redo2, RemoveFormatting, Underline, Undo2 } from 'lucide-vue-next';
import Tooltip from 'primevue/tooltip';
import { watch } from 'vue';

const vTooltip = Tooltip;
const model = defineModel<string>({ default: '' });
const editor = useEditor({
    content: model.value || '<p></p>',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: { openOnClick: false, defaultProtocol: 'https' },
        }),
    ],
    editorProps: {
        attributes: { class: 'min-h-56 p-3.5 outline-none' },
    },
    onUpdate: ({ editor: currentEditor }) => {
        model.value = currentEditor.getHTML();
    },
});

watch(model, (value) => {
    if (editor.value && editor.value.getHTML() !== value) {
        editor.value.commands.setContent(value || '<p></p>', { emitUpdate: false });
    }
});

const setLink = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link URL', previousUrl ?? 'https://');
    if (url === null) return;
    if (url.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
};

const buttonClass = (active = false) => ['icon-button', active ? 'bg-slate-200 text-slate-950' : 'text-slate-600'];
</script>

<template>
    <div class="overflow-hidden rounded-md border border-slate-300 bg-white">
        <div v-if="editor" class="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 p-2" role="toolbar" aria-label="Rich text formatting">
            <button type="button" :class="buttonClass(editor.isActive('heading', { level: 2 }))" aria-label="Heading" v-tooltip.top="{ value: 'Heading', showDelay: 2000 }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"><Heading2 class="size-4" /></button>
            <button type="button" :class="buttonClass(editor.isActive('heading', { level: 3 }))" aria-label="Subheading" v-tooltip.top="{ value: 'Subheading', showDelay: 2000 }" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"><Heading3 class="size-4" /></button>
            <span class="mx-1 border-l border-slate-300" aria-hidden="true"></span>
            <button type="button" :class="buttonClass(editor.isActive('bold'))" aria-label="Bold" v-tooltip.top="{ value: 'Bold', showDelay: 2000 }" @click="editor.chain().focus().toggleBold().run()"><Bold class="size-4" /></button>
            <button type="button" :class="buttonClass(editor.isActive('italic'))" aria-label="Italic" v-tooltip.top="{ value: 'Italic', showDelay: 2000 }" @click="editor.chain().focus().toggleItalic().run()"><Italic class="size-4" /></button>
            <button type="button" :class="buttonClass(editor.isActive('underline'))" aria-label="Underline" v-tooltip.top="{ value: 'Underline', showDelay: 2000 }" @click="editor.chain().focus().toggleUnderline().run()"><Underline class="size-4" /></button>
            <span class="mx-1 border-l border-slate-300" aria-hidden="true"></span>
            <button type="button" :class="buttonClass(editor.isActive('orderedList'))" aria-label="Numbered list" v-tooltip.top="{ value: 'Numbered list', showDelay: 2000 }" @click="editor.chain().focus().toggleOrderedList().run()"><ListOrdered class="size-4" /></button>
            <button type="button" :class="buttonClass(editor.isActive('bulletList'))" aria-label="Bulleted list" v-tooltip.top="{ value: 'Bulleted list', showDelay: 2000 }" @click="editor.chain().focus().toggleBulletList().run()"><List class="size-4" /></button>
            <button type="button" :class="buttonClass(editor.isActive('link'))" aria-label="Link" v-tooltip.top="{ value: 'Link', showDelay: 2000 }" @click="setLink"><Link2 class="size-4" /></button>
            <button type="button" :class="buttonClass()" aria-label="Clear formatting" v-tooltip.top="{ value: 'Clear formatting', showDelay: 2000 }" @click="editor.chain().focus().unsetAllMarks().clearNodes().run()"><RemoveFormatting class="size-4" /></button>
            <span class="mx-1 border-l border-slate-300" aria-hidden="true"></span>
            <button type="button" :disabled="!editor.can().chain().focus().undo().run()" :class="buttonClass()" aria-label="Undo" v-tooltip.top="{ value: 'Undo', showDelay: 2000 }" @click="editor.chain().focus().undo().run()"><Undo2 class="size-4" /></button>
            <button type="button" :disabled="!editor.can().chain().focus().redo().run()" :class="buttonClass()" aria-label="Redo" v-tooltip.top="{ value: 'Redo', showDelay: 2000 }" @click="editor.chain().focus().redo().run()"><Redo2 class="size-4" /></button>
        </div>
        <EditorContent :editor="editor" class="rich-text-content focus-within:shadow-[inset_0_0_0_2px_rgb(15_23_42)]" />
    </div>
</template>

<style scoped>
.rich-text-content :deep(h2) { margin: 0.75rem 0 0.4rem; font-size: 1.5rem; font-weight: 700; }
.rich-text-content :deep(h3) { margin: 0.65rem 0 0.35rem; font-size: 1.25rem; font-weight: 650; }
.rich-text-content :deep(p) { margin: 0.45rem 0; }
.rich-text-content :deep(ul) { margin: 0.5rem 0; list-style: disc; padding-left: 1.5rem; }
.rich-text-content :deep(ol) { margin: 0.5rem 0; list-style: decimal; padding-left: 1.5rem; }
.rich-text-content :deep(a) { color: rgb(29 78 216); text-decoration: underline; }
</style>
