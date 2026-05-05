<script setup>
import { computed, reactive, ref, watch } from 'vue'
import AdminButton from '../ui/AdminButton.vue'

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
})

const emit = defineEmits(['open-root', 'open-folder', 'create-folder', 'rename-folder', 'delete-folder'])

const createName = ref('')
const editingId = ref(null)
const renameForm = reactive({
    name: '',
    parent_id: '',
})

const rootOption = computed(() => [{ id: null, name: 'Корень', path: 'media' }, ...props.folderOptions])

watch(() => props.currentFolder?.id, () => {
    createName.value = ''
})

function startRename(folder) {
    editingId.value = folder.id
    renameForm.name = folder.name
    renameForm.parent_id = folder.parent_id ?? ''
}

function cancelRename() {
    editingId.value = null
    renameForm.name = ''
    renameForm.parent_id = ''
}

function submitCreate() {
    const name = createName.value.trim()

    if (name === '') {
        return
    }

    emit('create-folder', name)
}

function submitRename(folder) {
    const name = renameForm.name.trim()

    if (name === '') {
        return
    }

    emit('rename-folder', {
        folder,
        name,
        parent_id: renameForm.parent_id === '' ? null : Number(renameForm.parent_id),
    })
}
</script>

<template>
    <section class="media-folders">
        <div class="media-breadcrumbs media-folders__breadcrumbs">
            <button type="button" class="button-link" @click="emit('open-root')">
                Корень
            </button>

            <template v-for="folder in breadcrumbs" :key="folder.id">
                <span>/</span>
                <button type="button" class="button-link" @click="emit('open-folder', folder)">
                    {{ folder.name }}
                </button>
            </template>
        </div>

        <form class="media-folders__create" @submit.prevent="submitCreate">
            <label class="admin-form-label">
                <span>Новая папка</span>
                <input v-model="createName" class="admin-input" type="text" placeholder="Например, Homepage banners">
                <small v-if="createErrors.name" class="error-text">{{ createErrors.name[0] }}</small>
            </label>

            <AdminButton type="submit" variant="primary" :disabled="busy">
                {{ busy ? 'Сохранение...' : 'Создать папку' }}
            </AdminButton>
        </form>

        <div v-if="folders.length > 0" class="media-folders__list">
            <article v-for="folder in folders" :key="folder.id" class="media-folders__card">
                <template v-if="editingId === folder.id">
                    <form class="media-folders__rename" @submit.prevent="submitRename(folder)">
                        <label class="admin-form-label">
                            <span>Название</span>
                            <input v-model="renameForm.name" class="admin-input" type="text">
                        </label>

                        <label class="admin-form-label">
                            <span>Родитель</span>
                            <select v-model="renameForm.parent_id" class="admin-select">
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
                            <AdminButton type="submit" variant="primary" :disabled="busy">
                                Сохранить
                            </AdminButton>
                            <button type="button" class="button-link" @click="cancelRename">
                                Отмена
                            </button>
                        </div>
                    </form>
                </template>

                <template v-else>
                    <button type="button" class="media-folders__open" @click="emit('open-folder', folder)">
                        <strong>{{ folder.name }}</strong>
                        <span>{{ folder.files_count || 0 }} файлов | {{ folder.children_count || 0 }} вложенных папок</span>
                    </button>

                    <div class="admin-actions-row media-folders__actions">
                        <button type="button" class="button-link" @click="startRename(folder)">
                            Переименовать
                        </button>
                        <button type="button" class="button-link media-folders__danger" @click="emit('delete-folder', folder)">
                            Удалить
                        </button>
                    </div>
                </template>
            </article>
        </div>

        <p v-else class="muted">В текущем разделе пока нет вложенных папок.</p>
    </section>
</template>

<style scoped>
.media-folders {
    display: grid;
    gap: 1rem;
}

.media-folders__create {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.9rem;
    align-items: end;
}

.media-folders__list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
}

.media-folders__card {
    padding: 0.9rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 16px;
    background: rgba(248, 250, 252, 0.78);
}

.media-folders__open {
    display: grid;
    gap: 0.25rem;
    width: 100%;
    text-align: left;
}

.media-folders__open span {
    color: rgba(100, 116, 139, 0.92);
    font-size: 0.88rem;
}

.media-folders__actions {
    margin-top: 0.6rem;
}

.media-folders__rename {
    display: grid;
    gap: 0.8rem;
}

.media-folders__danger {
    color: #b91c1c;
}

@media (max-width: 720px) {
    .media-folders__create {
        grid-template-columns: 1fr;
    }
}
</style>