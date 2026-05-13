<script setup>
import { computed, reactive, ref } from "vue";
import AdminButton from "../ui/AdminButton.vue";

const props = defineProps({
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    folders: {
        type: Array,
        default: () => [],
    },
    folderOptions: {
        type: Array,
        default: () => [],
    },
    currentFolder: {
        type: Object,
        default: null,
    },
    createErrors: {
        type: Object,
        default: () => ({}),
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    "open-root",
    "open-folder",
    "rename-folder",
    "delete-folder",
]);

const editingId = ref(null);
const activeMenuId = ref(null);
const renameForm = reactive({
    name: "",
    parent_id: "",
});

const rootOption = computed(() => [
    { id: null, name: "Корень", path: "media" },
    ...props.folderOptions,
]);

function startEdit(folder) {
    editingId.value = folder.id;
    activeMenuId.value = null;
    renameForm.name = folder.name;
    renameForm.parent_id = folder.parent_id ?? "";
}

function cancelRename() {
    editingId.value = null;
    renameForm.name = "";
    renameForm.parent_id = "";
}

function submitRename(folder) {
    const name = renameForm.name.trim();

    if (name === "") {
        return;
    }

    emit("rename-folder", {
        folder,
        name,
        parent_id:
            renameForm.parent_id === "" ? null : Number(renameForm.parent_id),
    });
}

function toggleMenu(folderId) {
    activeMenuId.value = activeMenuId.value === folderId ? null : folderId;
}
</script>

<template>
    <section class="media-folders">
        <div class="media-breadcrumbs media-folders__breadcrumbs">
            <button
                type="button"
                class="button-link"
                @click="emit('open-root')"
            >
                Корень
            </button>

            <template v-for="folder in breadcrumbs" :key="folder.id">
                <span>/</span>
                <button
                    type="button"
                    class="button-link"
                    @click="emit('open-folder', folder)"
                >
                    {{ folder.name }}
                </button>
            </template>
        </div>

        <div v-if="folders.length > 0" class="media-folders__list">
            <article
                v-for="folder in folders"
                :key="folder.id"
                class="media-folders__card"
            >
                <template v-if="editingId === folder.id">
                    <form
                        class="media-folders__rename"
                        @submit.prevent="submitRename(folder)"
                    >
                        <label class="admin-form-label">
                            <span>Название</span>
                            <input
                                v-model="renameForm.name"
                                class="admin-input"
                                type="text"
                            />
                        </label>

                        <label class="admin-form-label">
                            <span>Родитель</span>
                            <select
                                v-model="renameForm.parent_id"
                                class="admin-select"
                            >
                                <option
                                    v-for="option in rootOption"
                                    :key="option.id ?? 'root'"
                                    :value="option.id ?? ''"
                                >
                                    {{ option.path || option.name }}
                                </option>
                            </select>
                        </label>

                        <div class="admin-actions-row">
                            <AdminButton
                                type="submit"
                                variant="primary"
                                :disabled="busy"
                            >
                                Сохранить
                            </AdminButton>
                            <button
                                type="button"
                                class="button-link"
                                @click="cancelRename"
                            >
                                Отмена
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else>
                    <button
                        type="button"
                        class="media-folders__open"
                        @click="emit('open-folder', folder)"
                    >
                        <span class="media-folders__icon" aria-hidden="true">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M2 7c0-1.4 0-2.1.272-2.635a2.5 2.5 0 0 1 1.093-1.093C3.9 3 4.6 3 6 3h1.431c.94 0 1.409 0 1.835.13a3 3 0 0 1 1.033.552c.345.283.605.674 1.126 1.455L12 6h6c1.4 0 2.1 0 2.635.272a2.5 2.5 0 0 1 1.092 1.093C22 7.9 22 8.6 22 10v5c0 1.4 0 2.1-.273 2.635a2.5 2.5 0 0 1-1.092 1.092C20.1 19 19.4 19 18 19H6c-1.4 0-2.1 0-2.635-.273a2.5 2.5 0 0 1-1.093-1.092C2 17.1 2 16.4 2 15V7z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>

                        <span class="media-folders__copy">
                            <strong>{{ folder.name }}</strong>
                            <small>{{ folder.path }}</small>
                            <span
                                >{{ folder.files_count || 0 }} файлов ·
                                {{ folder.children_count || 0 }} вложенных
                                папок</span
                            >
                        </span>
                    </button>

                    <div class="media-folders__menu-wrap">
                        <button
                            type="button"
                            class="media-folders__menu-button"
                            @click.stop="toggleMenu(folder.id)"
                        >
                            ...
                        </button>

                        <div
                            v-if="activeMenuId === folder.id"
                            class="media-folders__menu"
                        >
                            <button
                                type="button"
                                @click="emit('open-folder', folder)"
                            >
                                Открыть
                            </button>
                            <button type="button" @click="startEdit(folder)">
                                Переименовать
                            </button>
                            <button type="button" @click="startEdit(folder)">
                                Переместить
                            </button>
                            <button
                                type="button"
                                class="media-folders__danger"
                                @click="emit('delete-folder', folder)"
                            >
                                Удалить
                            </button>
                        </div>
                    </div>
                </template>
            </article>
        </div>

        <p v-else class="muted">В текущем разделе пока нет вложенных папок.</p>
    </section>
</template>
