<script setup>
import { computed, reactive, ref } from "vue";
import AdminButton from "../ui/AdminButton.vue";
import Icon from "../ui/Icon.vue";
import AdminModal from "../ui/AdminModal.vue";
import AdminSelect from "../ui/AdminSelect.vue";

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

const parentOptions = computed(() => [
    {
        value: null,
        label: "Корень",
    },

    ...props.folderOptions.map((option) => ({
        value: option.id,
        label: option.path || option.name,
    })),
]);

function startEdit(folder) {
    editingId.value = folder.id;
    activeMenuId.value = null;
    renameForm.name = folder.name;
    renameForm.parent_id = folder.parent_id ?? null;
}

function cancelRename() {
    editingId.value = null;
    renameForm.name = "";
    renameForm.parent_id = "";
}

async function submitRename(folder) {
    const name = renameForm.name.trim();

    if (name === "") {
        return;
    }

    await emit("rename-folder", {
        folder,
        name,
        parent_id:
            renameForm.parent_id === null ? null : Number(renameForm.parent_id),
    });

    cancelRename();
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
                class="media-folders__home"
                @click="emit('open-root')"
                title="Корень медиатеки"
            >
                <Icon name="home" width="20" height="20" />
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
                        <label class="admin-form-label name">
                            <span>Название:</span>
                            <input
                                v-model="renameForm.name"
                                class="admin-input"
                                type="text"
                            />
                        </label>

                        <div
                            class="admin-select-custom parent"
                            data-label="Родитель:"
                        >
                            <AdminSelect
                                v-model="renameForm.parent_id"
                                :options="parentOptions"
                            />
                        </div>

                        <div class="admin-actions-row">
                            <AdminButton
                                type="submit"
                                variant="primary"
                                :disabled="busy"
                            >
                                <Icon name="save" width="18" height="18" />
                                Сохранить
                            </AdminButton>
                            <button
                                type="button"
                                class="button-base button-danger"
                                @click="cancelRename"
                            >
                                <Icon name="cancel" width="18" height="18" />
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
                            <Icon name="folder" width="24" height="24" />
                        </span>

                        <span class="media-folders__copy">
                            <strong
                                >{{ folder.name }}
                                <small>{{ folder.path }}</small></strong
                            >

                            <span
                                >{{ folder.files_count || 0 }} файлов
                                <span
                                    >{{ folder.children_count || 0 }} вложенных
                                    папок</span
                                >
                            </span>
                        </span>
                    </button>

                    <div class="media-folders__menu-wrap">
                        <button
                            type="button"
                            class="button-link media-folders__menu-button"
                            @click.stop="toggleMenu(folder.id)"
                        >
                            <Icon name="points" width="22" height="22" />
                        </button>

                        <transition name="fade"
                            ><div
                                v-if="activeMenuId === folder.id"
                                class="media-folders__menu"
                            >
                                <button
                                    type="button"
                                    @click="emit('open-folder', folder)"
                                >
                                    Открыть
                                </button>
                                <button
                                    type="button"
                                    @click="startEdit(folder)"
                                >
                                    Переименовать
                                </button>
                                <button
                                    type="button"
                                    @click="startEdit(folder)"
                                >
                                    Переместить
                                </button>
                                <button
                                    type="button"
                                    class="media-folders__danger"
                                    @click="emit('delete-folder', folder)"
                                >
                                    Удалить
                                </button>
                            </div></transition
                        >
                    </div>
                </template>
            </article>
        </div>

        <p v-else class="text-center">
            В текущем разделе пока нет вложенных папок.
        </p>
    </section>
</template>
