<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import AdminButton from '../ui/AdminButton.vue'
import MediaFolders from './MediaFolders.vue'
import MediaGrid from './MediaGrid.vue'
import MediaSidebar from './MediaSidebar.vue'
import MediaUploader from './MediaUploader.vue'
import {
    createMediaFolder,
    deleteMediaFile,
    deleteMediaFolder,
    fetchMediaFile,
    fetchMediaLibrary,
    moveMediaFile,
    updateMediaFile,
    updateMediaFolder,
    uploadMediaFile,
} from '../../api/media'
import {
    DEFAULT_MEDIA_ACCEPT,
    buildFilename,
    createMediaSelection,
    formatBytes,
    getExtension,
    isAcceptedUpload,
    normalizeToArray,
    resolveMimeTypeByExtension,
    stripExtension,
    toNumericId,
} from './mediaHelpers'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Media Library',
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    accept: {
        type: String,
        default: DEFAULT_MEDIA_ACCEPT,
    },
    folder: {
        type: [Number, String, null],
        default: null,
    },
    allowUpload: {
        type: Boolean,
        default: true,
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'select'])

const loading = ref(false)
const saving = ref(false)
const uploading = ref(false)
const errorMessage = ref('')
const noticeMessage = ref('')
const createErrors = ref({})
const currentFolder = ref(null)
const breadcrumbs = ref([])
const folders = ref([])
const folderOptions = ref([])
const files = ref([])
const searchQuery = ref('')
const selectedIdsState = ref([])
const selectedItemsById = ref({})
const activeFileId = ref(null)
const uploadQueue = ref([])
const uploadPanelOpen = ref(false)
const dragDepth = ref(0)
const mainPanelRef = ref(null)
const uploadSectionRef = ref(null)
const recentUploadIds = ref([])
const recentOnly = ref(false)

const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase())
const filteredFiles = computed(() => {
    const baseFiles = recentOnly.value && recentUploadIds.value.length > 0
        ? files.value.filter((file) => recentUploadIds.value.includes(file.id))
        : files.value

    if (normalizedSearch.value === '') {
        return baseFiles
    }

    return baseFiles.filter((file) => [file.original_name, file.title, file.alt_text, file.folder_name, file.mime_type]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch.value)))
})
const selectedFile = computed(() => {
    const current = files.value.find((file) => file.id === activeFileId.value)

    return current ?? selectedItemsById.value[activeFileId.value] ?? null
})
const currentFolderId = computed(() => currentFolder.value?.id ?? null)
const moveFolderOptions = computed(() => [{ id: null, name: 'Корень', path: 'media' }, ...folderOptions.value])
const uploadProgress = computed(() => {
    const items = uploadQueue.value.filter((item) => item.file)

    if (items.length === 0) {
        return 0
    }

    const total = items.reduce((sum, item) => sum + item.progress, 0)

    return Math.round(total / items.length)
})
const hasFiles = computed(() => filteredFiles.value.length > 0)
const dragOverlayVisible = computed(() => dragDepth.value > 0)
const queueCount = computed(() => uploadQueue.value.length)
const hasRecentUploads = computed(() => recentUploadIds.value.length > 0)

watch(() => props.open, async (isOpen) => {
    if (!isOpen) {
        return
    }

    await initializeModal()
})

watch(() => props.selectedIds, (value) => {
    if (!props.open) {
        syncSelectedIds(value)
    }
}, { deep: true })

async function initializeModal() {
    searchQuery.value = ''
    errorMessage.value = ''
    noticeMessage.value = ''
    uploadPanelOpen.value = false
    dragDepth.value = 0
    recentOnly.value = false
    recentUploadIds.value = []
    syncSelectedIds(props.selectedIds)

    const initialFolderId = await resolveInitialFolderId()
    await loadLibrary(initialFolderId)
}

function syncSelectedIds(value) {
    const ids = normalizeToArray(value)
        .map((item) => toNumericId(item))
        .filter((item) => item !== null)

    selectedIdsState.value = Array.from(new Set(ids))
    activeFileId.value = selectedIdsState.value[0] ?? null
}

async function resolveInitialFolderId() {
    const selectedId = selectedIdsState.value[0] ?? null

    if (selectedId === null) {
        return toNumericId(props.folder)
    }

    try {
        const payload = await fetchMediaFile(selectedId)
        const file = createMediaSelection(payload.data ?? payload)

        rememberFile(file)
        activeFileId.value = file.id

        return file.folder_id ?? toNumericId(props.folder)
    } catch (error) {
        console.error(error)
        return toNumericId(props.folder)
    }
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
        files.value = (payload.data?.files ?? []).map((file) => createMediaSelection(file))

        files.value.forEach(rememberFile)

        if (activeFileId.value === null && files.value[0]) {
            activeFileId.value = files.value[0].id
        }
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить медиатеку.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

function rememberFile(file) {
    if (!file?.id) {
        return
    }

    selectedItemsById.value = {
        ...selectedItemsById.value,
        [file.id]: createMediaSelection(file),
    }
}

function openRoot() {
    loadLibrary(null)
}

function openFolder(folder) {
    loadLibrary(folder.id)
}

async function createFolder(name) {
    createErrors.value = {}
    saving.value = true

    try {
        await createMediaFolder({
            name,
            parent_id: currentFolderId.value,
        })

        noticeMessage.value = 'Папка создана.'
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        if (error.response?.status === 422) {
            createErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = error.response?.data?.message ?? 'Не удалось создать папку.'
        }

        console.error(error)
    } finally {
        saving.value = false
    }
}

async function renameFolder({ folder, name, parent_id }) {
    saving.value = true
    errorMessage.value = ''

    try {
        await updateMediaFolder(folder.id, {
            name,
            parent_id,
        })

        noticeMessage.value = 'Папка обновлена.'
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось обновить папку.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removeFolder(folder) {
    const confirmed = window.confirm(`Удалить папку "${folder.name}"? Только если она пуста.`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFolder(folder.id)
        noticeMessage.value = 'Папка удалена.'
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось удалить папку.'
        console.error(error)
    }
}

function handleFileClick(file) {
    rememberFile(file)
    activeFileId.value = file.id

    if (!props.multiple) {
        selectedIdsState.value = [file.id]
        return
    }

    const selected = new Set(selectedIdsState.value)

    if (selected.has(file.id)) {
        selected.delete(file.id)
    } else {
        selected.add(file.id)
    }

    selectedIdsState.value = Array.from(selected)
}

async function saveFileMeta(payload) {
    if (!selectedFile.value) {
        return
    }

    saving.value = true
    errorMessage.value = ''

    try {
        await updateMediaFile(selectedFile.value.id, {
            original_name: buildFilename(payload.original_name || stripExtension(selectedFile.value.original_name), selectedFile.value.extension),
            title: payload.title,
            alt_text: payload.alt_text,
            caption: payload.caption,
        })

        noticeMessage.value = 'Данные изображения обновлены.'
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось обновить данные изображения.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function moveSelectedFile({ file, folder_id }) {
    errorMessage.value = ''

    try {
        await moveMediaFile(file.id, { folder_id })
        noticeMessage.value = 'Файл перемещен.'
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось переместить файл.'
        console.error(error)
    }
}

async function deleteSelectedFile(file) {
    const confirmed = window.confirm(`Удалить файл "${file.original_name}"?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFile(file.id)

        selectedIdsState.value = selectedIdsState.value.filter((id) => id !== file.id)
        activeFileId.value = null
        delete selectedItemsById.value[file.id]

        noticeMessage.value = 'Файл удален.'
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось удалить файл.'
        console.error(error)
    }
}

async function copyUrl(file) {
    try {
        await navigator.clipboard.writeText(file.url)
        noticeMessage.value = 'URL скопирован.'
    } catch (error) {
        errorMessage.value = 'Не удалось скопировать URL.'
        console.error(error)
    }
}

function queueFiles(nextFiles) {
    errorMessage.value = ''
    uploadQueue.value.push(...nextFiles.map((file) => createUploadItem(file)))
    uploadPanelOpen.value = true

    nextTick(() => {
        uploadSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
}

function eventHasFiles(event) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

function handleMainDragEnter(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return
    }

    dragDepth.value += 1
}

function handleMainDragOver(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return
    }

    event.preventDefault()
}

function handleMainDragLeave(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return
    }

    dragDepth.value = Math.max(0, dragDepth.value - 1)
}

function handleMainDrop(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return
    }

    event.preventDefault()
    dragDepth.value = 0

    const files = Array.from(event.dataTransfer?.files ?? [])

    if (files.length > 0) {
        queueFiles(files)
    }
}

function createUploadItem(file) {
    const extension = getExtension(file.name)
    const accepted = isAcceptedUpload(file, props.accept)

    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        file,
        originalName: file.name,
        renameBase: stripExtension(file.name),
        extension,
        mimeType: file.type || resolveMimeTypeByExtension(extension),
        size: file.size,
        folderId: currentFolderId.value,
        previewUrl: String(file.type || '').startsWith('image/') ? URL.createObjectURL(file) : '',
        progress: 0,
        status: accepted ? 'queued' : 'error',
        errorMessage: accepted ? '' : 'Формат не поддерживается текущими правилами accept.',
    }
}

function updateUploadItem({ id, changes }) {
    uploadQueue.value = uploadQueue.value.map((item) => item.id === id ? { ...item, ...changes } : item)
}

function removeUploadItem(id) {
    const item = uploadQueue.value.find((entry) => entry.id === id)

    if (item?.previewUrl) {
        URL.revokeObjectURL(item.previewUrl)
    }

    uploadQueue.value = uploadQueue.value.filter((entry) => entry.id !== id)
}

function clearQueue() {
    uploadQueue.value.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl)
        }
    })

    uploadQueue.value = []
}

async function uploadQueuedFiles() {
    const uploadableItems = uploadQueue.value.filter((item) => item.status === 'queued' || item.status === 'error')

    if (uploadableItems.length === 0) {
        return
    }

    uploading.value = true
    errorMessage.value = ''

    const uploadedIds = []

    for (const item of uploadableItems) {
        updateUploadItem({ id: item.id, changes: { status: 'uploading', progress: 0, errorMessage: '' } })

        try {
            const response = await uploadMediaFile({
                folderId: item.folderId,
                file: item.file,
                name: item.renameBase,
                onUploadProgress: (event) => {
                    if (!event.total) {
                        return
                    }

                    updateUploadItem({
                        id: item.id,
                        changes: { progress: Math.round((event.loaded / event.total) * 100) },
                    })
                },
            })

            const uploadedFile = createMediaSelection(response.data ?? response)
            rememberFile(uploadedFile)

            if (uploadedFile.id !== null) {
                uploadedIds.push(uploadedFile.id)
            }

            updateUploadItem({
                id: item.id,
                changes: {
                    status: 'success',
                    progress: 100,
                    uploadedFile,
                },
            })
        } catch (error) {
            updateUploadItem({
                id: item.id,
                changes: {
                    status: 'error',
                    errorMessage: error.response?.data?.message ?? 'Не удалось загрузить файл.',
                },
            })
            console.error(error)
        }
    }

    uploading.value = false
    noticeMessage.value = 'Очередь обработана.'
    await loadLibrary(currentFolderId.value)

    recentUploadIds.value = uploadedIds
    recentOnly.value = uploadedIds.length > 0

    if (uploadedIds[0] !== undefined) {
        activeFileId.value = uploadedIds[0]

        if (!props.multiple) {
            selectedIdsState.value = [uploadedIds[0]]
        }

        nextTick(() => {
            mainPanelRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
        })
    }
}

function closeModal() {
    emit('close')
}

function submitSelection() {
    const ids = props.multiple
        ? selectedIdsState.value
        : selectedFile.value?.id
            ? [selectedFile.value.id]
            : []

    const items = ids
        .map((id) => selectedItemsById.value[id] ?? files.value.find((file) => file.id === id))
        .filter(Boolean)

    emit('select', props.multiple ? items : items[0] ?? null)
    closeModal()
}
</script>

<template>
    <div v-if="open" class="admin-modal" @click.self="closeModal">
        <div class="admin-modal__dialog admin-modal__dialog--wide media-library-modal">
            <div class="admin-modal__header media-library-modal__header">
                <div>
                    <p class="eyebrow">Media</p>
                    <h2>{{ title }}</h2>
                    <p class="muted">Универсальная медиатека для выбора, загрузки и управления изображениями.</p>
                </div>

                <div class="admin-actions-row">
                    <AdminButton type="button" :disabled="!selectedFile && !multiple" variant="primary" @click="submitSelection">
                        {{ multiple ? `Выбрать (${selectedIdsState.length})` : 'Выбрать' }}
                    </AdminButton>
                    <AdminButton type="button" @click="closeModal">
                        Закрыть
                    </AdminButton>
                </div>
            </div>

            <div class="admin-modal__body media-library-modal__body">
                <div class="media-library-modal__content">
                    <div
                        ref="mainPanelRef"
                        class="media-library-modal__main"
                        @dragenter="handleMainDragEnter"
                        @dragover="handleMainDragOver"
                        @dragleave="handleMainDragLeave"
                        @drop="handleMainDrop"
                    >
                        <section class="media-library-modal__toolbar">
                            <label class="admin-form-label media-library-modal__search">
                                <span>Поиск</span>
                                <input v-model="searchQuery" class="admin-input" type="search" placeholder="Название файла, alt, mime-type, папка">
                            </label>

                            <div class="media-library-modal__actions">
                                <div class="media-library-modal__stat">
                                    <strong>{{ filteredFiles.length }}</strong>
                                    <span>файлов в текущем списке</span>
                                </div>

                                <AdminButton
                                    v-if="hasRecentUploads"
                                    type="button"
                                    :variant="recentOnly ? 'primary' : undefined"
                                    @click="recentOnly = !recentOnly"
                                >
                                    {{ recentOnly ? 'Показать все' : 'Только недавние' }}
                                </AdminButton>

                                <AdminButton v-if="allowUpload" type="button" @click="uploadPanelOpen = !uploadPanelOpen">
                                    {{ uploadPanelOpen ? 'Скрыть очередь' : `Очередь загрузки${queueCount > 0 ? ` (${queueCount})` : ''}` }}
                                </AdminButton>
                            </div>
                        </section>

                        <p v-if="loading" class="muted">Загрузка медиатеки...</p>
                        <p v-else-if="errorMessage" class="error-text">{{ errorMessage }}</p>
                        <p v-else-if="noticeMessage" class="muted">{{ noticeMessage }}</p>

                        <MediaGrid v-if="hasFiles" :files="filteredFiles" :selected-ids="selectedIdsState" :accent-ids="recentUploadIds" @select="handleFileClick" />

                        <p v-else-if="!loading" class="muted">В текущей папке пока нет изображений.</p>

                        <details class="media-library-modal__section" :open="folders.length > 0 || currentFolder !== null">
                            <summary>Папки и структура</summary>
                            <MediaFolders
                                :breadcrumbs="breadcrumbs"
                                :folders="folders"
                                :folder-options="folderOptions"
                                :current-folder="currentFolder"
                                :create-errors="createErrors"
                                :busy="saving"
                                @open-root="openRoot"
                                @open-folder="openFolder"
                                @create-folder="createFolder"
                                @rename-folder="renameFolder"
                                @delete-folder="removeFolder"
                            />
                        </details>

                        <details v-if="allowUpload" ref="uploadSectionRef" class="media-library-modal__section" :open="uploadPanelOpen || queueCount > 0">
                            <summary>Загрузка и очередь</summary>
                            <MediaUploader
                                :queue="uploadQueue"
                                :folder-options="moveFolderOptions"
                                :uploading="uploading"
                                :upload-progress="uploadProgress"
                                :accept="accept"
                                @queue-files="queueFiles"
                                @remove-item="removeUploadItem"
                                @clear-queue="clearQueue"
                                @upload="uploadQueuedFiles"
                                @update-item="updateUploadItem"
                            />
                        </details>

                        <transition name="media-drag-overlay">
                            <div v-if="dragOverlayVisible" class="media-library-modal__drag-overlay">
                                <div class="media-library-modal__drag-card">
                                    <strong>Отпустите файлы, чтобы добавить в очередь</strong>
                                    <span>После добавления откроется блок загрузки и мы прокрутим его в видимую область.</span>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <MediaSidebar
                        :file="selectedFile"
                        :move-folder-options="moveFolderOptions"
                        :saving="saving"
                        @close="activeFileId = null"
                        @save="saveFileMeta"
                        @select="submitSelection"
                        @copy-url="copyUrl"
                        @delete="deleteSelectedFile"
                        @move-file="moveSelectedFile"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.media-library-modal {
    width: min(1380px, calc(100vw - 2rem));
    max-height: calc(100vh - 2rem);
}

.media-library-modal__header {
    align-items: flex-start;
}

.media-library-modal__body {
    overflow: hidden;
    padding: 0;
}

.media-library-modal__content {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.8fr);
    min-height: min(760px, calc(100vh - 12rem));
}

.media-library-modal__main {
    position: relative;
    display: grid;
    gap: 1rem;
    padding: 1.25rem;
    overflow: auto;
}

.media-library-modal__toolbar {
    position: sticky;
    top: -1.25rem;
    z-index: 5;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
    align-items: end;
    padding: 0.9rem 0 1rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.9) 78%, rgba(255, 255, 255, 0));
    backdrop-filter: blur(8px);
}

.media-library-modal__search {
    margin: 0;
}

.media-library-modal__actions {
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.media-library-modal__stat {
    display: grid;
    gap: 0.1rem;
    min-width: 140px;
    padding: 0.8rem 0.95rem;
    border-radius: 16px;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.media-library-modal__stat strong {
    font-size: 1.05rem;
}

.media-library-modal__stat span {
    color: rgba(71, 85, 105, 0.9);
    font-size: 0.82rem;
}

.media-library-modal__section {
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.8);
    overflow: hidden;
}

.media-library-modal__section summary {
    padding: 0.95rem 1rem;
    cursor: pointer;
    font-weight: 600;
    color: #1e3a5f;
    list-style: none;
}

.media-library-modal__section summary::-webkit-details-marker {
    display: none;
}

.media-library-modal__section :deep(.media-folders),
.media-library-modal__section :deep(.media-uploader) {
    margin: 0 1rem 1rem;
}

.media-library-modal__drag-overlay {
    position: absolute;
    inset: 1.25rem;
    display: grid;
    place-items: center;
    border-radius: 28px;
    background: rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(4px);
    z-index: 10;
}

.media-library-modal__drag-card {
    display: grid;
    gap: 0.35rem;
    width: min(520px, calc(100% - 2rem));
    padding: 1.4rem 1.5rem;
    border-radius: 24px;
    border: 1px dashed rgba(59, 130, 246, 0.55);
    background: linear-gradient(180deg, rgba(239, 246, 255, 0.97), rgba(255, 255, 255, 0.95));
    text-align: center;
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.18);
}

.media-library-modal__drag-card strong {
    font-size: 1.05rem;
    color: #1d4ed8;
}

.media-library-modal__drag-card span {
    color: rgba(51, 65, 85, 0.92);
}

.media-drag-overlay-enter-active,
.media-drag-overlay-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.media-drag-overlay-enter-from,
.media-drag-overlay-leave-to {
    opacity: 0;
    transform: scale(0.98);
}

@media (max-width: 1100px) {
    .media-library-modal__content {
        grid-template-columns: 1fr;
    }

    .media-library-modal__toolbar {
        grid-template-columns: 1fr;
    }

    .media-library-modal__actions {
        justify-content: flex-start;
    }
}
</style>