<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { formatCmsDateTime, loadCmsSettings } from '../../composables/useCmsSettings'
import {
    createMediaFolder,
    deleteMediaFile,
    deleteMediaFolder,
    fetchMediaLibrary,
    moveMediaFile,
    updateMediaFile,
    updateMediaFolder,
    uploadMediaFile,
} from '../../api/media'

const loading = ref(true)
const savingFolder = ref(false)
const uploading = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const currentFolder = ref(null)
const breadcrumbs = ref([])
const folders = ref([])
const folderOptions = ref([])
const files = ref([])
const searchQuery = ref('')
const editingFolderId = ref(null)
const expandedFileId = ref(null)
const movingFileId = ref(null)
const savingFolderId = ref(null)

const folderForm = reactive({
    name: '',
})

const folderRenameForm = reactive({
    name: '',
})

const fileEditForm = reactive({
    title: '',
    alt_text: '',
    caption: '',
})

const fileInput = ref(null)

const currentFolderId = computed(() => currentFolder.value?.id ?? null)
const moveFolderOptions = computed(() => [{ id: null, name: 'Корень', path: 'media' }, ...folderOptions.value])
const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase())
const filteredFolders = computed(() => {
    if (normalizedSearch.value === '') {
        return folders.value
    }

    return folders.value.filter((folder) => [folder.name, folder.path]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch.value)))
})
const filteredFiles = computed(() => {
    if (normalizedSearch.value === '') {
        return files.value
    }

    return files.value.filter((file) => [file.original_name, file.filename, file.path, file.mime_type, file.folder_name]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch.value)))
})

function resetFolderForm() {
    folderForm.name = ''
    validationErrors.value = {}
}

async function loadLibrary(folderId = null) {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchMediaLibrary(folderId)
        currentFolder.value = payload.data?.current_folder ?? null
        breadcrumbs.value = payload.data?.breadcrumbs ?? []
        folders.value = payload.data?.folders ?? []
        folderOptions.value = payload.data?.folder_options ?? []
        files.value = payload.data?.files ?? []
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить медиабиблиотеку.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitFolder() {
    savingFolder.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        await createMediaFolder({
            name: folderForm.name,
            parent_id: currentFolderId.value,
        })

        resetFolderForm()
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = error.response?.data?.message ?? 'Не удалось создать папку.'
        }

        console.error(error)
    } finally {
        savingFolder.value = false
    }
}

async function openFolder(folder) {
    editingFolderId.value = null
    expandedFileId.value = null
    await loadLibrary(folder.id)
}

async function openRoot() {
    editingFolderId.value = null
    expandedFileId.value = null
    await loadLibrary(null)
}

async function removeFolder(folder) {
    const confirmed = window.confirm(`Удалить папку "${folder.name}"? Папка должна быть пустой.`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFolder(folder.id)
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось удалить папку.'
        console.error(error)
    }
}

async function updateFolder(folder, event) {
    const formData = new FormData(event.target)
    const nextName = String(formData.get('name') || '').trim()
    const parentValue = String(formData.get('parent_id') || '')
    const nextParentId = parentValue === '' ? null : Number(parentValue)

    savingFolderId.value = folder.id
    errorMessage.value = ''

    try {
        await updateMediaFolder(folder.id, {
            name: nextName,
            parent_id: nextParentId,
        })

        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось обновить папку.'
        console.error(error)
    } finally {
        savingFolderId.value = null
    }
}

function startFolderRename(folder) {
    editingFolderId.value = folder.id
    folderRenameForm.name = folder.name
}

function cancelFolderRename() {
    editingFolderId.value = null
    folderRenameForm.name = ''
}

async function submitFolderRename(folder) {
    savingFolderId.value = folder.id
    errorMessage.value = ''

    try {
        await updateMediaFolder(folder.id, {
            name: folderRenameForm.name,
            parent_id: folder.parent_id,
        })

        cancelFolderRename()
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось переименовать папку.'
        console.error(error)
    } finally {
        savingFolderId.value = null
    }
}

async function handleFileSelect(event) {
    const [file] = event.target.files ?? []

    if (!file) {
        return
    }

    uploading.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        await uploadMediaFile({
            folderId: currentFolderId.value,
            file,
        })

        await loadLibrary(currentFolderId.value)
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = error.response?.data?.message ?? 'Не удалось загрузить файл.'
        }

        console.error(error)
    } finally {
        uploading.value = false

        if (fileInput.value) {
            fileInput.value.value = ''
        }
    }
}

async function removeFile(file) {
    const confirmed = window.confirm(`Удалить файл "${file.original_name}"?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFile(file.id)
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось удалить файл.'
        console.error(error)
    }
}

async function changeFileFolder(file, event) {
    const nextFolderId = event.target.value === '' ? null : Number(event.target.value)

    movingFileId.value = file.id
    errorMessage.value = ''

    try {
        await moveMediaFile(file.id, {
            folder_id: nextFolderId,
        })

        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось переместить файл.'
        console.error(error)
    } finally {
        movingFileId.value = null
    }
}

function toggleFileEditor(file) {
    if (expandedFileId.value === file.id) {
        expandedFileId.value = null
        fileEditForm.title = ''
        fileEditForm.alt_text = ''
        fileEditForm.caption = ''

        return
    }

    expandedFileId.value = file.id
    fileEditForm.title = file.title ?? ''
    fileEditForm.alt_text = file.alt_text ?? ''
    fileEditForm.caption = file.caption ?? ''
}

async function saveFileMetadata(file) {
    errorMessage.value = ''

    try {
        await updateMediaFile(file.id, {
            title: fileEditForm.title,
            alt_text: fileEditForm.alt_text,
            caption: fileEditForm.caption,
        })

        await loadLibrary(currentFolderId.value)
        expandedFileId.value = file.id
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось обновить метаданные файла.'
        console.error(error)
    }
}

function formatDate(value) {
    return value ? formatCmsDateTime(value) : 'Без даты'
}

async function copyUrl(file) {
    try {
        await navigator.clipboard.writeText(file.url)
    } catch (error) {
        errorMessage.value = 'Не удалось скопировать ссылку.'
        console.error(error)
    }
}

onMounted(async () => {
    await loadCmsSettings()
    await loadLibrary()
})
</script>

<template>
    <AdminPage
        eyebrow="Media"
        title="Медиабиблиотека"
        description="Изображения хранятся в папках на public disk, а структура и метаданные сохраняются в базе данных. SVG пока запрещен на уровне загрузки."
    >
        <div class="media-grid">
            <AdminCard>
                <div class="admin-stack">
                    <div>
                        <h2>Текущая папка</h2>
                        <div class="media-breadcrumbs">
                            <button type="button" class="button-link" @click="openRoot">
                                Корень
                            </button>

                            <template v-for="folder in breadcrumbs" :key="folder.id">
                                <span>/</span>
                                <button type="button" class="button-link" @click="openFolder(folder)">
                                    {{ folder.name }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <form class="admin-form-stack" @submit.prevent="submitFolder">
                        <h2>Новая папка</h2>

                        <label class="admin-form-label">
                            <span>Название</span>
                            <input v-model="folderForm.name" class="admin-input" type="text">
                            <small v-if="validationErrors.name" class="error-text">{{ validationErrors.name[0] }}</small>
                        </label>

                        <div class="admin-actions-row">
                            <AdminButton type="submit" variant="primary">
                                {{ savingFolder ? 'Создание...' : 'Создать папку' }}
                            </AdminButton>
                        </div>
                    </form>

                    <div class="admin-form-stack">
                        <h2>Загрузка изображения</h2>
                        <label class="admin-form-label">
                            <span>Файл</span>
                            <input ref="fileInput" class="admin-input" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp" @change="handleFileSelect">
                            <small v-if="validationErrors.file" class="error-text">{{ validationErrors.file[0] }}</small>
                        </label>

                        <p class="muted">Доступны JPG, PNG, GIF, WEBP, AVIF и BMP. SVG отключен специально.</p>

                        <p v-if="uploading" class="muted">Загрузка файла...</p>
                        <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>
                    </div>

                    <label class="admin-form-label">
                        <span>Поиск по текущей папке</span>
                        <input v-model="searchQuery" class="admin-input" type="search" placeholder="Название файла, папки, MIME, путь...">
                    </label>
                </div>
            </AdminCard>

            <AdminCard>
                <div class="media-library-stack">
                    <div>
                        <h2>Папки</h2>
                        <p v-if="loading" class="muted">Загрузка медиабиблиотеки...</p>
                        <p v-else-if="folders.length === 0" class="muted">В этой папке пока нет вложенных папок.</p>
                        <p v-else-if="filteredFolders.length === 0" class="muted">По текущему поиску папки не найдены.</p>
                    </div>

                    <div v-if="filteredFolders.length > 0" class="media-folder-list">
                        <article v-for="folder in filteredFolders" :key="folder.id" class="media-folder-card">
                            <div class="media-folder-card__content">
                                <div class="media-folder-card__top">
                                    <div class="media-folder-card__main">
                                        <form v-if="editingFolderId === folder.id" class="media-folder-card__rename" @submit.prevent="submitFolderRename(folder)">
                                            <input v-model="folderRenameForm.name" class="admin-input" type="text">
                                        </form>

                                        <button v-else type="button" class="media-folder-card__title" @click="openFolder(folder)">
                                            {{ folder.name }}
                                        </button>

                                        <p class="muted">{{ folder.path }}</p>
                                        <p class="muted">Папок: {{ folder.children_count }} | Файлов: {{ folder.files_count }}</p>
                                    </div>

                                    <div class="media-folder-card__actions">
                                        <template v-if="editingFolderId === folder.id">
                                            <button type="button" class="button-link" @click="cancelFolderRename">
                                                Отмена
                                            </button>
                                            <button type="button" class="button-link" @click="submitFolderRename(folder)">
                                                {{ savingFolderId === folder.id ? 'Сохранение...' : 'Сохранить' }}
                                            </button>
                                        </template>

                                        <template v-else>
                                            <button type="button" class="button-link" @click="startFolderRename(folder)">
                                                Переименовать
                                            </button>
                                            <button type="button" class="button-link media-file-card__action--danger" @click="removeFolder(folder)">
                                                Удалить
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div>
                        <h2>Файлы</h2>
                        <p v-if="!loading && files.length === 0" class="muted">В этой папке пока нет файлов.</p>
                        <p v-else-if="files.length > 0 && filteredFiles.length === 0" class="muted">По текущему поиску файлы не найдены.</p>
                        <p v-else-if="filteredFiles.length > 0" class="muted">Нажмите «Редактировать», чтобы открыть быстрые действия по файлу. По клику на папку выше отображаются ее изображения.</p>
                    </div>

                    <div v-if="filteredFiles.length > 0" class="media-file-grid">
                        <article v-for="file in filteredFiles" :key="file.id" class="media-file-card">
                            <div class="media-file-card__preview">
                                <img :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
                            </div>

                            <div class="media-file-card__body">
                                <h3>{{ file.title || file.original_name }}</h3>
                                <p class="muted">{{ file.size_human }}<span v-if="file.width && file.height"> | {{ file.width }} x {{ file.height }}</span></p>

                                <div class="media-file-card__actions">
                                    <button type="button" class="button-link media-file-card__action" @click="toggleFileEditor(file)">
                                        {{ expandedFileId === file.id ? 'Скрыть' : 'Редактировать' }}
                                    </button>

                                    <button type="button" class="button-link media-file-card__action" @click="copyUrl(file)">
                                        Копировать ссылку
                                    </button>

                                    <button type="button" class="button-link media-file-card__action media-file-card__action--danger" @click="removeFile(file)">
                                        Удалить
                                    </button>
                                </div>

                                <div v-if="expandedFileId === file.id" class="media-file-card__editor">
                                    <div class="media-file-card__meta">
                                        <p class="muted">Title: {{ file.title || '—' }}</p>
                                        <p class="muted">Alt: {{ file.alt_text || '—' }}</p>
                                        <p class="muted">Вес: {{ file.size_human }}</p>
                                        <p class="muted">MIME: {{ file.mime_type }}</p>
                                        <p class="muted">Расширение: {{ file.extension || 'без расширения' }}</p>
                                        <p class="muted">Папка: {{ file.folder_name || 'Корень' }}</p>
                                        <p class="muted">Путь: {{ file.path }}</p>
                                        <p v-if="file.width && file.height" class="muted">Размеры: {{ file.width }} x {{ file.height }}</p>
                                        <p class="muted">Загружен: {{ formatDate(file.created_at) }}</p>
                                    </div>

                                    <label class="admin-form-label">
                                        <span>Заголовок</span>
                                        <input v-model="fileEditForm.title" class="admin-input" type="text" placeholder="Например, Hero image">
                                    </label>

                                    <label class="admin-form-label">
                                        <span>Alt текст</span>
                                        <input v-model="fileEditForm.alt_text" class="admin-input" type="text" placeholder="Описание изображения для accessibility">
                                    </label>

                                    <label class="admin-form-label">
                                        <span>Подпись</span>
                                        <textarea v-model="fileEditForm.caption" class="admin-textarea" rows="3" placeholder="Короткая подпись к изображению"></textarea>
                                    </label>

                                    <label class="admin-form-label">
                                        <span>Переместить в папку</span>
                                        <select
                                            class="admin-input"
                                            :value="file.folder_id ?? ''"
                                            :disabled="movingFileId === file.id"
                                            @change="changeFileFolder(file, $event)"
                                        >
                                            <option
                                                v-for="folderOption in moveFolderOptions"
                                                :key="folderOption.id ?? 'root'"
                                                :value="folderOption.id ?? ''"
                                            >
                                                {{ folderOption.path || folderOption.name }}
                                            </option>
                                        </select>
                                    </label>

                                    <div class="admin-actions-row">
                                        <AdminButton type="button" variant="primary" @click="saveFileMetadata(file)">
                                            Сохранить метаданные
                                        </AdminButton>
                                    </div>

                                    <p class="muted">Следующим этапом сюда можно добавить crop, resize и другие операции редактирования.</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </AdminCard>
        </div>
    </AdminPage>
</template>