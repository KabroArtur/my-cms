<script setup>
import AdminButton from './AdminButton.vue'

const props = defineProps({
    editor: {
        type: Object,
        default: null,
    },
    headingLevels: {
        type: Array,
        default: () => [1, 2, 3, 4, 5, 6],
    },
})

function toolbarButtonClass(isActive) {
    return {
        'tiptap-toolbar-btn': true,
        'is-active': isActive,
    }
}

function setParagraph() {
    props.editor?.chain().focus().setParagraph().run()
}

function setHeading(level) {
    if (!Number.isInteger(level) || level < 1 || level > 6) {
        return
    }

    props.editor?.chain().focus().setHeading({ level }).run()
}

function toggleLink() {
    if (!props.editor) {
        return
    }

    const currentHref = props.editor.getAttributes('link').href
    const nextHref = window.prompt('Введите URL ссылки', currentHref || 'https://')

    if (nextHref === null) {
        return
    }

    const normalized = nextHref.trim()

    if (normalized === '') {
        props.editor.chain().focus().unsetLink().run()

        return
    }

    props.editor.chain().focus().setLink({ href: normalized }).run()
}

function toggleTextAlign(align) {
    props.editor?.chain().focus().setTextAlign(align).run()
}

function insertTable() {
    props.editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}
</script>

<template>
    <div class="admin-editor-toolbar" @mousedown.prevent>
        <div class="tiptap-toolbar-group">
            <AdminButton :class="toolbarButtonClass(editor?.isActive('paragraph'))" type="button" @click="setParagraph">Параграф</AdminButton>
            <AdminButton
                v-for="level in headingLevels"
                :key="`h-${level}`"
                :class="toolbarButtonClass(editor?.isActive('heading', { level }))"
                type="button"
                @click="setHeading(level)"
            >
                H{{ level }}
            </AdminButton>
        </div>

        <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

        <div class="tiptap-toolbar-group">
            <AdminButton :class="toolbarButtonClass(editor?.isActive('bold'))" type="button" @click="editor?.chain().focus().toggleBold().run()">Жирный</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('italic'))" type="button" @click="editor?.chain().focus().toggleItalic().run()">Курсив</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('underline'))" type="button" @click="editor?.chain().focus().toggleUnderline().run()">Подчеркн.</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('strike'))" type="button" @click="editor?.chain().focus().toggleStrike().run()">Зачеркн.</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('code'))" type="button" @click="editor?.chain().focus().toggleCode().run()">Код</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('codeBlock'))" type="button" @click="editor?.chain().focus().toggleCodeBlock().run()">Блок кода</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('highlight'))" type="button" @click="editor?.chain().focus().toggleHighlight().run()">Подсветка</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('link'))" type="button" @click="toggleLink">Ссылка</AdminButton>
        </div>

        <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

        <div class="tiptap-toolbar-group">
            <AdminButton :class="toolbarButtonClass(editor?.isActive('bulletList'))" type="button" @click="editor?.chain().focus().toggleBulletList().run()">Список •</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('orderedList'))" type="button" @click="editor?.chain().focus().toggleOrderedList().run()">Список 1.</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('taskList'))" type="button" @click="editor?.chain().focus().toggleTaskList().run()">Чек-лист</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('blockquote'))" type="button" @click="editor?.chain().focus().toggleBlockquote().run()">Цитата</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().setHorizontalRule().run()">Линия</AdminButton>
        </div>

        <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

        <div class="tiptap-toolbar-group">
            <AdminButton :class="toolbarButtonClass(editor?.isActive({ textAlign: 'left' }))" type="button" @click="toggleTextAlign('left')">Слева</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive({ textAlign: 'center' }))" type="button" @click="toggleTextAlign('center')">Центр</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive({ textAlign: 'right' }))" type="button" @click="toggleTextAlign('right')">Справа</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive({ textAlign: 'justify' }))" type="button" @click="toggleTextAlign('justify')">По ширине</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('subscript'))" type="button" @click="editor?.chain().focus().toggleSubscript().run()">x₂</AdminButton>
            <AdminButton :class="toolbarButtonClass(editor?.isActive('superscript'))" type="button" @click="editor?.chain().focus().toggleSuperscript().run()">x²</AdminButton>
        </div>

        <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

        <div class="tiptap-toolbar-group">
            <AdminButton :class="toolbarButtonClass(editor?.isActive('table'))" type="button" @click="insertTable">Таблица</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().addColumnBefore().run()">+Колонка</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().addRowAfter().run()">+Строка</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().deleteColumn().run()">-Колонка</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().deleteRow().run()">-Строка</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().deleteTable().run()">Удалить таблицу</AdminButton>
        </div>

        <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

        <div class="tiptap-toolbar-group">
            <AdminButton type="button" @click="editor?.chain().focus().undo().run()">Назад</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().redo().run()">Вперед</AdminButton>
            <AdminButton type="button" @click="editor?.chain().focus().unsetAllMarks().clearNodes().run()">Сброс</AdminButton>
        </div>
    </div>
</template>