<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { useAdminNotifications } from '../../composables/useAdminNotifications'
import { formatCmsDateTime, loadCmsSettings } from '../../composables/useCmsSettings'
import {
    createMediaFolder,
    deleteMediaFile,
    deleteMediaFolder,
    fetchMediaLibrary,
    moveMediaFile,
    replaceMediaFile,
    transformMediaFile,
    updateMediaFile,
    updateMediaFolder,
    uploadMediaFile,
} from '../../api/media'
import {
    buildFilename,
    createMediaSelection,
    DEFAULT_MEDIA_ACCEPT,
    formatBytes,
    getExtension,
    isAcceptedUpload,
    stripExtension,
    withMediaCacheBust,
} from '../../components/media/mediaHelpers'

const storageKeys = {
    viewMode: 'cms.media.viewMode',
    sortBy: 'cms.media.sortBy',
    folderScope: 'cms.media.folderScope',
}

const fileTypeTabs = [
    { value: 'all', label: 'Все файлы' },
    { value: 'images', label: 'Изображения' },
    { value: 'documents', label: 'Документы' },
    { value: 'archives', label: 'Архивы' },
    { value: 'other', label: 'Другое' },
]

const loading = ref(true)
const savingDetails = ref(false)
const savingFolder = ref(false)
const uploading = ref(false)
const errorMessage = ref('')
const currentFolder = ref(null)
const breadcrumbs = ref([])
const folders = ref([])
const folderOptions = ref([])
const files = ref([])
const searchQuery = ref('')
const activeTab = ref('all')
const typeFilter = ref('all')
const sortBy = ref(readStoredPreference(storageKeys.sortBy, 'date_desc'))
const viewMode = ref(readStoredPreference(storageKeys.viewMode, 'grid'))
const folderScope = ref(readStoredPreference(storageKeys.folderScope, 'current'))
const dateFilter = ref('all')
const sizeFilter = ref('all')
const filtersOpen = ref(false)
const selectedFileId = ref(null)
const folderModalOpen = ref(false)
const activeFolderMenuId = ref(null)
const dragDepth = ref(0)
const uploadQueue = ref([])

const fileInput = ref(null)
const replaceInput = ref(null)
const pendingReplaceFileId = ref(null)
const transforming = ref(false)
const transformStageRef = ref(null)
const transformTool = ref('')

const transformDragState = reactive({
    active: false,
    mode: '',
    startX: 0,
    startY: 0,
    crop: null,
})
const { notify } = useAdminNotifications()

const folderErrors = ref({})
const folderModalMode = ref('create')
const folderModalTitle = computed(() => {
    if (folderModalMode.value === 'rename') {
        return 'Переименовать папку'
    }

    if (folderModalMode.value === 'move') {
        return 'Переместить папку'
    }

    return 'Создать папку'
})

const folderForm = reactive({
    id: null,
    name: '',
    slug: '',
    parent_id: '',
    slugTouched: false,
})

const selectedFileForm = reactive({
    original_name: '',
    alt_text: '',
    folder_id: '',
})

const transformForm = reactive({
    crop_enabled: true,
    crop_x: 0.05,
    crop_y: 0.05,
    crop_width: 0.9,
    crop_height: 0.9,
    resize_width: '',
    resize_height: '',
    maintain_aspect_ratio: true,
    quality: '',
    format: 'original',
})

const uploadStats = computed(() => ({
    total: uploadQueue.value.length,
    queued: uploadQueue.value.filter((item) => item.status === 'queued').length,
    uploading: uploadQueue.value.filter((item) => item.status === 'uploading').length,
    success: uploadQueue.value.filter((item) => item.status === 'success').length,
    error: uploadQueue.value.filter((item) => item.status === 'error').length,
}))

const uploadProgress = computed(() => {
    const items = uploadQueue.value.filter((item) => item.file)

    if (items.length === 0) {
        return 0
    }

    const total = items.reduce((sum, item) => sum + item.progress, 0)

    return Math.round(total / items.length)
})

const currentFolderId = computed(() => currentFolder.value?.id ?? null)
const selectedFile = computed(() => files.value.find((file) => file.id === selectedFileId.value) ?? null)
const dragOverlayVisible = computed(() => dragDepth.value > 0)
const moveFolderOptions = computed(() => [{ id: null, name: 'Корень', path: 'media' }, ...folderOptions.value])
const canTransformSelectedFile = computed(() => isTransformableImage(selectedFile.value))
const transformFormatOptions = computed(() => [
    { value: 'original', label: 'Оставить текущий формат' },
    { value: 'jpg', label: 'JPEG' },
    { value: 'png', label: 'PNG' },
    { value: 'webp', label: 'WebP' },
])
const transformSourceDimensions = computed(() => {
    if (!selectedFile.value?.width || !selectedFile.value?.height) {
        return { width: null, height: null }
    }

    if (!transformForm.crop_enabled) {
        return {
            width: Number(selectedFile.value.width),
            height: Number(selectedFile.value.height),
        }
    }

    return {
        width: Math.max(1, Math.round(Number(selectedFile.value.width) * transformForm.crop_width)),
        height: Math.max(1, Math.round(Number(selectedFile.value.height) * transformForm.crop_height)),
    }
})
const transformAspectRatio = computed(() => {
    const width = Number(transformSourceDimensions.value.width || 0)
    const height = Number(transformSourceDimensions.value.height || 0)

    if (!width || !height) {
        return null
    }

    return width / height
})
const transformOutputDimensions = computed(() => {
    const sourceWidth = Number(transformSourceDimensions.value.width || 0)
    const sourceHeight = Number(transformSourceDimensions.value.height || 0)
    const resizeWidth = Number(transformForm.resize_width || 0)
    const resizeHeight = Number(transformForm.resize_height || 0)

    if (!sourceWidth || !sourceHeight) {
        return { width: null, height: null }
    }

    if (!resizeWidth && !resizeHeight) {
        return { width: sourceWidth, height: sourceHeight }
    }

    if (transformForm.maintain_aspect_ratio) {
        if (resizeWidth && !resizeHeight) {
            return { width: resizeWidth, height: Math.max(1, Math.round(resizeWidth / transformAspectRatio.value)) }
        }

        if (!resizeWidth && resizeHeight) {
            return { width: Math.max(1, Math.round(resizeHeight * transformAspectRatio.value)), height: resizeHeight }
        }

        const ratio = Math.min(resizeWidth / sourceWidth, resizeHeight / sourceHeight)

        return {
            width: Math.max(1, Math.round(sourceWidth * ratio)),
            height: Math.max(1, Math.round(sourceHeight * ratio)),
        }
    }

    return {
        width: resizeWidth || sourceWidth,
        height: resizeHeight || sourceHeight,
    }
})
const transformCropStyle = computed(() => ({
    left: `${transformForm.crop_x * 100}%`,
    top: `${transformForm.crop_y * 100}%`,
    width: `${transformForm.crop_width * 100}%`,
    height: `${transformForm.crop_height * 100}%`,
}))
const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase())
const hasActiveAdvancedFilters = computed(() => dateFilter.value !== 'all' || sizeFilter.value !== 'all' || folderScope.value !== 'current')
const visibleFolders = computed(() => {
    if (normalizedSearch.value === '') {
        return folders.value
    }

    return folders.value.filter((folder) => [folder.name, folder.slug, folder.path]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch.value)))
})

const typeOptions = computed(() => {
    const map = new Map([['all', 'Все типы']])

    files.value.forEach((file) => {
        const key = resolveTypeOptionKey(file)

        if (!map.has(key)) {
            map.set(key, resolveTypeOptionLabel(file))
        }
    })

    return Array.from(map.entries()).map(([value, label]) => ({ value, label }))
})

const filteredFiles = computed(() => {
    let list = [...files.value]

    list = list.filter((file) => matchesTab(file, activeTab.value))

    if (typeFilter.value !== 'all') {
        list = list.filter((file) => resolveTypeOptionKey(file) === typeFilter.value)
    }

    if (normalizedSearch.value !== '') {
        list = list.filter((file) => [
            file.original_name,
            file.alt_text,
            file.folder_name,
            file.mime_type,
            file.path,
            file.extension,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(normalizedSearch.value)))
    }

    if (dateFilter.value !== 'all') {
        list = list.filter((file) => matchesDateFilter(file, dateFilter.value))
    }

    if (sizeFilter.value !== 'all') {
        list = list.filter((file) => matchesSizeFilter(file, sizeFilter.value))
    }

    return sortFiles(list, sortBy.value)
})

watch(() => folderForm.name, (value) => {
    if (!folderModalOpen.value || folderForm.slugTouched) {
        return
    }

    folderForm.slug = slugify(value)
})

watch(selectedFile, (file) => {
    syncSelectedFileForm(file)

    if (file) {
        resetTransformForm(file)
    }

    transformTool.value = ''
    stopTransformDrag()
}, { immediate: true })

watch(viewMode, (value) => storePreference(storageKeys.viewMode, value))
watch(sortBy, (value) => storePreference(storageKeys.sortBy, value))
watch(folderScope, async (value) => {
    storePreference(storageKeys.folderScope, value)
    await loadLibrary(currentFolderId.value)
})

function readStoredPreference(key, fallback) {
    if (typeof window === 'undefined') {
        return fallback
    }

    return window.localStorage.getItem(key) || fallback
}

function storePreference(key, value) {
    if (typeof window === 'undefined') {
        return
    }

    window.localStorage.setItem(key, value)
}

function syncSelectedFileForm(file) {
    selectedFileForm.original_name = stripExtension(file?.original_name || file?.filename || '')
    selectedFileForm.alt_text = file?.alt_text ?? ''
    selectedFileForm.folder_id = file?.folder_id ?? ''
}

async function loadLibrary(folderId = null) {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchMediaLibrary(folderId, {
            scope: folderScope.value,
        })

        currentFolder.value = payload.data?.current_folder ?? null
        breadcrumbs.value = payload.data?.breadcrumbs ?? []
        folders.value = payload.data?.folders ?? []
        folderOptions.value = payload.data?.folder_options ?? []
        files.value = (payload.data?.files ?? []).map((file) => createMediaSelection(file))

        if (selectedFileId.value !== null && !files.value.some((file) => file.id === selectedFileId.value)) {
            selectedFileId.value = null
        }
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить медиатеку.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

function replaceLoadedFile(file) {
    if (!file?.id) {
        return
    }

    files.value = files.value.map((entry) => entry.id === file.id ? createMediaSelection(file) : entry)
}

function openRoot() {
    closeMenus()
    loadLibrary(null)
}

function openFolder(folder) {
    closeMenus()
    loadLibrary(folder.id)
}

function openCreateFolderModal() {
    folderModalMode.value = 'create'
    folderErrors.value = {}
    folderForm.id = null
    folderForm.name = ''
    folderForm.slug = ''
    folderForm.parent_id = currentFolderId.value ?? ''
    folderForm.slugTouched = false
    folderModalOpen.value = true
}

function openFolderEditModal(folder, mode = 'rename') {
    folderModalMode.value = mode
    folderErrors.value = {}
    folderForm.id = folder.id
    folderForm.name = folder.name
    folderForm.slug = folder.slug ?? ''
    folderForm.parent_id = folder.parent_id ?? ''
    folderForm.slugTouched = false
    folderModalOpen.value = true
    activeFolderMenuId.value = null
}

function closeFolderModal() {
    if (savingFolder.value) {
        return
    }

    folderModalOpen.value = false
}

async function submitFolderModal() {
    savingFolder.value = true
    folderErrors.value = {}

    const payload = {
        name: folderForm.name,
        slug: folderForm.slug || null,
        parent_id: folderForm.parent_id === '' ? null : Number(folderForm.parent_id),
    }

    try {
        if (folderModalMode.value === 'create') {
            await createMediaFolder(payload)
            pushToast('Папка создана.')
        } else {
            await updateMediaFolder(folderForm.id, payload)
            pushToast(folderModalMode.value === 'move' ? 'Папка перемещена.' : 'Папка обновлена.')
        }

        folderModalOpen.value = false
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        if (error.response?.status === 422) {
            folderErrors.value = error.response.data.errors ?? {}
        } else {
            pushToast(error.response?.data?.message ?? 'Не удалось сохранить папку.', 'error')
        }

        console.error(error)
    } finally {
        savingFolder.value = false
    }
}

async function deleteFolder(folder) {
    const confirmed = window.confirm(`Удалить папку "${folder.name}"?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFolder(folder.id)
        pushToast('Папка удалена.')
        await loadLibrary(currentFolderId.value)
    } catch (error) {
        pushToast(error.response?.data?.message ?? 'Не удалось удалить папку.', 'error')
        console.error(error)
    }
}

function openFileDetails(file) {
    selectedFileId.value = file.id
}

function closeFileDetails() {
    if (transforming.value) {
        return
    }

    selectedFileId.value = null
    transformTool.value = ''
    stopTransformDrag()
}

function openTransformModal(file) {
    if (!isTransformableImage(file)) {
        pushToast('Этот файл нельзя кадрировать или конвертировать в редакторе.', 'warning')
        return
    }

    resetTransformForm(file)
    selectedFileId.value = file.id
    transformTool.value = 'crop'
}

function closeTransformModal() {
    if (transforming.value) {
        return
    }

    transformTool.value = ''
    stopTransformDrag()
}

function toggleTransformTool(tool) {
    if (!canTransformSelectedFile.value) {
        return
    }

    if (transformTool.value === tool) {
        closeTransformModal()
        return
    }

    transformTool.value = tool
}

async function saveFileDetails() {
    if (!selectedFile.value) {
        return
    }

    savingDetails.value = true

    try {
        const currentFile = selectedFile.value
        const nextFolderId = selectedFileForm.folder_id === '' ? null : Number(selectedFileForm.folder_id)

        await updateMediaFile(currentFile.id, {
            original_name: buildFilename(selectedFileForm.original_name, currentFile.extension),
            alt_text: selectedFileForm.alt_text,
        })

        if ((currentFile.folder_id ?? null) !== nextFolderId) {
            await moveMediaFile(currentFile.id, {
                folder_id: nextFolderId,
            })
        }

        await loadLibrary(currentFolderId.value)
        pushToast('Данные файла обновлены.')
    } catch (error) {
        pushToast(error.response?.data?.message ?? 'Не удалось обновить файл.', 'error')
        console.error(error)
    } finally {
        savingDetails.value = false
    }
}

async function deleteFile(file) {
    const confirmed = window.confirm(`Удалить файл "${file.original_name}"?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteMediaFile(file.id)

        if (selectedFileId.value === file.id) {
            selectedFileId.value = null
        }

        await loadLibrary(currentFolderId.value)
        pushToast('Файл удалён.')
    } catch (error) {
        pushToast(error.response?.data?.message ?? 'Не удалось удалить файл.', 'error')
        console.error(error)
    }
}

async function copyUrl(file) {
    try {
        await navigator.clipboard.writeText(file.url)
        pushToast('URL скопирован.')
    } catch (error) {
        pushToast('Не удалось скопировать URL.', 'error')
        console.error(error)
    }
}

function downloadFile(file) {
    window.open(file.url, '_blank', 'noopener')
}

function resetTransformForm(file) {
    transformForm.crop_enabled = true
    transformForm.crop_x = 0.05
    transformForm.crop_y = 0.05
    transformForm.crop_width = 0.9
    transformForm.crop_height = 0.9
    transformForm.maintain_aspect_ratio = true
    transformForm.quality = ''
    transformForm.format = 'original'

    if (!file?.width || !file?.height) {
        transformForm.crop_x = 0
        transformForm.crop_y = 0
        transformForm.crop_width = 1
        transformForm.crop_height = 1

        transformForm.resize_width = ''
        transformForm.resize_height = ''

        return
    }

    transformForm.resize_width = Math.max(1, Math.round(Number(file.width) * transformForm.crop_width))
    transformForm.resize_height = Math.max(1, Math.round(Number(file.height) * transformForm.crop_height))
}

function handleResizeWidthInput() {
    if (!transformForm.maintain_aspect_ratio || !transformAspectRatio.value) {
        return
    }

    const width = Number(transformForm.resize_width || 0)

    if (!width) {
        transformForm.resize_height = ''
        return
    }

    transformForm.resize_height = Math.max(1, Math.round(width / transformAspectRatio.value))
}

function handleResizeHeightInput() {
    if (!transformForm.maintain_aspect_ratio || !transformAspectRatio.value) {
        return
    }

    const height = Number(transformForm.resize_height || 0)

    if (!height) {
        transformForm.resize_width = ''
        return
    }

    transformForm.resize_width = Math.max(1, Math.round(height * transformAspectRatio.value))
}

async function applyTransform() {
    if (!selectedFile.value) {
        return
    }

    transforming.value = true

    try {
        const response = await transformMediaFile(selectedFile.value.id, {
            crop_enabled: transformForm.crop_enabled,
            crop_x: normalizeTransformFloat(transformForm.crop_x),
            crop_y: normalizeTransformFloat(transformForm.crop_y),
            crop_width: normalizeTransformFloat(transformForm.crop_width),
            crop_height: normalizeTransformFloat(transformForm.crop_height),
            resize_width: transformForm.resize_width === '' ? null : Number(transformForm.resize_width),
            resize_height: transformForm.resize_height === '' ? null : Number(transformForm.resize_height),
            maintain_aspect_ratio: transformForm.maintain_aspect_ratio,
            quality: transformForm.quality === '' ? null : Number(transformForm.quality),
            format: transformForm.format,
        })

        const transformedFile = withMediaCacheBust(response.data ?? response)

        replaceLoadedFile(transformedFile)
        selectedFileId.value = transformedFile.id

        pushToast('Изображение обработано.')
        resetTransformForm(transformedFile)
        transformTool.value = ''
        stopTransformDrag()
    } catch (error) {
        pushToast(error.response?.data?.message ?? firstTransformValidationError(error) ?? 'Не удалось обработать изображение.', 'error')
        console.error(error)
    } finally {
        transforming.value = false
    }
}

function firstTransformValidationError(error) {
    const validation = error.response?.data?.errors ?? {}

    return Object.values(validation).flat()[0] || null
}

function normalizeTransformFloat(value) {
    return Number(Number(value).toFixed(6))
}

function isTransformableImage(file) {
    if (!file) {
        return false
    }

    const mimeType = String(file.mime_type || '').toLowerCase()

    return mimeType.startsWith('image/') && mimeType !== 'image/svg+xml'
}

function startTransformDrag(mode, event) {
    if (!transformForm.crop_enabled || !transformStageRef.value) {
        return
    }

    transformDragState.active = true
    transformDragState.mode = mode
    transformDragState.startX = event.clientX
    transformDragState.startY = event.clientY
    transformDragState.crop = {
        x: transformForm.crop_x,
        y: transformForm.crop_y,
        width: transformForm.crop_width,
        height: transformForm.crop_height,
    }
}

function handleTransformPointerMove(event) {
    if (!transformDragState.active || !transformStageRef.value) {
        return
    }

    const rect = transformStageRef.value.getBoundingClientRect()

    if (!rect.width || !rect.height) {
        return
    }

    const dx = (event.clientX - transformDragState.startX) / rect.width
    const dy = (event.clientY - transformDragState.startY) / rect.height
    const initial = transformDragState.crop

    if (!initial) {
        return
    }

    const minSize = 0.05

    if (transformDragState.mode === 'move') {
        transformForm.crop_x = clamp(initial.x + dx, 0, 1 - initial.width)
        transformForm.crop_y = clamp(initial.y + dy, 0, 1 - initial.height)
        return
    }

    let left = initial.x
    let top = initial.y
    let right = initial.x + initial.width
    let bottom = initial.y + initial.height

    if (transformDragState.mode.includes('w')) {
        left = clamp(initial.x + dx, 0, right - minSize)
    }

    if (transformDragState.mode.includes('e')) {
        right = clamp(initial.x + initial.width + dx, left + minSize, 1)
    }

    if (transformDragState.mode.includes('n')) {
        top = clamp(initial.y + dy, 0, bottom - minSize)
    }

    if (transformDragState.mode.includes('s')) {
        bottom = clamp(initial.y + initial.height + dy, top + minSize, 1)
    }

    transformForm.crop_x = left
    transformForm.crop_y = top
    transformForm.crop_width = right - left
    transformForm.crop_height = bottom - top
}

function stopTransformDrag() {
    transformDragState.active = false
    transformDragState.mode = ''
    transformDragState.crop = null
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value))
}

function triggerFileDialog() {
    fileInput.value?.click()
}

function queueUploadFiles(nextFiles) {
    if (nextFiles.length === 0) {
        return
    }

    const acceptedFiles = nextFiles.filter((file) => isAcceptedUpload(file, DEFAULT_MEDIA_ACCEPT))

    uploadQueue.value.push(...nextFiles.map((file) => createUploadItem(file)))

    if (!uploading.value && acceptedFiles.length > 0) {
        void uploadQueuedFiles()
    }
}

function createUploadItem(file) {
    const accepted = isAcceptedUpload(file, DEFAULT_MEDIA_ACCEPT)

    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        file,
        previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : '',
        originalName: file.name,
        renameBase: stripExtension(file.name),
        extension: getExtension(file.name),
        mimeType: file.type || 'application/octet-stream',
        size: file.size,
        folderId: currentFolderId.value,
        status: accepted ? 'queued' : 'error',
        progress: 0,
        errorMessage: accepted ? '' : 'Формат пока не поддерживается загрузкой.',
    }
}

function revokePreview(item) {
    if (item.previewUrl) {
        URL.revokeObjectURL(item.previewUrl)
    }
}

function removeUploadItem(itemId) {
    const item = uploadQueue.value.find((entry) => entry.id === itemId)

    if (!item || item.status === 'uploading') {
        return
    }

    revokePreview(item)
    uploadQueue.value = uploadQueue.value.filter((entry) => entry.id !== itemId)
}

function clearUploadQueue() {
    if (uploading.value) {
        return
    }

    uploadQueue.value.forEach(revokePreview)
    uploadQueue.value = []
}

function handleFileInput(event) {
    const nextFiles = Array.from(event.target.files ?? [])

    queueUploadFiles(nextFiles)

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

async function uploadQueuedFiles() {
    if (uploading.value || uploadQueue.value.length === 0) {
        return
    }

    uploading.value = true

    const uploadableItems = uploadQueue.value.filter((item) => item.file && (item.status === 'queued' || item.status === 'error'))

    for (const item of uploadableItems) {
        if (!item.file) {
            continue
        }

        item.status = 'uploading'
        item.progress = 0

        try {
            await uploadMediaFile({
                folderId: item.folderId,
                file: item.file,
                name: item.renameBase,
                onUploadProgress: (event) => {
                    if (!event.total) {
                        return
                    }

                    item.progress = Math.min(100, Math.round((event.loaded / event.total) * 100))
                },
            })

            item.status = 'success'
            item.progress = 100
            item.errorMessage = ''
        } catch (error) {
            item.status = 'error'
            item.progress = 0
            item.errorMessage = extractUploadError(error)
            console.error(error)
        }
    }

    uploading.value = false
    await loadLibrary(currentFolderId.value)

    if (uploadQueue.value.every((item) => item.status === 'success')) {
        pushToast('Файлы загружены.')
        return
    }

    if (uploadQueue.value.some((item) => item.status === 'queued')) {
        void uploadQueuedFiles()
    }

    pushToast('Очередь обработки завершена.', uploadQueue.value.some((item) => item.status === 'error') ? 'warning' : 'success')
}

function extractUploadError(error) {
    if (error.response?.status === 413) {
        return 'Файл превышает лимит сервера. Увеличьте upload_max_filesize/post_max_size или загрузите файл меньшего размера.'
    }

    const validation = error.response?.data?.errors ?? {}
    const firstValidationError = Object.values(validation).flat()[0]

    if (firstValidationError) {
        return firstValidationError
    }

    if (error.message === 'Network Error') {
        return 'Не удалось загрузить файл. Проверьте лимит сервера upload_max_filesize/post_max_size и права на запись.'
    }

    return error.response?.data?.message || 'Не удалось загрузить файл.'
}

function openReplaceDialog(file) {
    pendingReplaceFileId.value = file.id
    replaceInput.value?.click()
}

async function handleReplaceInput(event) {
    const file = Array.from(event.target.files ?? [])[0] ?? null

    if (!file || pendingReplaceFileId.value === null) {
        return
    }

    try {
        await replaceMediaFile(pendingReplaceFileId.value, { file })
        await loadLibrary(currentFolderId.value)
        pushToast('Файл заменён.')
    } catch (error) {
        pushToast(error.response?.data?.message ?? 'Не удалось заменить файл.', 'error')
        console.error(error)
    } finally {
        pendingReplaceFileId.value = null

        if (replaceInput.value) {
            replaceInput.value.value = ''
        }
    }
}

function pushToast(message, tone = 'success') {
    notify(message, tone)
}

function closeMenus() {
    activeFolderMenuId.value = null
}

function toggleFolderMenu(folderId) {
    activeFolderMenuId.value = activeFolderMenuId.value === folderId ? null : folderId
}

function slugify(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .replace(/^-+|-+$/g, '')
}

function markSlugTouched() {
    folderForm.slugTouched = true
}

function handleDocumentClick() {
    closeMenus()
}

function matchesTab(file, tab) {
    if (tab === 'all') {
        return true
    }

    return resolveFileKind(file) === tab
}

function resolveFileKind(file) {
    const extension = String(file.extension || getExtension(file.original_name)).toLowerCase()
    const mimeType = String(file.mime_type || '').toLowerCase()

    if (mimeType.startsWith('image/')) {
        return 'images'
    }

    if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv'].includes(extension)) {
        return 'documents'
    }

    if (['application/pdf'].includes(mimeType)) {
        return 'documents'
    }

    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(extension)) {
        return 'archives'
    }

    return 'other'
}

function resolveTypeOptionKey(file) {
    return String(file.mime_type || file.extension || 'other').toLowerCase()
}

function resolveTypeOptionLabel(file) {
    const extension = String(file.extension || '').toUpperCase()

    if (extension !== '') {
        return extension
    }

    if (file.mime_type) {
        return file.mime_type
    }

    return 'Другое'
}

function matchesDateFilter(file, filter) {
    const timestamp = file.created_at ? new Date(file.created_at).getTime() : null

    if (!timestamp) {
        return false
    }

    const now = Date.now()
    const day = 24 * 60 * 60 * 1000

    if (filter === 'today') {
        return now - timestamp <= day
    }

    if (filter === 'week') {
        return now - timestamp <= day * 7
    }

    if (filter === 'month') {
        return now - timestamp <= day * 31
    }

    return true
}

function matchesSizeFilter(file, filter) {
    const size = Number(file.size || 0)

    if (filter === 'small') {
        return size > 0 && size < 512 * 1024
    }

    if (filter === 'medium') {
        return size >= 512 * 1024 && size <= 2 * 1024 * 1024
    }

    if (filter === 'large') {
        return size > 2 * 1024 * 1024
    }

    return true
}

function sortFiles(list, mode) {
    return list.sort((left, right) => {
        if (mode === 'name_asc') {
            return left.original_name.localeCompare(right.original_name, 'ru')
        }

        if (mode === 'name_desc') {
            return right.original_name.localeCompare(left.original_name, 'ru')
        }

        if (mode === 'size_desc') {
            return Number(right.size || 0) - Number(left.size || 0)
        }

        if (mode === 'size_asc') {
            return Number(left.size || 0) - Number(right.size || 0)
        }

        if (mode === 'date_asc') {
            return new Date(left.created_at || 0).getTime() - new Date(right.created_at || 0).getTime()
        }

        return new Date(right.created_at || 0).getTime() - new Date(left.created_at || 0).getTime()
    })
}

function resolveUploadStatusLabel(status) {
    const labels = {
        queued: 'Ожидает',
        uploading: 'Загружается',
        success: 'Готово',
        error: 'Ошибка',
    }

    return labels[status] ?? 'Ожидает'
}

function formatFileDate(value) {
    return value ? formatCmsDateTime(value) : 'Без даты'
}

function filePreviewLabel(file) {
    if (resolveFileKind(file) === 'images') {
        return 'IMG'
    }

    return String(file.extension || 'FILE').toUpperCase()
}

function hasImagePreview(file) {
    return resolveFileKind(file) === 'images' && Boolean(file.preview_url || file.url)
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

    queueUploadFiles(droppedFiles)
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
    window.addEventListener('pointermove', handleTransformPointerMove)
    window.addEventListener('pointerup', stopTransformDrag)
    document.addEventListener('click', handleDocumentClick)
})

onBeforeUnmount(() => {
    uploadQueue.value.forEach(revokePreview)
    window.removeEventListener('dragenter', handleWindowDragEnter)
    window.removeEventListener('dragover', handleWindowDragOver)
    window.removeEventListener('dragleave', handleWindowDragLeave)
    window.removeEventListener('drop', handleWindowDrop)
    window.removeEventListener('pointermove', handleTransformPointerMove)
    window.removeEventListener('pointerup', stopTransformDrag)
    document.removeEventListener('click', handleDocumentClick)
})
</script>

<template>
    <AdminPage eyebrow="Media" title="Медиатека" description="Управление файлами и папками">
        <input ref="fileInput" type="file" hidden multiple :accept="DEFAULT_MEDIA_ACCEPT" @change="handleFileInput">
        <input ref="replaceInput" type="file" hidden :accept="DEFAULT_MEDIA_ACCEPT" @change="handleReplaceInput">

        <div v-if="dragOverlayVisible" class="media-library-page__drag-overlay">
            <div class="media-library-page__drag-card">
                <strong>Отпустите файлы, чтобы загрузить</strong>
                <span>Файлы сразу добавятся в очередь и начнут загружаться на этой странице.</span>
            </div>
        </div>

        <section class="panel-card media-library-page">
            <div class="media-library-page__workspace" :class="{ 'has-sidebar': selectedFile }">
                <div class="media-library-page__content">
                    <header class="media-library-page__hero">
                        <div>
                            <h2>Медиатека</h2>
                            <p>Управление файлами и папками</p>
                        </div>

                        <div class="media-library-page__hero-actions">
                            <AdminButton type="button" variant="primary" @click.stop="triggerFileDialog">
                                Добавить файл
                            </AdminButton>
                            <button type="button" class="button-link" @click.stop="openCreateFolderModal">
                                Создать папку
                            </button>
                        </div>
                    </header>

                    <div class="admin-tabs media-library-page__tabs">
                        <button
                            v-for="tab in fileTypeTabs"
                            :key="tab.value"
                            type="button"
                            class="admin-tab"
                            :class="{ 'is-active': activeTab === tab.value }"
                            @click="activeTab = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="media-library-page__toolbar">
                        <label class="admin-form-label media-library-page__search">
                            <span>Поиск</span>
                            <input v-model="searchQuery" class="admin-input" type="search" placeholder="Поиск файлов...">
                        </label>

                        <label class="admin-form-label media-library-page__select">
                            <span>Тип</span>
                            <select v-model="typeFilter" class="admin-select">
                                <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>

                        <div class="media-library-page__toolbar-actions">
                            <button type="button" class="button-link" :class="{ 'is-active': filtersOpen || hasActiveAdvancedFilters }" @click.stop="filtersOpen = !filtersOpen">
                                Фильтры
                            </button>

                            <label class="admin-form-label media-library-page__select">
                                <span>Сортировка</span>
                                <select v-model="sortBy" class="admin-select">
                                    <option value="date_desc">Сначала новые</option>
                                    <option value="date_asc">Сначала старые</option>
                                    <option value="name_asc">По названию A-Z</option>
                                    <option value="name_desc">По названию Z-A</option>
                                    <option value="size_desc">Большие сверху</option>
                                    <option value="size_asc">Маленькие сверху</option>
                                </select>
                            </label>

                            <div class="media-library-page__view-switch">
                                <button type="button" class="media-library-page__icon-button" :class="{ 'is-active': viewMode === 'grid' }" @click.stop="viewMode = 'grid'" aria-label="Сетка">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z" fill="currentColor"/></svg>
                                </button>
                                <button type="button" class="media-library-page__icon-button" :class="{ 'is-active': viewMode === 'list' }" @click.stop="viewMode = 'list'" aria-label="Список">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h2v2H5V6Zm4 0h10v2H9V6Zm-4 5h2v2H5v-2Zm4 0h10v2H9v-2Zm-4 5h2v2H5v-2Zm4 0h10v2H9v-2Z" fill="currentColor"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="filtersOpen || hasActiveAdvancedFilters" class="media-library-page__filters-panel">
                        <label class="admin-form-label">
                            <span>Период</span>
                            <select v-model="dateFilter" class="admin-select">
                                <option value="all">Все даты</option>
                                <option value="today">За сегодня</option>
                                <option value="week">За неделю</option>
                                <option value="month">За месяц</option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Размер</span>
                            <select v-model="sizeFilter" class="admin-select">
                                <option value="all">Любой размер</option>
                                <option value="small">До 512 KB</option>
                                <option value="medium">512 KB - 2 MB</option>
                                <option value="large">Больше 2 MB</option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Охват папок</span>
                            <select v-model="folderScope" class="admin-select">
                                <option value="current">Текущая папка</option>
                                <option value="all">Все папки</option>
                            </select>
                        </label>
                    </div>

                    <div class="media-library-page__breadcrumbs-row">
                        <div class="media-library-page__breadcrumbs">
                            <button type="button" class="media-library-page__home-button" @click.stop="openRoot" aria-label="Корень медиатеки">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor"/></svg>
                            </button>

                            <template v-for="folder in breadcrumbs" :key="folder.id">
                                <span>/</span>
                                <button type="button" class="button-link media-library-page__crumb" @click.stop="openFolder(folder)">
                                    {{ folder.name }}
                                </button>
                            </template>
                        </div>

                        <span class="media-library-page__results">{{ filteredFiles.length }} файлов</span>
                    </div>

                    <p v-if="errorMessage" class="error-text media-library-page__error">{{ errorMessage }}</p>

                    <section v-if="uploadQueue.length > 0" class="media-library-page__section">
                        <div class="media-library-page__section-head">
                            <div>
                                <h3>Очередь загрузки</h3>
                                <p>Файлы загружаются сразу после добавления. Здесь можно следить за прогрессом и ошибками.</p>
                            </div>

                            <div class="admin-actions-row media-library-page__upload-actions">
                                <button type="button" class="button-link" :disabled="uploading" @click="clearUploadQueue">
                                    Очистить очередь
                                </button>
                            </div>
                        </div>

                        <div class="media-library-page__upload-body">
                            <div class="media-library-page__upload-summary">
                                <strong>{{ uploadStats.total }} файлов</strong>
                                <span>Ожидает: {{ uploadStats.queued }} · Загружается: {{ uploadStats.uploading }} · Готово: {{ uploadStats.success }} · Ошибки: {{ uploadStats.error }}</span>
                            </div>

                            <div class="media-library-page__progress"><span :style="{ width: `${uploadProgress}%` }"></span></div>

                            <div class="media-library-page__upload-list">
                                <article v-for="item in uploadQueue" :key="item.id" class="media-library-page__upload-item" :class="`is-${item.status}`">
                                    <div class="media-library-page__upload-preview">
                                        <img v-if="item.previewUrl" :src="item.previewUrl" :alt="item.originalName">
                                        <span v-else>{{ item.extension ? item.extension.toUpperCase() : 'FILE' }}</span>
                                        <div v-if="item.status === 'uploading'" class="media-library-page__upload-preview-overlay">
                                            <span class="media-library-page__upload-spinner"></span>
                                        </div>
                                    </div>

                                    <div class="media-library-page__upload-main">
                                        <div class="media-library-page__upload-head">
                                            <div>
                                                <strong>{{ item.originalName }}</strong>
                                                <span>{{ formatBytes(item.size) }} · {{ item.mimeType }}</span>
                                            </div>
                                            <span class="media-library-page__status-pill" :class="`is-${item.status}`">{{ resolveUploadStatusLabel(item.status) }}</span>
                                        </div>

                                        <div class="media-library-page__upload-fields">
                                            <label class="admin-form-label">
                                                <span>Имя файла</span>
                                                <input v-model="item.renameBase" class="admin-input" type="text" :disabled="item.status === 'uploading' || item.status === 'success'">
                                            </label>

                                            <label class="admin-form-label">
                                                <span>Папка назначения</span>
                                                <select v-model="item.folderId" class="admin-select" :disabled="item.status === 'uploading' || item.status === 'success'">
                                                    <option v-for="option in moveFolderOptions" :key="option.id ?? 'root'" :value="option.id ?? null">
                                                        {{ option.path || option.name }}
                                                    </option>
                                                </select>
                                            </label>
                                        </div>

                                        <div class="media-library-page__upload-progress-row">
                                            <div class="media-library-page__progress"><span :style="{ width: `${item.progress}%` }"></span></div>
                                            <span>{{ item.progress }}%</span>
                                        </div>

                                        <p v-if="item.errorMessage" class="error-text">{{ item.errorMessage }}</p>
                                    </div>

                                    <button type="button" class="button-link media-library-page__danger" :disabled="item.status === 'uploading'" @click="removeUploadItem(item.id)">
                                        Удалить
                                    </button>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section class="media-library-page__section">
                        <div class="media-library-page__section-head">
                            <div>
                                <h3>Папки</h3>
                                <p>Быстрый переход по структуре медиатеки.</p>
                            </div>
                        </div>

                        <div v-if="loading" class="media-library-page__folder-grid is-loading">
                            <div v-for="index in 6" :key="index" class="media-library-page__folder-skeleton"></div>
                        </div>

                        <div v-else class="media-library-page__folder-grid">
                            <article v-for="folder in visibleFolders" :key="folder.id" class="media-library-page__folder-card">
                                <button type="button" class="media-library-page__folder-main" @click.stop="openFolder(folder)">
                                    <span class="media-library-page__folder-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 7c0-1.4 0-2.1.272-2.635a2.5 2.5 0 0 1 1.093-1.093C3.9 3 4.6 3 6 3h1.431c.94 0 1.409 0 1.835.13a3 3 0 0 1 1.033.552c.345.283.605.674 1.126 1.455L12 6h6c1.4 0 2.1 0 2.635.272a2.5 2.5 0 0 1 1.092 1.093C22 7.9 22 8.6 22 10v5c0 1.4 0 2.1-.273 2.635a2.5 2.5 0 0 1-1.092 1.092C20.1 19 19.4 19 18 19H6c-1.4 0-2.1 0-2.635-.273a2.5 2.5 0 0 1-1.093-1.092C2 17.1 2 16.4 2 15V7z" fill="currentColor"/></svg>
                                    </span>

                                    <span class="media-library-page__folder-copy">
                                        <strong>{{ folder.name }}</strong>
                                        <small>{{ folder.files_count || 0 }} файлов · {{ folder.children_count || 0 }} вложенных папок</small>
                                    </span>
                                </button>

                                <div class="media-library-page__menu-wrap">
                                    <button type="button" class="media-library-page__menu-button" @click.stop="toggleFolderMenu(folder.id)">...</button>

                                    <div v-if="activeFolderMenuId === folder.id" class="media-library-page__menu" @click.stop>
                                        <button type="button" @click.stop="openFolder(folder)">Открыть</button>
                                        <button type="button" @click.stop="openFolderEditModal(folder, 'rename')">Переименовать</button>
                                        <button type="button" @click.stop="openFolderEditModal(folder, 'move')">Переместить</button>
                                        <button type="button" class="is-danger" @click.stop="deleteFolder(folder)">Удалить</button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div v-if="!loading && visibleFolders.length === 0" class="media-library-page__empty-state">
                            <h4>В текущем разделе пока нет папок</h4>
                            <p>Создайте первую папку, чтобы разложить файлы по структуре.</p>
                        </div>
                    </section>

                    <section class="media-library-page__section">
                        <div class="media-library-page__section-head">
                            <div>
                                <h3>Файлы</h3>
                                <p>Клик по карточке открывает боковую панель деталей справа.</p>
                            </div>
                        </div>

                        <div v-if="loading" class="media-library-page__file-grid is-loading">
                            <div v-for="index in 8" :key="index" class="media-library-page__file-skeleton"></div>
                        </div>

                        <div v-else-if="filteredFiles.length === 0" class="media-library-page__empty-state">
                            <h4>Файлы не найдены</h4>
                            <p>Попробуйте изменить фильтры или загрузить новые материалы.</p>
                        </div>

                        <div v-else>
                            <div v-if="viewMode === 'grid'" class="media-library-page__file-grid">
                                <article
                                    v-for="file in filteredFiles"
                                    :key="file.id"
                                    class="media-library-page__file-card"
                                    :class="{ 'is-selected': selectedFileId === file.id }"
                                    @click="openFileDetails(file)"
                                >
                                    <div class="media-library-page__file-preview">
                                        <img v-if="hasImagePreview(file)" :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
                                        <span v-else class="media-library-page__file-fallback">{{ filePreviewLabel(file) }}</span>
                                    </div>

                                    <div class="media-library-page__file-meta">
                                        <strong>{{ file.original_name }}</strong>
                                        <span>{{ file.size_human }}</span>
                                        <span>{{ formatFileDate(file.created_at) }}</span>
                                    </div>
                                </article>
                            </div>

                            <div v-else class="media-library-page__table-wrap">
                                <table class="data-table media-library-page__table">
                                    <thead>
                                        <tr>
                                            <th>Файл</th>
                                            <th>Тип</th>
                                            <th>Размер</th>
                                            <th>Папка</th>
                                            <th>Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="file in filteredFiles"
                                            :key="file.id"
                                            class="media-library-page__row"
                                            :class="{ 'is-selected': selectedFileId === file.id }"
                                            @click="openFileDetails(file)"
                                        >
                                            <td>
                                                <div class="media-library-page__row-file">
                                                    <div class="media-library-page__row-preview">
                                                        <img v-if="hasImagePreview(file)" :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
                                                        <span v-else>{{ filePreviewLabel(file) }}</span>
                                                    </div>
                                                    <div>
                                                        <strong>{{ file.original_name }}</strong>
                                                        <small>{{ file.path }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ resolveTypeOptionLabel(file) }}</td>
                                            <td>{{ file.size_human }}</td>
                                            <td>{{ file.folder_name || 'Корень' }}</td>
                                            <td>{{ formatFileDate(file.created_at) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

            </div>
        </section>

        <div v-if="selectedFile" class="admin-modal" @click.self="closeFileDetails">
            <div class="admin-modal__dialog admin-modal__dialog--wide media-library-page__file-modal">
                <div class="admin-modal__header">
                    <div>
                        <p class="eyebrow">Файл</p>
                        <h2>{{ selectedFile.original_name }}</h2>
                    </div>

                    <button type="button" class="button-link" :disabled="transforming || savingDetails" @click="closeFileDetails">
                        Закрыть
                    </button>
                </div>

                <div class="admin-modal__body media-library-page__file-modal-body">
                    <div class="media-library-page__editor-stage-panel">
                        <div class="media-library-page__editor-stage-shell">
                            <div ref="transformStageRef" class="media-library-page__editor-stage" :class="{ 'is-editing': transformTool === 'crop' && canTransformSelectedFile }">
                                <img v-if="hasImagePreview(selectedFile)" :src="selectedFile.url" :alt="selectedFile.alt_text || selectedFile.original_name" draggable="false">
                                <div v-else class="media-library-page__file-modal-fallback">{{ filePreviewLabel(selectedFile) }}</div>

                                <div
                                    v-if="transformTool === 'crop' && transformForm.crop_enabled && canTransformSelectedFile"
                                    class="media-library-page__crop-box"
                                    :style="transformCropStyle"
                                    @pointerdown.prevent="startTransformDrag('move', $event)"
                                >
                                    <button type="button" class="media-library-page__crop-handle is-nw" @pointerdown.stop.prevent="startTransformDrag('nw', $event)"></button>
                                    <button type="button" class="media-library-page__crop-handle is-ne" @pointerdown.stop.prevent="startTransformDrag('ne', $event)"></button>
                                    <button type="button" class="media-library-page__crop-handle is-se" @pointerdown.stop.prevent="startTransformDrag('se', $event)"></button>
                                    <button type="button" class="media-library-page__crop-handle is-sw" @pointerdown.stop.prevent="startTransformDrag('sw', $event)"></button>
                                </div>
                            </div>
                        </div>

                        <div v-if="canTransformSelectedFile" class="media-library-page__editor-facts">
                            <div><span>Исходник</span><strong>{{ selectedFile.width && selectedFile.height ? `${selectedFile.width} x ${selectedFile.height}` : '—' }}</strong></div>
                            <div><span>После кадрирования</span><strong>{{ transformSourceDimensions.width && transformSourceDimensions.height ? `${transformSourceDimensions.width} x ${transformSourceDimensions.height}` : '—' }}</strong></div>
                            <div><span>Итог</span><strong>{{ transformOutputDimensions.width && transformOutputDimensions.height ? `${transformOutputDimensions.width} x ${transformOutputDimensions.height}` : 'Без изменения размера' }}</strong></div>
                        </div>

                        <div v-if="canTransformSelectedFile" class="media-library-page__tool-switcher">
                            <button type="button" class="media-library-page__tool-button" :class="{ 'is-active': transformTool === 'crop' }" @click="toggleTransformTool('crop')">Кадрировать</button>
                            <button type="button" class="media-library-page__tool-button" :class="{ 'is-active': transformTool === 'resize' }" @click="toggleTransformTool('resize')">Размер</button>
                            <button type="button" class="media-library-page__tool-button" :class="{ 'is-active': transformTool === 'format' }" @click="toggleTransformTool('format')">Формат</button>
                            <button type="button" class="media-library-page__tool-button" :class="{ 'is-active': transformTool === 'quality' }" @click="toggleTransformTool('quality')">Качество</button>
                        </div>

                        <div v-if="transformTool && canTransformSelectedFile" class="admin-form-stack media-library-page__tool-panel">
                            <p v-if="transformTool === 'crop'" class="muted">Тяните рамку мышкой за область или уголки, чтобы обрезать изображение прямо в этом окне.</p>

                            <label v-if="transformTool === 'crop'" class="admin-form-label">
                                <span><input v-model="transformForm.crop_enabled" type="checkbox"> Включить кадрирование</span>
                            </label>

                            <template v-if="transformTool === 'resize'">
                                <label class="admin-form-label">
                                    <span><input v-model="transformForm.maintain_aspect_ratio" type="checkbox"> Сохранять пропорции</span>
                                </label>

                                <div class="media-library-page__editor-grid">
                                    <label class="admin-form-label">
                                        <span>Ширина</span>
                                        <input v-model.number="transformForm.resize_width" class="admin-input" type="number" min="1" step="1" @input="handleResizeWidthInput">
                                    </label>

                                    <label class="admin-form-label">
                                        <span>Высота</span>
                                        <input v-model.number="transformForm.resize_height" class="admin-input" type="number" min="1" step="1" @input="handleResizeHeightInput">
                                    </label>
                                </div>
                            </template>

                            <label v-if="transformTool === 'format'" class="admin-form-label">
                                <span>Формат</span>
                                <select v-model="transformForm.format" class="admin-select">
                                    <option v-for="option in transformFormatOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>

                            <label v-if="transformTool === 'quality'" class="admin-form-label">
                                <span>Качество</span>
                                <input v-model.number="transformForm.quality" class="admin-input" type="number" min="30" max="100" step="1" placeholder="По умолчанию из настроек">
                            </label>

                            <div class="admin-actions-row media-library-page__editor-actions">
                                <AdminButton type="button" variant="primary" :disabled="transforming" @click="applyTransform">
                                    {{ transforming ? 'Обработка...' : 'Применить изменения' }}
                                </AdminButton>
                                <button type="button" class="button-link" :disabled="transforming" @click="closeTransformModal">Скрыть инструменты</button>
                            </div>
                        </div>

                        <p v-else-if="canTransformSelectedFile" class="muted">Ниже доступны инструменты кадрирования, изменения размера, формата и качества без выхода из этого окна.</p>
                    </div>

                    <div class="media-library-page__file-info">
                        <div class="media-library-page__drawer-facts media-library-page__file-facts">
                            <div><span>Путь</span><strong>{{ selectedFile.path }}</strong></div>
                            <div><span>Размер</span><strong>{{ selectedFile.size_human }}</strong></div>
                            <div><span>MIME</span><strong>{{ selectedFile.mime_type }}</strong></div>
                            <div><span>Размеры</span><strong>{{ selectedFile.width && selectedFile.height ? `${selectedFile.width} x ${selectedFile.height}` : 'Не указаны' }}</strong></div>
                            <div><span>Дата загрузки</span><strong>{{ formatFileDate(selectedFile.created_at) }}</strong></div>
                        </div>

                        <form class="admin-form-stack media-library-page__file-form" @submit.prevent="saveFileDetails">
                            <label class="admin-form-label">
                                <span>Название файла</span>
                                <input v-model="selectedFileForm.original_name" class="admin-input" type="text">
                            </label>

                            <label class="admin-form-label">
                                <span>Alt</span>
                                <input v-model="selectedFileForm.alt_text" class="admin-input" type="text">
                            </label>

                            <label class="admin-form-label">
                                <span>Папка</span>
                                <select v-model="selectedFileForm.folder_id" class="admin-select">
                                    <option v-for="option in moveFolderOptions" :key="option.id ?? 'root'" :value="option.id ?? ''">
                                        {{ option.path || option.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="admin-form-label">
                                <span>URL</span>
                                <input class="admin-input" type="text" :value="selectedFile.url" readonly>
                            </label>

                            <div class="admin-actions-row media-library-page__drawer-actions media-library-page__file-actions">
                                <AdminButton type="submit" variant="primary" :disabled="savingDetails || transforming">
                                    {{ savingDetails ? 'Сохранение...' : 'Сохранить' }}
                                </AdminButton>
                                <button type="button" class="button-link" :disabled="transforming" @click="copyUrl(selectedFile)">Скопировать URL</button>
                                <button type="button" class="button-link" :disabled="transforming" @click="downloadFile(selectedFile)">Скачать</button>
                                <button type="button" class="button-link" :disabled="transforming" @click="openReplaceDialog(selectedFile)">Заменить</button>
                                <button type="button" class="button-link media-library-page__danger" :disabled="transforming" @click="deleteFile(selectedFile)">Удалить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="folderModalOpen" class="admin-modal" @click.self="closeFolderModal">
            <div class="admin-modal__dialog media-library-page__folder-modal">
                <div class="admin-modal__header">
                    <div>
                        <p class="eyebrow">Folder</p>
                        <h2>{{ folderModalTitle }}</h2>
                    </div>

                    <button type="button" class="button-link" :disabled="savingFolder" @click="closeFolderModal">
                        Отмена
                    </button>
                </div>

                <div class="admin-modal__body">
                    <form class="admin-form-stack" @submit.prevent="submitFolderModal">
                        <label class="admin-form-label">
                            <span>Название папки</span>
                            <input v-model="folderForm.name" class="admin-input" type="text">
                            <small v-if="folderErrors.name" class="error-text">{{ folderErrors.name[0] }}</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Родительская папка</span>
                            <select v-model="folderForm.parent_id" class="admin-select">
                                <option v-for="option in moveFolderOptions" :key="option.id ?? 'root'" :value="option.id ?? ''">
                                    {{ option.path || option.name }}
                                </option>
                            </select>
                            <small v-if="folderErrors.parent_id" class="error-text">{{ folderErrors.parent_id[0] }}</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Slug</span>
                            <input v-model="folderForm.slug" class="admin-input" type="text" @input="markSlugTouched">
                            <small v-if="folderErrors.slug" class="error-text">{{ folderErrors.slug[0] }}</small>
                        </label>

                        <div class="admin-actions-row">
                            <AdminButton type="submit" variant="primary" :disabled="savingFolder">
                                {{ savingFolder ? 'Сохранение...' : (folderModalMode === 'create' ? 'Создать' : 'Сохранить') }}
                            </AdminButton>
                            <button type="button" class="button-link" :disabled="savingFolder" @click="closeFolderModal">
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminPage>
</template>
