<script setup>
import { computed, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'
import Placeholder from '@tiptap/extension-placeholder'
import AdminButton from '../ui/AdminButton.vue'

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Введите текст...',
    },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        Link.configure({
            openOnClick: false,
            autolink: true,
            protocols: ['http', 'https', 'mailto', 'tel'],
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    content: props.modelValue || '',
    onUpdate({ editor: tiptap }) {
        emit('update:modelValue', tiptap.getHTML())
    },
})

watch(() => props.modelValue, (value) => {
    const next = value || ''

    if (!editor.value || editor.value.getHTML() === next) {
        return
    }

    editor.value.commands.setContent(next, false)
})

const toolbar = computed(() => ({
    bold: editor.value?.isActive('bold') ?? false,
    italic: editor.value?.isActive('italic') ?? false,
    underline: editor.value?.isActive('underline') ?? false,
    bulletList: editor.value?.isActive('bulletList') ?? false,
    orderedList: editor.value?.isActive('orderedList') ?? false,
    blockquote: editor.value?.isActive('blockquote') ?? false,
    link: editor.value?.isActive('link') ?? false,
}))

function buttonClass(active) {
    return {
        'tiptap-toolbar-btn': true,
        'is-active': active,
    }
}

function editLink() {
    if (!editor.value) {
        return
    }

    const currentHref = editor.value.getAttributes('link').href
    const nextHref = window.prompt('Введите URL ссылки', currentHref || 'https://')

    if (nextHref === null) {
        return
    }

    const normalized = nextHref.trim()

    if (normalized === '') {
        editor.value.chain().focus().unsetLink().run()
        return
    }

    editor.value.chain().focus().setLink({ href: normalized }).run()
}
</script>

<template>
    <div class="custom-rich-text-field">
        <div class="admin-editor-toolbar compact">
            <div class="tiptap-toolbar-group">
                <AdminButton :class="buttonClass(toolbar.bold)" type="button" @click="editor?.chain().focus().toggleBold().run()">B</AdminButton>
                <AdminButton :class="buttonClass(toolbar.italic)" type="button" @click="editor?.chain().focus().toggleItalic().run()">I</AdminButton>
                <AdminButton :class="buttonClass(toolbar.underline)" type="button" @click="editor?.chain().focus().toggleUnderline().run()">U</AdminButton>
                <AdminButton :class="buttonClass(toolbar.link)" type="button" @click="editLink">Link</AdminButton>
            </div>

            <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

            <div class="tiptap-toolbar-group">
                <AdminButton :class="buttonClass(toolbar.bulletList)" type="button" @click="editor?.chain().focus().toggleBulletList().run()">• List</AdminButton>
                <AdminButton :class="buttonClass(toolbar.orderedList)" type="button" @click="editor?.chain().focus().toggleOrderedList().run()">1. List</AdminButton>
                <AdminButton :class="buttonClass(toolbar.blockquote)" type="button" @click="editor?.chain().focus().toggleBlockquote().run()">Quote</AdminButton>
            </div>
        </div>

        <EditorContent :editor="editor" class="admin-editor tiptap-editor compact" />
    </div>
</template>

<style scoped>
.custom-rich-text-field {
    display: grid;
    gap: 0.75rem;
}

.compact {
    padding: 0.75rem;
}
</style>