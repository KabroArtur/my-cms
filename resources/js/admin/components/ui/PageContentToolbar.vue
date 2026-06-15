<script setup>
import AdminButton from "./AdminButton.vue";
import AdminSelect from "./AdminSelect.vue";
import Icon from "./Icon.vue";

const props = defineProps({
    editor: {
        type: Object,
        default: null,
    },
    headingLevels: {
        type: Array,
        default: () => [1, 2, 3, 4, 5, 6],
    },
});

const emit = defineEmits(["open-media"]);

const blockOptions = [
    { value: "p", label: "Параграф" },
    { value: "h1", label: "H1" },
    { value: "h2", label: "H2" },
    { value: "h3", label: "H3" },
    { value: "h4", label: "H4" },
    { value: "h5", label: "H5" },
    { value: "h6", label: "H6" },
];

const alignOptions = [
    { value: "left", icon: "paragraph-left", label: "Слева" },
    { value: "center", icon: "paragraph-center", label: "Центр" },
    { value: "right", icon: "paragraph-right", label: "Справа" },
    { value: "justify", icon: "paragraph-justify", label: "По ширине" },
];

function getActiveBlock() {
    if (!props.editor) return "p";

    for (let i = 1; i <= 6; i++) {
        if (props.editor.isActive("heading", { level: i })) {
            return `h${i}`;
        }
    }

    return "p";
}

function setBlockType(value) {
    if (value === "p") {
        props.editor?.chain().focus().setParagraph().run();
        return;
    }

    props.editor
        ?.chain()
        .focus()
        .setHeading({
            level: Number(value.replace("h", "")),
        })
        .run();
}

function getActiveAlign() {
    if (props.editor?.isActive({ textAlign: "center" })) return "center";
    if (props.editor?.isActive({ textAlign: "right" })) return "right";
    if (props.editor?.isActive({ textAlign: "justify" })) return "justify";

    return "left";
}

function toolbarButtonClass(isActive) {
    return {
        "tiptap-toolbar-btn": true,
        "is-active": isActive,
    };
}

function setParagraph() {
    props.editor?.chain().focus().setParagraph().run();
}

function setHeading(level) {
    if (!Number.isInteger(level) || level < 1 || level > 6) {
        return;
    }

    props.editor?.chain().focus().setHeading({ level }).run();
}

function toggleLink() {
    if (!props.editor) {
        return;
    }

    const currentHref = props.editor.getAttributes("link").href;
    const nextHref = window.prompt(
        "Введите URL ссылки",
        currentHref || "https://",
    );

    if (nextHref === null) {
        return;
    }

    const normalized = nextHref.trim();

    if (normalized === "") {
        props.editor.chain().focus().unsetLink().run();

        return;
    }

    props.editor.chain().focus().setLink({ href: normalized }).run();
}

function toggleTextAlign(align) {
    props.editor?.chain().focus().setTextAlign(align).run();
}

function insertTable() {
    props.editor
        ?.chain()
        .focus()
        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
        .run();
}
</script>

<template>
    <div class="admin-editor-toolbar" @mousedown.prevent>
        <div class="tiptap-toolbar-group">
            <AdminButton
                type="button"
                title="Назад"
                @click="editor?.chain().focus().undo().run()"
            >
                <Icon name="undo" width="16" height="16" />
            </AdminButton>

            <AdminButton
                type="button"
                title="Вперед"
                @click="editor?.chain().focus().redo().run()"
            >
                <Icon name="redo" width="16" height="16" />
            </AdminButton>
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminSelect
                :options="blockOptions"
                :model-value="getActiveBlock()"
                @update:model-value="setBlockType"
                class="title"
            />
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('bold'))"
                type="button"
                title="Жирный"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Icon name="bold" width="15" height="15" />
            </AdminButton>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('italic'))"
                type="button"
                title="Курсив"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Icon name="italic" width="16" height="16" />
            </AdminButton>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('underline'))"
                type="button"
                title="Подчеркивание"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                <Icon name="underline" width="16" height="16" />
            </AdminButton>

            <AdminButton
                class="width"
                :class="toolbarButtonClass(editor?.isActive('strike'))"
                type="button"
                title="Зачеркивание"
                @click="editor?.chain().focus().toggleStrike().run()"
            >
                <Icon name="text-cross" width="30" height="30" />
            </AdminButton>

            <AdminButton
                class="width"
                :class="toolbarButtonClass(editor?.isActive('highlight'))"
                type="button"
                title="Подсветка"
                @click="editor?.chain().focus().toggleHighlight().run()"
            >
                <Icon name="marker" width="18" height="18" />
            </AdminButton>

            <AdminButton
                class="width"
                :class="toolbarButtonClass(editor?.isActive('link'))"
                type="button"
                title="Ссылка"
                @click="toggleLink"
            >
                <Icon name="link" width="24" height="24" />
            </AdminButton>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('subscript'))"
                type="button"
                title="Нижний индекс"
                @click="editor?.chain().focus().toggleSubscript().run()"
            >
                <Icon name="subscript" width="18" height="18" />
            </AdminButton>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('superscript'))"
                type="button"
                title="Верхний индекс"
                @click="editor?.chain().focus().toggleSuperscript().run()"
            >
                <Icon name="superscript" width="18" height="18" />
            </AdminButton>

            <AdminButton
                class="width"
                :class="toolbarButtonClass(editor?.isActive('code'))"
                type="button"
                title="Код"
                @click="editor?.chain().focus().toggleCode().run()"
            >
                <Icon name="embed" width="22" height="22" />
            </AdminButton>

            <AdminButton
                class="width"
                :class="toolbarButtonClass(editor?.isActive('codeBlock'))"
                type="button"
                title="Блок кода"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
            >
                <Icon name="code-block" width="20" height="20" />
            </AdminButton>
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminSelect
                :options="alignOptions"
                :model-value="getActiveAlign()"
                @update:model-value="toggleTextAlign"
                title="Выравнивание и отступы"
                class="paragraph"
            >
                <template #option="{ option }">
                    <span class="align-option">
                        <Icon :name="option.icon" />
                    </span>
                </template>
            </AdminSelect>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('taskList'))"
                type="button"
                title="Чеклист"
                @click="editor?.chain().focus().toggleTaskList().run()"
            >
                <Icon name="list-task" width="18" height="18" />
            </AdminButton>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('bulletList'))"
                type="button"
                title="Маркированный список"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <Icon name="list" width="16" height="16" />
            </AdminButton>

            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('orderedList'))"
                type="button"
                title="Нумерованный список"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <Icon name="list-number" width="16" height="16" />
            </AdminButton>
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminButton
                type="button"
                title="Медиа"
                @click="emit('open-media')"
            >
                <Icon name="image" width="18" height="18" />
            </AdminButton>
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('blockquote'))"
                type="button"
                title="Цитата"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                ❝
            </AdminButton>

            <AdminButton
                type="button"
                title="Линия"
                @click="editor?.chain().focus().setHorizontalRule().run()"
            >
                —
            </AdminButton>
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminButton
                :class="toolbarButtonClass(editor?.isActive('table'))"
                type="button"
                title="Таблица"
                @click="insertTable"
            >
                <Icon name="table" width="18" height="18" />
            </AdminButton>

            <AdminButton
                type="button"
                title="Добавить колонку"
                @click="editor?.chain().focus().addColumnBefore().run()"
            >
                <Icon name="add-column" width="18" height="18" />
            </AdminButton>

            <AdminButton
                type="button"
                title="Добавить строку"
                @click="editor?.chain().focus().addRowAfter().run()"
            >
                <Icon name="add-row" width="18" height="18" />
            </AdminButton>

            <AdminButton
                type="button"
                title="Удалить колонку"
                @click="editor?.chain().focus().deleteColumn().run()"
            >
                <Icon name="subtract-column" width="18" height="18" />
            </AdminButton>

            <AdminButton
                type="button"
                title="Удалить строку"
                @click="editor?.chain().focus().deleteRow().run()"
            >
                <Icon name="subtract-row" width="18" height="18" />
            </AdminButton>

            <AdminButton
                type="button"
                title="Удалить таблицу"
                @click="editor?.chain().focus().deleteTable().run()"
            >
                <Icon name="table-delete" width="17" height="17" />
            </AdminButton>
            <span class="tiptap-toolbar-separator" />
        </div>

        <div class="tiptap-toolbar-group">
            <AdminButton
                type="button"
                title="Сброс"
                @click="
                    editor?.chain().focus().unsetAllMarks().clearNodes().run()
                "
            >
                <Icon name="spinner" width="16" height="16" />
            </AdminButton>
        </div>
    </div>
</template>
