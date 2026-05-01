<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
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

const uploadAccept = 'image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp'
const allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp']
const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp']

const loading = ref(true)
const savingFolder = ref(false)
const uploading = ref(false)
const creatingUploadFolder = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const uploadFolderErrors = ref({})
const currentFolder = ref(null)
const breadcrumbs = ref([])
const folders = ref([])
const folderOptions = ref([])
const files = ref([])
const searchQuery = ref('')
const editingFolderId = ref(null)
const movingFileId = ref(null)
const savingFolderId = ref(null)
const selectedFileId = ref(null)
const activeFileMenuId = ref(null)
const filePanelSaving = ref(false)
const noticeMessage = ref('')
const uploadModalOpen = ref(false)
const uploadQueue = ref([])
const dragDepth = ref(0)

const folderForm = reactive({
    name: '',
})

const folderRenameForm = reactive({
    name: '',
})

const selectedFileForm = reactive({
    original_name: '',
    title: '',
    alt_text: '',
    caption: '',
})

const uploadFolderForm = reactive({
    name: '',
})

const fileInput = ref(null)

const currentFolderId = computed(() => currentFolder.value?.id ?? null)
const moveFolderOptions = computed(() => [{ id: null, name: 'Корень', path: 'media' }, ...folderOptions.value])
const selectedFile = computed(() => files.value.find((file) => file.id === selectedFileId.value) ?? null)
const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase())
const dragOverlayVisible = computed(() => dragDepth.value > 0)
const uploadableItems = computed(() => uploadQueue.value.filter((item) => item.file && item.retryable !== false && item.status !== 'success'))
const uploadProgress = computed(() => {
    const items = uploadQueue.value.filter((item) => item.file)

    if (items.length === 0) {
        return 0
    }

    const total = items.reduce((sum, item) => sum + item.progress, 0)

    return Math.round(total / items.length)
})
const uploadStats = computed(() => ({
    total: uploadQueue.value.length,
    queued: uploadQueue.value.filter((item) => item.status === 'queued').length,
    uploading: uploadQueue.value.filter((item) => item.status === 'uploading').length,
    success: uploadQueue.value.filter((item) => item.status === 'success').length,
    error: uploadQueue.value.filter((item) => item.status === 'error').length,
}))
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

function resetSelectedFileForm() {
    selectedFileForm.original_name = ''
    selectedFileForm.title = ''
    selectedFileForm.alt_text = ''
    selectedFileForm.caption = ''
}

function resetFolderForm() {
    folderForm.name = ''
    validationErrors.value = {}
}

function resetUploadFolderForm() {
    uploadFolderForm.name = ''
    uploadFolderErrors.value = {}
}

function syncSelectedFileForm(file) {
    selectedFileForm.original_name = stripExtension(file.original_name || file.filename || '')
    selectedFileForm.title = file.title ?? ''
    selectedFileForm.alt_text = file.alt_text ?? ''
    selectedFileForm.caption = file.caption ?? ''
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

        if (selectedFileId.value !== null) {
            const nextSelectedFile = files.value.find((file) => file.id === selectedFileId.value) ?? null

            if (nextSelectedFile) {
                syncSelectedFileForm(nextSelectedFile)
            } else {
                selectedFileId.value = null
                resetSelectedFileForm()
            }
        }
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
    selectedFileId.value = null
    await loadLibrary(folder.id)
}

async function openRoot() {
    editingFolderId.value = null
    selectedFileId.value = null
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
    const nextFiles = Array.from(event.target.files ?? [])

    if (nextFiles.length === 0) {
        return
    }

    queueUploadFiles(nextFiles)

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

async function removeFile(file) {
    const confirmed = window.confirm(`Удалить файл "${file.original_name}"?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFile(file.id)

        if (selectedFileId.value === file.id) {
            selectedFileId.value = null
            resetSelectedFileForm()
        }

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

        if (nextFolderId !== currentFolderId.value) {
            noticeMessage.value = 'Файл перемещен в другую папку.'
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось переместить файл.'
        console.error(error)
    } finally {
        movingFileId.value = null
    }
}

function openFileDetails(file) {
    selectedFileId.value = file.id
    activeFileMenuId.value = null
    syncSelectedFileForm(file)
}

function closeFileDetails() {
    selectedFileId.value = null
    resetSelectedFileForm()
}

async function saveSelectedFileDetails() {
    if (!selectedFile.value) {
        return
    }

    filePanelSaving.value = true
    errorMessage.value = ''

    try {
        await updateMediaFile(selectedFile.value.id, {
            original_name: buildFilename(selectedFileForm.original_name, selectedFile.value.extension),
            title: selectedFileForm.title,
            alt_text: selectedFileForm.alt_text,
            caption: selectedFileForm.caption,
        })

        await loadLibrary(currentFolderId.value)
        noticeMessage.value = 'Данные файла обновлены.'
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось обновить метаданные файла.'
        console.error(error)
    } finally {
        filePanelSaving.value = false
    }
}

function formatDate(value) {
    return value ? formatCmsDateTime(value) : 'Без даты'
}

async function copyUrl(file) {
    try {
        await navigator.clipboard.writeText(file.url)
        noticeMessage.value = 'Ссылка на файл скопирована.'
    } catch (error) {
        errorMessage.value = 'Не удалось скопировать ссылку.'
        console.error(error)
    }
}

function toggleFileMenu(file) {
    activeFileMenuId.value = activeFileMenuId.value === file.id ? null : file.id
}

function triggerFileDialog() {
    fileInput.value?.click()
}

function queueUploadFiles(nextFiles) {
    errorMessage.value = ''
    uploadModalOpen.value = true

    uploadQueue.value.push(...nextFiles.map((file) => createUploadItem(file)))
}

function createUploadItem(file) {
    const extension = getExtension(file.name)
    const retryable = isSupportedUpload(file)

    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        file,
        originalName: file.name,
        renameBase: stripExtension(file.name),
        extension,
        mimeType: file.type || resolveMimeTypeByExtension(extension),
        size: file.size,
        folderId: currentFolderId.value,
        previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : '',
        status: retryable ? 'queued' : 'error',
        progress: 0,
        errorMessage: retryable ? '' : 'Формат не поддерживается. Разрешены JPG, PNG, GIF, WEBP, AVIF и BMP.',
        retryable,
        uploadedFile: null,
    }
}

function removeUploadItem(itemId) {
    const item = uploadQueue.value.find((entry) => entry.id === itemId)

    if (!item || (uploading.value && item.status === 'uploading')) {
        return
    }

    revokePreviewUrl(item)
    uploadQueue.value = uploadQueue.value.filter((entry) => entry.id !== itemId)
}

function clearUploadQueue() {
    if (uploading.value) {
        return
    }

    uploadQueue.value.forEach(revokePreviewUrl)
    uploadQueue.value = []
    resetUploadFolderForm()
}

function closeUploadModal() {
    if (uploading.value) {
        return
    }

    clearUploadQueue()
    uploadModalOpen.value = false
}

function revokePreviewUrl(item) {
    if (item.previewUrl) {
        URL.revokeObjectURL(item.previewUrl)
    }
}

function setUploadItemFolder(item, event) {
    item.folderId = event.target.value === '' ? null : Number(event.target.value)
}

async function submitUploadFolder() {
    creatingUploadFolder.value = true
    uploadFolderErrors.value = {}
    errorMessage.value = ''

    try {
        const payload = await createMediaFolder({
            name: uploadFolderForm.name,
            parent_id: currentFolderId.value,
        })

        const newFolderId = payload.data?.id ?? null

        resetUploadFolderForm()
        await loadLibrary(currentFolderId.value)

        if (newFolderId) {
            uploadQueue.value.forEach((item) => {
                if (item.status !== 'success') {
                    item.folderId = newFolderId
                }
            })
        }

        noticeMessage.value = 'Новая папка создана и доступна для загрузки.'
    } catch (error) {
        if (error.response?.status === 422) {
            uploadFolderErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = error.response?.data?.message ?? 'Не удалось создать папку из модалки загрузки.'
        }

        console.error(error)
    } finally {
        creatingUploadFolder.value = false
    }
}

async function uploadQueuedFiles() {
    if (uploading.value || uploadableItems.value.length === 0) {
        return
    }

    uploading.value = true
    errorMessage.value = ''

    let uploadedCurrentFolderCount = 0
    let uploadedOtherFolderCount = 0

    for (const item of uploadQueue.value) {
        if (!item.file || item.retryable === false || item.status === 'success') {
            continue
        }

        item.status = 'uploading'
        item.progress = 0
        item.errorMessage = ''

        try {
            const payload = await uploadMediaFile({
                folderId: item.folderId,
                file: item.file,
                name: item.renameBase,
                onUploadProgress: (progressEvent) => {
                    if (!progressEvent.total) {
                        item.progress = 0

                        return
                    }

                    item.progress = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100))
                },
            })

            item.status = 'success'
            item.progress = 100
            item.uploadedFile = payload.data ?? null

            if ((item.folderId ?? null) === currentFolderId.value) {
                uploadedCurrentFolderCount++
            } else {
                uploadedOtherFolderCount++
            }
        } catch (error) {
            item.status = 'error'
            item.progress = 0
            item.errorMessage = extractUploadError(error)
            console.error(error)
        }
    }

    await loadLibrary(currentFolderId.value)

    if (uploadedCurrentFolderCount > 0 || uploadedOtherFolderCount > 0) {
        const summary = []

        if (uploadedCurrentFolderCount > 0) {
            summary.push(`в текущую папку: ${uploadedCurrentFolderCount}`)
        }

        if (uploadedOtherFolderCount > 0) {
            summary.push(`в другие папки: ${uploadedOtherFolderCount}`)
        }

        noticeMessage.value = `Загрузка завершена, ${summary.join(' | ')}.`
    }

    uploading.value = false
}

function extractUploadError(error) {
    const validation = error.response?.data?.errors ?? {}
    const firstValidationError = Object.values(validation).flat()[0]

    return firstValidationError || error.response?.data?.message || 'Не удалось загрузить файл.'
}

function buildFilename(baseName, extension) {
    const normalizedBaseName = String(baseName || '').trim()

    return extension ? `${normalizedBaseName}.${extension}` : normalizedBaseName
}

function formatBytes(size) {
    const units = ['B', 'KB', 'MB', 'GB']
    let value = Number(size || 0)
    let index = 0

    while (value >= 1024 && index < units.length - 1) {
        value /= 1024
        index += 1
    }

    return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`
}

function stripExtension(filename) {
    const normalized = String(filename || '').trim()

    if (normalized === '') {
        return ''
    }

    return normalized.replace(/\.[^.]+$/, '')
}

function getExtension(filename) {
    const match = String(filename || '').trim().toLowerCase().match(/\.([^.]+)$/)

    return match?.[1] ?? ''
}

function isSupportedUpload(file) {
    const extension = getExtension(file.name)

    return allowedMimeTypes.includes(file.type) || allowedExtensions.includes(extension)
}

function resolveMimeTypeByExtension(extension) {
    const map = {
        jpg: 'image/jpeg',
        jpeg: 'image/jpeg',
        png: 'image/png',
        gif: 'image/gif',
        webp: 'image/webp',
        avif: 'image/avif',
        bmp: 'image/bmp',
    }

    return map[extension] ?? 'application/octet-stream'
}

function resolveUploadStatusLabel(status) {
    const labels = {
        queued: 'Ожидает',
        uploading: 'Загружается',
        success: 'Успешно',
        error: 'Ошибка',
    }

    return labels[status] ?? 'Ожидает'
}

function handleWindowDragEnter(event) {
    if (!hasDraggedFiles(event)) {
        return
    }

    event.preventDefault()
    dragDepth.value += 1
}

function handleWindowDragOver(event) {
    if (!hasDraggedFiles(event)) {
        return
    }

    event.preventDefault()

    if (dragDepth.value === 0) {
        dragDepth.value = 1
    }
}

function handleWindowDragLeave(event) {
    if (!hasDraggedFiles(event)) {
        return
    }

    event.preventDefault()
    dragDepth.value = Math.max(0, dragDepth.value - 1)
}

function handleWindowDrop(event) {
    if (!hasDraggedFiles(event)) {
        return
    }

    event.preventDefault()
    dragDepth.value = 0

    const droppedFiles = Array.from(event.dataTransfer?.files ?? [])

    if (droppedFiles.length > 0) {
        queueUploadFiles(droppedFiles)
    }
}

function hasDraggedFiles(event) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

onMounted(async () => {
    await loadCmsSettings()
    await loadLibrary()

    window.addEventListener('dragenter', handleWindowDragEnter)
    window.addEventListener('dragover', handleWindowDragOver)
    window.addEventListener('dragleave', handleWindowDragLeave)
    window.addEventListener('drop', handleWindowDrop)
})

onBeforeUnmount(() => {
    uploadQueue.value.forEach(revokePreviewUrl)
    window.removeEventListener('dragenter', handleWindowDragEnter)
    window.removeEventListener('dragover', handleWindowDragOver)
    window.removeEventListener('dragleave', handleWindowDragLeave)
    window.removeEventListener('drop', handleWindowDrop)
})
</script>

<template>
    <AdminPage
        eyebrow="Media"
        title="Медиабиблиотека"
        description="Изображения хранятся в папках на public disk, а структура и метаданные сохраняются в базе данных. SVG пока запрещен на уровне загрузки."
    >
        <input ref="fileInput" type="file" hidden multiple :accept="uploadAccept" @change="handleFileSelect">

        <div v-if="dragOverlayVisible" class="media-drag-overlay">
            <div class="media-drag-overlay__panel">
                <p class="eyebrow">Media Upload</p>
                <h2>Отпустите файлы для загрузки</h2>
                <p>Файлы попадут в очередь, где можно переименовать их, выбрать папки и запустить загрузку.</p>
            </div>
        </div>

        <div v-if="noticeMessage" class="media-notice">
            {{ noticeMessage }}
        </div>

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

                    <section class="media-upload-dropzone" @click="triggerFileDialog">
                        <div class="media-upload-dropzone__copy">
                            <p class="eyebrow">Upload</p>
                            <h2>Drag & Drop или выбор файлов</h2>
                            <p>Перетащите изображения на страницу медиатеки или откройте очередь вручную. Поддерживаются JPG, PNG, GIF, WEBP, AVIF и BMP.</p>
                        </div>

                        <div class="admin-actions-row">
                            <AdminButton type="button" variant="primary">
                                Выбрать файлы
                            </AdminButton>

                            <button type="button" class="button-link" @click.stop="uploadModalOpen = true">
                                Открыть очередь
                            </button>
                        </div>
                    </section>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>

                    <label class="admin-form-label">
                        <span>Поиск по текущей папке</span>
                        <input v-model="searchQuery" class="admin-input" type="search" placeholder="Название файла, папки, MIME, путь...">
                    </label>
                </div>
            </AdminCard>

            <AdminCard>
                <div class="media-workspace" :class="{ 'has-details': Boolean(selectedFile) }">
                    <div class="media-library-stack">
                        <section class="media-library-section">
                            <div class="media-library-section__header">
                                <div>
                                    <h2>Папки</h2>
                                    <p class="muted">Выберите папку, чтобы открыть вложения или управлять структурой медиатеки.</p>
                                </div>
                            </div>

                            <p v-if="loading" class="muted">Загрузка медиабиблиотеки...</p>
                            <div v-else-if="folders.length === 0" class="media-empty-state">
                                <h3>В этой папке пока нет вложенных папок</h3>
                                <p>Создайте первую папку слева или загрузите файлы в текущую директорию.</p>
                            </div>
                            <div v-else-if="filteredFolders.length === 0" class="media-empty-state media-empty-state--search">
                                <h3>Папки не найдены</h3>
                                <p>Измените строку поиска или перейдите в другую папку.</p>
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
                        </section>

                        <section class="media-library-section">
                            <div class="media-library-section__header">
                                <div>
                                    <h2>Файлы</h2>
                                    <p class="muted">Клик по карточке открывает детальную панель справа. Действия по файлу собраны в компактном меню.</p>
                                </div>
                            </div>

                            <div v-if="!loading && files.length === 0" class="media-empty-state">
                                <h3>Файлов в текущей папке пока нет</h3>
                                <p>Перетащите изображения на страницу или откройте очередь загрузки кнопкой слева.</p>
                            </div>
                            <div v-else-if="files.length > 0 && filteredFiles.length === 0" class="media-empty-state media-empty-state--search">
                                <h3>Ничего не найдено</h3>
                                <p>Поиск не нашел файлов в текущей папке. Попробуйте другое имя, MIME или путь.</p>
                            </div>

                            <div v-if="filteredFiles.length > 0" class="media-file-grid media-file-grid--tiles">
                                <article
                                    v-for="file in filteredFiles"
                                    :key="file.id"
                                    class="media-file-tile"
                                    :class="{ 'is-selected': selectedFileId === file.id }"
                                    @click="openFileDetails(file)"
                                >
                                    <div class="media-file-tile__preview">
                                        <img :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
                                    </div>

                                    <div class="media-file-tile__body">
                                        <div class="media-file-tile__header">
                                            <div>
                                                <h3>{{ file.title || file.original_name }}</h3>
                                                <p class="muted">{{ file.size_human }}<span v-if="file.width && file.height"> | {{ file.width }} x {{ file.height }}</span></p>
                                            </div>

                                            <div class="media-file-tile__menu-wrap">
                                                <button type="button" class="media-file-tile__menu-button" @click.stop="toggleFileMenu(file)">
                                                    ⋯
                                                </button>

                                                <div v-if="activeFileMenuId === file.id" class="media-file-tile__menu">
                                                    <button type="button" @click.stop="openFileDetails(file)">
                                                        Открыть
                                                    </button>
                                                    <button type="button" @click.stop="copyUrl(file)">
                                                        Копировать ссылку
                                                    </button>
                                                    <button type="button" class="is-danger" @click.stop="removeFile(file)">
                                                        Удалить
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="muted">{{ file.original_name }}</p>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>

                    <aside v-if="selectedFile" class="media-detail-panel">
                        <div class="media-detail-panel__header">
                            <div>
                                <p class="eyebrow">File details</p>
                                <h2>{{ selectedFile.title || selectedFile.original_name }}</h2>
                            </div>

                            <button type="button" class="button-link" @click="closeFileDetails">
                                Закрыть
                            </button>
                        </div>

                        <div class="media-detail-panel__preview">
                            <img :src="selectedFile.preview_url || selectedFile.url" :alt="selectedFile.alt_text || selectedFile.original_name">
                        </div>

                        <div class="media-detail-panel__meta">
                            <p><strong>Путь:</strong> {{ selectedFile.path }}</p>
                            <p><strong>Вес:</strong> {{ selectedFile.size_human }}</p>
                            <p><strong>MIME:</strong> {{ selectedFile.mime_type }}</p>
                            <p><strong>Папка:</strong> {{ selectedFile.folder_name || 'Корень' }}</p>
                            <p v-if="selectedFile.width && selectedFile.height"><strong>Размеры:</strong> {{ selectedFile.width }} x {{ selectedFile.height }}</p>
                            <p><strong>Загружен:</strong> {{ formatDate(selectedFile.created_at) }}</p>
                        </div>

                        <label class="admin-form-label">
                            <span>Имя файла</span>
                            <div class="media-file-name-input">
                                <input v-model="selectedFileForm.original_name" class="admin-input" type="text" placeholder="Например, hero-banner">
                                <span v-if="selectedFile.extension" class="media-file-name-input__suffix">.{{ selectedFile.extension }}</span>
                            </div>
                        </label>

                        <label class="admin-form-label">
                            <span>Заголовок</span>
                            <input v-model="selectedFileForm.title" class="admin-input" type="text" placeholder="Например, Hero image">
                        </label>

                        <label class="admin-form-label">
                            <span>Alt текст</span>
                            <input v-model="selectedFileForm.alt_text" class="admin-input" type="text" placeholder="Описание изображения для accessibility">
                        </label>

                        <label class="admin-form-label">
                            <span>Подпись</span>
                            <textarea v-model="selectedFileForm.caption" class="admin-textarea" rows="3" placeholder="Короткая подпись к изображению"></textarea>
                        </label>

                        <label class="admin-form-label">
                            <span>Переместить в папку</span>
                            <select
                                class="admin-input"
                                :value="selectedFile.folder_id ?? ''"
                                :disabled="movingFileId === selectedFile.id"
                                @change="changeFileFolder(selectedFile, $event)"
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
                            <AdminButton type="button" variant="primary" @click="saveSelectedFileDetails">
                                {{ filePanelSaving ? 'Сохранение...' : 'Сохранить' }}
                            </AdminButton>

                            <button type="button" class="button-link" @click="copyUrl(selectedFile)">
                                Копировать ссылку
                            </button>

                            <button type="button" class="button-link media-file-card__action--danger" @click="removeFile(selectedFile)">
                                Удалить
                            </button>
                        </div>
                    </aside>
                </div>
            </AdminCard>
        </div>

        <div v-if="uploadModalOpen" class="admin-modal" @click.self="closeUploadModal">
            <div class="admin-modal__dialog admin-modal__dialog--wide media-upload-modal">
                <div class="admin-modal__header">
                    <div>
                        <p class="eyebrow">Upload Queue</p>
                        <h2>Массовая загрузка изображений</h2>
                        <p class="muted">Переименуйте файлы, укажите папки назначения и запустите загрузку. Успешные и ошибочные элементы останутся в списке.</p>
                    </div>

                    <div class="admin-actions-row">
                        <AdminButton type="button" @click="triggerFileDialog">
                            Добавить файлы
                        </AdminButton>
                        <button type="button" class="button-link" :disabled="uploading" @click="closeUploadModal">
                            {{ uploadStats.success > 0 || uploadStats.error > 0 ? 'Закрыть' : 'Отмена' }}
                        </button>
                    </div>
                </div>

                <div class="admin-modal__body media-upload-modal__body">
                    <div class="media-upload-summary">
                        <div>
                            <h3>Общий прогресс</h3>
                            <p class="muted">Всего: {{ uploadStats.total }} | В очереди: {{ uploadStats.queued }} | Успешно: {{ uploadStats.success }} | Ошибки: {{ uploadStats.error }}</p>
                        </div>

                        <div class="media-progress media-progress--large">
                            <span :style="{ width: `${uploadProgress}%` }"></span>
                        </div>
                    </div>

                    <form class="media-upload-folder-inline" @submit.prevent="submitUploadFolder">
                        <label class="admin-form-label">
                            <span>Быстро создать папку в текущем разделе</span>
                            <input v-model="uploadFolderForm.name" class="admin-input" type="text" placeholder="Например, Homepage banners">
                            <small v-if="uploadFolderErrors.name" class="error-text">{{ uploadFolderErrors.name[0] }}</small>
                        </label>

                        <div class="admin-actions-row">
                            <AdminButton type="submit" variant="primary">
                                {{ creatingUploadFolder ? 'Создание...' : 'Создать папку' }}
                            </AdminButton>
                        </div>
                    </form>

                    <div v-if="uploadQueue.length === 0" class="media-empty-state media-empty-state--modal">
                        <h3>Очередь пока пустая</h3>
                        <p>Перетащите файлы на страницу или добавьте их кнопкой выше.</p>
                    </div>

                    <div v-else class="media-upload-list">
                        <article
                            v-for="item in uploadQueue"
                            :key="item.id"
                            class="media-upload-item"
                            :class="[`is-${item.status}`]"
                        >
                            <div class="media-upload-item__preview">
                                <img v-if="item.previewUrl" :src="item.previewUrl" :alt="item.originalName">
                                <span v-else>{{ item.extension ? item.extension.toUpperCase() : 'FILE' }}</span>
                            </div>

                            <div class="media-upload-item__body">
                                <div class="media-upload-item__top">
                                    <div>
                                        <strong>{{ item.originalName }}</strong>
                                        <p class="muted">{{ formatBytes(item.size) }} | {{ item.mimeType }}</p>
                                    </div>

                                    <span class="media-upload-status" :class="[`is-${item.status}`]">
                                        {{ resolveUploadStatusLabel(item.status) }}
                                    </span>
                                </div>

                                <div class="media-upload-item__fields">
                                    <label class="admin-form-label">
                                        <span>Имя файла</span>
                                        <div class="media-file-name-input">
                                            <input
                                                v-model="item.renameBase"
                                                class="admin-input"
                                                type="text"
                                                :disabled="item.status === 'success' || item.status === 'uploading'"
                                                placeholder="Например, hero-banner"
                                            >
                                            <span v-if="item.extension" class="media-file-name-input__suffix">.{{ item.extension }}</span>
                                        </div>
                                    </label>

                                    <label class="admin-form-label">
                                        <span>Папка назначения</span>
                                        <select
                                            class="admin-input"
                                            :value="item.folderId ?? ''"
                                            :disabled="item.status === 'success' || item.status === 'uploading'"
                                            @change="setUploadItemFolder(item, $event)"
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
                                </div>

                                <div class="media-upload-item__progress">
                                    <div class="media-progress">
                                        <span :style="{ width: `${item.progress}%` }"></span>
                                    </div>
                                    <span class="muted">{{ item.progress }}%</span>
                                </div>

                                <p v-if="item.errorMessage" class="error-text">{{ item.errorMessage }}</p>
                            </div>

                            <button type="button" class="button-link media-file-card__action--danger" :disabled="item.status === 'uploading'" @click="removeUploadItem(item.id)">
                                Убрать
                            </button>
                        </article>
                    </div>

                    <div class="media-upload-modal__actions">
                        <AdminButton type="button" variant="primary" :disabled="uploadableItems.length === 0 || uploading" @click="uploadQueuedFiles">
                            {{ uploading ? 'Загрузка...' : 'Загрузить' }}
                        </AdminButton>

                        <button type="button" class="button-link" :disabled="uploading || uploadQueue.length === 0" @click="clearUploadQueue">
                            Очистить список
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminPage>
</template>