<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import AdminButton from "../ui/AdminButton.vue";
import MediaFolders from "./MediaFolders.vue";
import MediaGrid from "./MediaGrid.vue";
import MediaSidebar from "./MediaSidebar.vue";
import MediaUploader from "./MediaUploader.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import {
    createMediaFolder,
    deleteMediaFile,
    deleteMediaFolder,
    fetchMediaFile,
    fetchMediaLibrary,
    moveMediaFile,
    transformMediaFile,
    updateMediaFile,
    updateMediaFolder,
    uploadMediaFile,
} from "../../api/media";
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
    withMediaCacheBust,
} from "./mediaHelpers";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "Media Library",
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
});

const emit = defineEmits(["close", "select"]);

const loading = ref(false);
const saving = ref(false);
const uploading = ref(false);
const errorMessage = ref("");
const noticeMessage = ref("");
const createErrors = ref({});
const currentFolder = ref(null);
const breadcrumbs = ref([]);
const folders = ref([]);
const folderOptions = ref([]);
const files = ref([]);
const searchQuery = ref("");
const selectedIdsState = ref([]);
const selectedItemsById = ref({});
const activeFileId = ref(null);
const uploadQueue = ref([]);
const uploadPanelOpen = ref(false);
const dragDepth = ref(0);
const mainPanelRef = ref(null);
const uploadSectionRef = ref(null);
const recentUploadIds = ref([]);
const folderModalOpen = ref(false);
const activeTab = ref("library");
const transformTool = ref("");
const transforming = ref(false);
const transformStageRef = ref(null);
const transformDragState = reactive({
    active: false,
    mode: "",
    startX: 0,
    startY: 0,
    crop: null,
});
const { notify } = useAdminNotifications();
const folderForm = reactive({
    name: "",
    slug: "",
    slugTouched: false,
});
const transformForm = reactive({
    crop_enabled: true,
    crop_x: 0.05,
    crop_y: 0.05,
    crop_width: 0.9,
    crop_height: 0.9,
    resize_width: "",
    resize_height: "",
    maintain_aspect_ratio: true,
    quality: "",
    format: "original",
});

const normalizedSearch = computed(() => searchQuery.value.trim().toLowerCase());
const filteredFiles = computed(() => {
    const baseFiles = files.value;

    if (normalizedSearch.value === "") {
        return baseFiles;
    }

    return baseFiles.filter((file) =>
        [file.original_name, file.alt_text, file.folder_name, file.mime_type]
            .filter(Boolean)
            .some((value) =>
                String(value).toLowerCase().includes(normalizedSearch.value),
            ),
    );
});
const selectedFile = computed(() => {
    const current = files.value.find((file) => file.id === activeFileId.value);

    return current ?? selectedItemsById.value[activeFileId.value] ?? null;
});
const currentFolderId = computed(() => currentFolder.value?.id ?? null);
const moveFolderOptions = computed(() => [
    { id: null, name: "Корень", path: "media" },
    ...folderOptions.value,
]);
const uploadProgress = computed(() => {
    const items = uploadQueue.value.filter((item) => item.file);

    if (items.length === 0) {
        return 0;
    }

    const total = items.reduce((sum, item) => sum + item.progress, 0);

    return Math.round(total / items.length);
});
const hasFiles = computed(() => filteredFiles.value.length > 0);
const dragOverlayVisible = computed(() => dragDepth.value > 0);
const queueCount = computed(() => uploadQueue.value.length);
const canTransformSelectedFile = computed(() =>
    isTransformableImage(selectedFile.value),
);
const transformFormatOptions = computed(() => [
    { value: "original", label: "Оставить текущий формат" },
    { value: "jpg", label: "JPEG" },
    { value: "png", label: "PNG" },
    { value: "webp", label: "WebP" },
]);
const transformSourceDimensions = computed(() => {
    if (!selectedFile.value?.width || !selectedFile.value?.height) {
        return { width: null, height: null };
    }

    if (!transformForm.crop_enabled) {
        return {
            width: Number(selectedFile.value.width),
            height: Number(selectedFile.value.height),
        };
    }

    return {
        width: Math.max(
            1,
            Math.round(
                Number(selectedFile.value.width) * transformForm.crop_width,
            ),
        ),
        height: Math.max(
            1,
            Math.round(
                Number(selectedFile.value.height) * transformForm.crop_height,
            ),
        ),
    };
});
const transformAspectRatio = computed(() => {
    const width = Number(transformSourceDimensions.value.width || 0);
    const height = Number(transformSourceDimensions.value.height || 0);

    if (!width || !height) {
        return null;
    }

    return width / height;
});
const transformOutputDimensions = computed(() => {
    const sourceWidth = Number(transformSourceDimensions.value.width || 0);
    const sourceHeight = Number(transformSourceDimensions.value.height || 0);
    const resizeWidth = Number(transformForm.resize_width || 0);
    const resizeHeight = Number(transformForm.resize_height || 0);

    if (!sourceWidth || !sourceHeight) {
        return { width: null, height: null };
    }

    if (!resizeWidth && !resizeHeight) {
        return { width: sourceWidth, height: sourceHeight };
    }

    if (transformForm.maintain_aspect_ratio) {
        if (resizeWidth && !resizeHeight) {
            return {
                width: resizeWidth,
                height: Math.max(
                    1,
                    Math.round(resizeWidth / transformAspectRatio.value),
                ),
            };
        }

        if (!resizeWidth && resizeHeight) {
            return {
                width: Math.max(
                    1,
                    Math.round(resizeHeight * transformAspectRatio.value),
                ),
                height: resizeHeight,
            };
        }

        const ratio = Math.min(
            resizeWidth / sourceWidth,
            resizeHeight / sourceHeight,
        );

        return {
            width: Math.max(1, Math.round(sourceWidth * ratio)),
            height: Math.max(1, Math.round(sourceHeight * ratio)),
        };
    }

    return {
        width: resizeWidth || sourceWidth,
        height: resizeHeight || sourceHeight,
    };
});
const transformCropStyle = computed(() => ({
    left: `${transformForm.crop_x * 100}%`,
    top: `${transformForm.crop_y * 100}%`,
    width: `${transformForm.crop_width * 100}%`,
    height: `${transformForm.crop_height * 100}%`,
}));

watch(
    () => folderForm.name,
    (value) => {
        if (!folderModalOpen.value || folderForm.slugTouched) {
            return;
        }

        folderForm.slug = slugify(value);
    },
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            activeTab.value = "library";
            transformTool.value = "";
            stopTransformDrag();
            return;
        }

        await initializeModal();
    },
);

watch(
    selectedFile,
    (file) => {
        if (file) {
            resetTransformForm(file);
        }

        if (!file || !isTransformableImage(file)) {
            activeTab.value = "library";
            transformTool.value = "";
        }

        stopTransformDrag();
    },
    { immediate: true },
);

watch(
    () => props.selectedIds,
    (value) => {
        if (!props.open) {
            syncSelectedIds(value);
        }
    },
    { deep: true },
);

async function initializeModal() {
    searchQuery.value = "";
    errorMessage.value = "";
    noticeMessage.value = "";
    uploadPanelOpen.value = false;
    dragDepth.value = 0;
    recentUploadIds.value = [];
    activeTab.value = "library";
    transformTool.value = "";
    syncSelectedIds(props.selectedIds);

    const initialFolderId = await resolveInitialFolderId();
    await loadLibrary(initialFolderId);
}

function syncSelectedIds(value) {
    const ids = normalizeToArray(value)
        .map((item) => toNumericId(item))
        .filter((item) => item !== null);

    selectedIdsState.value = Array.from(new Set(ids));
    activeFileId.value = selectedIdsState.value[0] ?? null;
}

async function resolveInitialFolderId() {
    const selectedId = selectedIdsState.value[0] ?? null;

    if (selectedId === null) {
        return toNumericId(props.folder);
    }

    try {
        const payload = await fetchMediaFile(selectedId);
        const file = createMediaSelection(payload.data ?? payload);

        rememberFile(file);
        activeFileId.value = file.id;

        return file.folder_id ?? toNumericId(props.folder);
    } catch (error) {
        console.error(error);
        return toNumericId(props.folder);
    }
}

async function loadLibrary(folderId = null) {
    loading.value = true;
    errorMessage.value = "";

    try {
        const payload = await fetchMediaLibrary(folderId);
        currentFolder.value = payload.data?.current_folder ?? null;
        breadcrumbs.value = payload.data?.breadcrumbs ?? [];
        folders.value = payload.data?.folders ?? [];
        folderOptions.value = payload.data?.folder_options ?? [];
        files.value = (payload.data?.files ?? []).map((file) =>
            createMediaSelection(file),
        );

        files.value.forEach(rememberFile);
    } catch (error) {
        errorMessage.value = "Не удалось загрузить медиатеку.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

function rememberFile(file) {
    if (!file?.id) {
        return;
    }

    selectedItemsById.value = {
        ...selectedItemsById.value,
        [file.id]: createMediaSelection(file),
    };
}

function showNotice(message, tone = "success") {
    noticeMessage.value = message;
    notify(message, tone);
}

function replaceLoadedFile(file) {
    if (!file?.id) {
        return;
    }

    files.value = files.value.map((entry) =>
        entry.id === file.id ? createMediaSelection(file) : entry,
    );
}

function openRoot() {
    loadLibrary(null);
}

function openFolder(folder) {
    loadLibrary(folder.id);
}

async function createFolder(name) {
    createErrors.value = {};
    saving.value = true;

    try {
        await createMediaFolder({
            name,
            parent_id: currentFolderId.value,
        });

        showNotice("Папка создана.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        if (error.response?.status === 422) {
            createErrors.value = error.response.data.errors ?? {};
        } else {
            errorMessage.value =
                error.response?.data?.message ?? "Не удалось создать папку.";
        }

        console.error(error);
    } finally {
        saving.value = false;
    }
}

function openCreateFolderModal() {
    createErrors.value = {};
    folderForm.name = "";
    folderForm.slug = "";
    folderForm.slugTouched = false;
    folderModalOpen.value = true;
}

function closeCreateFolderModal() {
    if (saving.value) {
        return;
    }

    folderModalOpen.value = false;
}

async function submitCreateFolderModal() {
    const name = folderForm.name.trim();

    if (name === "") {
        return;
    }

    createErrors.value = {};
    saving.value = true;

    try {
        await createMediaFolder({
            name,
            slug: folderForm.slug || null,
            parent_id: currentFolderId.value,
        });

        folderModalOpen.value = false;
        showNotice("Папка создана.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        if (error.response?.status === 422) {
            createErrors.value = error.response.data.errors ?? {};
        } else {
            errorMessage.value =
                error.response?.data?.message ?? "Не удалось создать папку.";
        }

        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function renameFolder({ folder, name, parent_id }) {
    saving.value = true;
    errorMessage.value = "";

    try {
        await updateMediaFolder(folder.id, {
            name,
            parent_id,
        });

        showNotice("Папка обновлена.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось обновить папку.";
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function removeFolder(folder) {
    const confirmed = window.confirm(
        `Удалить папку "${folder.name}"? Только если она пуста.`,
    );

    if (!confirmed) {
        return;
    }

    try {
        await deleteMediaFolder(folder.id);
        showNotice("Папка удалена.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось удалить папку.";
        console.error(error);
    }
}

function handleFileClick(file) {
    rememberFile(file);
    activeFileId.value = file.id;

    if (!props.multiple) {
        selectedIdsState.value = [file.id];
        return;
    }

    const selected = new Set(selectedIdsState.value);

    if (selected.has(file.id)) {
        selected.delete(file.id);
    } else {
        selected.add(file.id);
    }

    selectedIdsState.value = Array.from(selected);
}

function openEditorTab() {
    if (!canTransformSelectedFile.value) {
        return;
    }

    activeTab.value = "edit";

    if (!transformTool.value) {
        transformTool.value = "crop";
    }
}

function setActiveTab(tab) {
    if (tab === "edit" && !canTransformSelectedFile.value) {
        return;
    }

    activeTab.value = tab;

    if (tab !== "edit") {
        transformTool.value = "";
        stopTransformDrag();
        return;
    }

    if (!transformTool.value) {
        transformTool.value = "crop";
    }
}

async function saveFileMeta(payload) {
    if (!selectedFile.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = "";

    try {
        await updateMediaFile(selectedFile.value.id, {
            original_name: buildFilename(
                payload.original_name ||
                    stripExtension(selectedFile.value.original_name),
                selectedFile.value.extension,
            ),
            alt_text: payload.alt_text,
        });

        showNotice("Данные изображения обновлены.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            "Не удалось обновить данные изображения.";
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function moveSelectedFile({ file, folder_id }) {
    errorMessage.value = "";

    try {
        await moveMediaFile(file.id, { folder_id });
        showNotice("Файл перемещен.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось переместить файл.";
        console.error(error);
    }
}

async function deleteSelectedFile(file) {
    const confirmed = window.confirm(`Удалить файл "${file.original_name}"?`);

    if (!confirmed) {
        return;
    }

    try {
        await deleteMediaFile(file.id);

        selectedIdsState.value = selectedIdsState.value.filter(
            (id) => id !== file.id,
        );
        activeFileId.value = null;
        delete selectedItemsById.value[file.id];

        showNotice("Файл удален.");
        await loadLibrary(currentFolderId.value);
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось удалить файл.";
        console.error(error);
    }
}

async function copyUrl(file) {
    try {
        await navigator.clipboard.writeText(file.url);
        showNotice("URL скопирован.", "info");
    } catch (error) {
        errorMessage.value = "Не удалось скопировать URL.";
        console.error(error);
    }
}

function resetTransformForm(file) {
    transformForm.crop_enabled = true;
    transformForm.crop_x = 0.05;
    transformForm.crop_y = 0.05;
    transformForm.crop_width = 0.9;
    transformForm.crop_height = 0.9;
    transformForm.maintain_aspect_ratio = true;
    transformForm.quality = "";
    transformForm.format = "original";

    if (!file?.width || !file?.height) {
        transformForm.crop_x = 0;
        transformForm.crop_y = 0;
        transformForm.crop_width = 1;
        transformForm.crop_height = 1;

        transformForm.resize_width = "";
        transformForm.resize_height = "";

        return;
    }

    transformForm.resize_width = Math.max(
        1,
        Math.round(Number(file.width) * transformForm.crop_width),
    );
    transformForm.resize_height = Math.max(
        1,
        Math.round(Number(file.height) * transformForm.crop_height),
    );
}

function toggleTransformTool(tool) {
    if (!canTransformSelectedFile.value) {
        return;
    }

    transformTool.value = transformTool.value === tool ? "" : tool;
}

function handleResizeWidthInput() {
    if (!transformForm.maintain_aspect_ratio || !transformAspectRatio.value) {
        return;
    }

    const width = Number(transformForm.resize_width || 0);

    if (!width) {
        transformForm.resize_height = "";
        return;
    }

    transformForm.resize_height = Math.max(
        1,
        Math.round(width / transformAspectRatio.value),
    );
}

function handleResizeHeightInput() {
    if (!transformForm.maintain_aspect_ratio || !transformAspectRatio.value) {
        return;
    }

    const height = Number(transformForm.resize_height || 0);

    if (!height) {
        transformForm.resize_width = "";
        return;
    }

    transformForm.resize_width = Math.max(
        1,
        Math.round(height * transformAspectRatio.value),
    );
}

async function applyTransform() {
    if (!selectedFile.value) {
        return;
    }

    transforming.value = true;
    errorMessage.value = "";

    try {
        const response = await transformMediaFile(selectedFile.value.id, {
            crop_enabled: transformForm.crop_enabled,
            crop_x: normalizeTransformFloat(transformForm.crop_x),
            crop_y: normalizeTransformFloat(transformForm.crop_y),
            crop_width: normalizeTransformFloat(transformForm.crop_width),
            crop_height: normalizeTransformFloat(transformForm.crop_height),
            resize_width:
                transformForm.resize_width === ""
                    ? null
                    : Number(transformForm.resize_width),
            resize_height:
                transformForm.resize_height === ""
                    ? null
                    : Number(transformForm.resize_height),
            maintain_aspect_ratio: transformForm.maintain_aspect_ratio,
            quality:
                transformForm.quality === ""
                    ? null
                    : Number(transformForm.quality),
            format: transformForm.format,
        });

        const transformedFile = withMediaCacheBust(response.data ?? response);

        replaceLoadedFile(transformedFile);
        rememberFile(transformedFile);

        showNotice("Изображение обработано.");
        resetTransformForm(transformedFile);
        transformTool.value = "";
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            firstTransformValidationError(error) ??
            "Не удалось обработать изображение.";
        console.error(error);
    } finally {
        transforming.value = false;
        stopTransformDrag();
    }
}

function firstTransformValidationError(error) {
    const validation = error.response?.data?.errors ?? {};

    return Object.values(validation).flat()[0] || null;
}

function normalizeTransformFloat(value) {
    return Number(Number(value).toFixed(6));
}

function isTransformableImage(file) {
    if (!file) {
        return false;
    }

    const mimeType = String(file.mime_type || "").toLowerCase();

    return mimeType.startsWith("image/") && mimeType !== "image/svg+xml";
}

function startTransformDrag(mode, event) {
    if (!transformForm.crop_enabled || !transformStageRef.value) {
        return;
    }

    transformDragState.active = true;
    transformDragState.mode = mode;
    transformDragState.startX = event.clientX;
    transformDragState.startY = event.clientY;
    transformDragState.crop = {
        x: transformForm.crop_x,
        y: transformForm.crop_y,
        width: transformForm.crop_width,
        height: transformForm.crop_height,
    };
}

function handleTransformPointerMove(event) {
    if (!transformDragState.active || !transformStageRef.value) {
        return;
    }

    const rect = transformStageRef.value.getBoundingClientRect();

    if (!rect.width || !rect.height) {
        return;
    }

    const dx = (event.clientX - transformDragState.startX) / rect.width;
    const dy = (event.clientY - transformDragState.startY) / rect.height;
    const initial = transformDragState.crop;

    if (!initial) {
        return;
    }

    const minSize = 0.05;

    if (transformDragState.mode === "move") {
        transformForm.crop_x = clamp(initial.x + dx, 0, 1 - initial.width);
        transformForm.crop_y = clamp(initial.y + dy, 0, 1 - initial.height);
        return;
    }

    let left = initial.x;
    let top = initial.y;
    let right = initial.x + initial.width;
    let bottom = initial.y + initial.height;

    if (transformDragState.mode.includes("w")) {
        left = clamp(initial.x + dx, 0, right - minSize);
    }

    if (transformDragState.mode.includes("e")) {
        right = clamp(initial.x + initial.width + dx, left + minSize, 1);
    }

    if (transformDragState.mode.includes("n")) {
        top = clamp(initial.y + dy, 0, bottom - minSize);
    }

    if (transformDragState.mode.includes("s")) {
        bottom = clamp(initial.y + initial.height + dy, top + minSize, 1);
    }

    transformForm.crop_x = left;
    transformForm.crop_y = top;
    transformForm.crop_width = right - left;
    transformForm.crop_height = bottom - top;
}

function stopTransformDrag() {
    transformDragState.active = false;
    transformDragState.mode = "";
    transformDragState.crop = null;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function queueFiles(nextFiles) {
    const acceptedFiles = nextFiles.filter((file) =>
        isAcceptedUpload(file, props.accept),
    );

    errorMessage.value = "";
    uploadQueue.value.push(...nextFiles.map((file) => createUploadItem(file)));
    uploadPanelOpen.value = true;

    nextTick(() => {
        uploadSectionRef.value?.scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    });

    if (!uploading.value && acceptedFiles.length > 0) {
        void uploadQueuedFiles();
    }
}

function eventHasFiles(event) {
    return Array.from(event.dataTransfer?.types ?? []).includes("Files");
}

function handleMainDragEnter(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return;
    }

    dragDepth.value += 1;
}

function handleMainDragOver(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return;
    }

    event.preventDefault();
}

function handleMainDragLeave(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return;
    }

    dragDepth.value = Math.max(0, dragDepth.value - 1);
}

function handleMainDrop(event) {
    if (!props.allowUpload || !eventHasFiles(event)) {
        return;
    }

    event.preventDefault();
    dragDepth.value = 0;

    const files = Array.from(event.dataTransfer?.files ?? []);

    if (files.length > 0) {
        queueFiles(files);
    }
}

function createUploadItem(file) {
    const extension = getExtension(file.name);
    const accepted = isAcceptedUpload(file, props.accept);

    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        file,
        originalName: file.name,
        renameBase: stripExtension(file.name),
        extension,
        mimeType: file.type || resolveMimeTypeByExtension(extension),
        size: file.size,
        folderId: currentFolderId.value,
        previewUrl: String(file.type || "").startsWith("image/")
            ? URL.createObjectURL(file)
            : "",
        progress: 0,
        status: accepted ? "queued" : "error",
        errorMessage: accepted
            ? ""
            : "Формат не поддерживается текущими правилами accept.",
    };
}

function updateUploadItem({ id, changes }) {
    uploadQueue.value = uploadQueue.value.map((item) =>
        item.id === id ? { ...item, ...changes } : item,
    );
}

function removeUploadItem(id) {
    const item = uploadQueue.value.find((entry) => entry.id === id);

    if (item?.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }

    uploadQueue.value = uploadQueue.value.filter((entry) => entry.id !== id);
}

function clearQueue() {
    uploadQueue.value.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });

    uploadQueue.value = [];
}

async function uploadQueuedFiles() {
    const uploadableItems = uploadQueue.value.filter(
        (item) => item.status === "queued" || item.status === "error",
    );

    if (uploadableItems.length === 0) {
        return;
    }

    uploading.value = true;
    errorMessage.value = "";

    const uploadedIds = [];

    for (const item of uploadableItems) {
        updateUploadItem({
            id: item.id,
            changes: { status: "uploading", progress: 0, errorMessage: "" },
        });

        try {
            const response = await uploadMediaFile({
                folderId: item.folderId,
                file: item.file,
                name: item.renameBase,
                onUploadProgress: (event) => {
                    if (!event.total) {
                        return;
                    }

                    updateUploadItem({
                        id: item.id,
                        changes: {
                            progress: Math.round(
                                (event.loaded / event.total) * 100,
                            ),
                        },
                    });
                },
            });

            const uploadedFile = createMediaSelection(
                response.data ?? response,
            );
            rememberFile(uploadedFile);

            if (uploadedFile.id !== null) {
                uploadedIds.push(uploadedFile.id);
            }

            updateUploadItem({
                id: item.id,
                changes: {
                    status: "success",
                    progress: 100,
                    uploadedFile,
                },
            });
        } catch (error) {
            updateUploadItem({
                id: item.id,
                changes: {
                    status: "error",
                    errorMessage:
                        error.response?.data?.message ??
                        "Не удалось загрузить файл.",
                },
            });
            console.error(error);
        }
    }

    uploading.value = false;
    showNotice("Очередь обработана.");
    await loadLibrary(currentFolderId.value);

    recentUploadIds.value = uploadedIds;

    if (uploadedIds.length > 0) {
        nextTick(() => {
            mainPanelRef.value?.scrollTo({ top: 0, behavior: "smooth" });
        });
    }

    if (uploadQueue.value.some((item) => item.status === "queued")) {
        void uploadQueuedFiles();
    }
}

function closeModal() {
    emit("close");
}

function markFolderSlugTouched() {
    folderForm.slugTouched = true;
}

function slugify(value) {
    return String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, "-")
        .replace(/^-+|-+$/g, "");
}

function submitSelection() {
    const ids = props.multiple
        ? selectedIdsState.value
        : selectedFile.value?.id
          ? [selectedFile.value.id]
          : [];

    const items = ids
        .map(
            (id) =>
                selectedItemsById.value[id] ??
                files.value.find((file) => file.id === id),
        )
        .filter(Boolean);

    emit("select", props.multiple ? items : (items[0] ?? null));
    closeModal();
}

onMounted(() => {
    window.addEventListener("pointermove", handleTransformPointerMove);
    window.addEventListener("pointerup", stopTransformDrag);
});

onBeforeUnmount(() => {
    window.removeEventListener("pointermove", handleTransformPointerMove);
    window.removeEventListener("pointerup", stopTransformDrag);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="admin-modal" @click.self="closeModal">
            <div
                class="admin-modal__dialog admin-modal__dialog--wide media-library-modal"
                @click.stop
            >
                <div class="admin-modal__header media-library-modal__header">
                    <div>
                        <p class="eyebrow">Media</p>
                        <h2>{{ title }}</h2>
                        <p class="muted">
                            Универсальная медиатека для выбора, загрузки и
                            управления изображениями.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="button-link"
                        @click.stop="closeModal"
                    >
                        Закрыть
                    </button>
                </div>

                <div class="admin-modal__body media-library-modal__body">
                    <div class="media-library-modal__tabs">
                        <button
                            type="button"
                            class="media-library-modal__tab"
                            :class="{ 'is-active': activeTab === 'library' }"
                            @click="setActiveTab('library')"
                        >
                            Все изображения
                        </button>
                        <button
                            type="button"
                            class="media-library-modal__tab"
                            :class="{ 'is-active': activeTab === 'edit' }"
                            :disabled="!canTransformSelectedFile"
                            @click="setActiveTab('edit')"
                        >
                            Редактирование
                        </button>
                    </div>

                    <div class="media-library-modal__content">
                        <div
                            v-if="activeTab === 'library'"
                            ref="mainPanelRef"
                            class="media-library-modal__main"
                            @dragenter="handleMainDragEnter"
                            @dragover="handleMainDragOver"
                            @dragleave="handleMainDragLeave"
                            @drop="handleMainDrop"
                        >
                            <section class="media-library-modal__toolbar">
                                <label
                                    class="admin-form-label media-library-modal__search"
                                >
                                    <span>Поиск</span>
                                    <input
                                        v-model="searchQuery"
                                        class="admin-input"
                                        type="search"
                                        placeholder="Название файла, alt, mime-type, папка"
                                    />
                                </label>

                                <div class="media-library-modal__actions">
                                    <div class="media-library-modal__stat">
                                        <strong>{{
                                            filteredFiles.length
                                        }}</strong>
                                        <span>файлов в текущем списке</span>
                                    </div>

                                    <AdminButton
                                        v-if="allowUpload"
                                        type="button"
                                        @click="
                                            uploadPanelOpen = !uploadPanelOpen
                                        "
                                    >
                                        {{
                                            uploadPanelOpen
                                                ? "Скрыть очередь"
                                                : `Очередь загрузки${queueCount > 0 ? ` (${queueCount})` : ""}`
                                        }}
                                    </AdminButton>
                                </div>
                            </section>

                            <section
                                class="media-library-modal__section media-library-modal__section--folders"
                            >
                                <div class="media-library-modal__section-head">
                                    <div>
                                        <strong>Папки</strong>
                                        <span
                                            >Сначала структура: выберите нужную
                                            папку, затем переходите к
                                            файлам.</span
                                        >
                                    </div>

                                    <button
                                        type="button"
                                        class="button-link media-library-modal__section-button"
                                        @click="openCreateFolderModal"
                                    >
                                        Создать папку
                                    </button>
                                </div>

                                <div
                                    v-if="folderModalOpen"
                                    class="media-library-modal__folder-panel"
                                >
                                    <div
                                        class="media-library-modal__folder-panel-head"
                                    >
                                        <div>
                                            <p class="eyebrow">Folder</p>
                                            <strong>Создать папку</strong>
                                        </div>

                                        <button
                                            type="button"
                                            class="button-link"
                                            :disabled="saving"
                                            @click="closeCreateFolderModal"
                                        >
                                            Отмена
                                        </button>
                                    </div>

                                    <form
                                        class="admin-form-stack"
                                        @submit.prevent="
                                            submitCreateFolderModal
                                        "
                                    >
                                        <label class="admin-form-label">
                                            <span>Название папки</span>
                                            <input
                                                v-model="folderForm.name"
                                                class="admin-input"
                                                type="text"
                                                placeholder="Например, Баннеры"
                                            />
                                            <small
                                                v-if="createErrors.name"
                                                class="error-text"
                                                >{{
                                                    createErrors.name[0]
                                                }}</small
                                            >
                                        </label>

                                        <label class="admin-form-label">
                                            <span>Slug</span>
                                            <input
                                                v-model="folderForm.slug"
                                                class="admin-input"
                                                type="text"
                                                placeholder="bannery"
                                                @input="markFolderSlugTouched"
                                            />
                                            <small
                                                v-if="createErrors.slug"
                                                class="error-text"
                                                >{{
                                                    createErrors.slug[0]
                                                }}</small
                                            >
                                        </label>

                                        <p
                                            class="muted media-library-modal__folder-hint"
                                        >
                                            Родительская папка:
                                            {{ currentFolder?.path || "media" }}
                                        </p>

                                        <div class="admin-actions-row">
                                            <AdminButton
                                                type="submit"
                                                variant="primary"
                                                :disabled="saving"
                                            >
                                                {{
                                                    saving
                                                        ? "Создание..."
                                                        : "Создать"
                                                }}
                                            </AdminButton>
                                            <button
                                                type="button"
                                                class="button-link"
                                                :disabled="saving"
                                                @click="closeCreateFolderModal"
                                            >
                                                Отмена
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <MediaFolders
                                    :breadcrumbs="breadcrumbs"
                                    :folders="folders"
                                    :folder-options="folderOptions"
                                    :current-folder="currentFolder"
                                    :create-errors="createErrors"
                                    :busy="saving"
                                    :show-create="false"
                                    @open-root="openRoot"
                                    @open-folder="openFolder"
                                    @rename-folder="renameFolder"
                                    @delete-folder="removeFolder"
                                />
                            </section>

                            <p v-if="loading" class="muted">
                                Загрузка медиатеки...
                            </p>
                            <p v-else-if="errorMessage" class="error-text">
                                {{ errorMessage }}
                            </p>
                            <p v-else-if="noticeMessage" class="muted">
                                {{ noticeMessage }}
                            </p>

                            <MediaGrid
                                v-if="hasFiles"
                                :files="filteredFiles"
                                :selected-ids="selectedIdsState"
                                :accent-ids="recentUploadIds"
                                @select="handleFileClick"
                            />

                            <div
                                v-if="hasFiles"
                                class="media-library-modal__selection-actions"
                            >
                                <button
                                    type="button"
                                    class="button-base button-primary"
                                    :disabled="!selectedFile && !multiple"
                                    @click="submitSelection"
                                >
                                    {{
                                        multiple
                                            ? `Выбрать (${selectedIdsState.length})`
                                            : "Выбрать"
                                    }}
                                </button>
                            </div>

                            <p v-else-if="!loading" class="muted">
                                В текущей папке пока нет изображений.
                            </p>

                            <details
                                v-if="allowUpload"
                                ref="uploadSectionRef"
                                class="media-library-modal__section"
                                :open="uploadPanelOpen || queueCount > 0"
                            >
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
                                <div
                                    v-if="dragOverlayVisible"
                                    class="media-library-modal__drag-overlay"
                                >
                                    <div class="media-library-modal__drag-card">
                                        <strong
                                            >Отпустите файлы, чтобы добавить в
                                            очередь</strong
                                        >
                                        <span
                                            >После добавления откроется блок
                                            загрузки и мы прокрутим его в
                                            видимую область.</span
                                        >
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <div v-else class="media-library-modal__editor">
                            <div
                                class="media-library-modal__editor-stage-shell"
                            >
                                <div
                                    ref="transformStageRef"
                                    class="media-library-modal__editor-stage"
                                >
                                    <img
                                        :src="selectedFile.url"
                                        :alt="
                                            selectedFile.alt_text ||
                                            selectedFile.original_name
                                        "
                                        draggable="false"
                                    />

                                    <div
                                        v-if="
                                            transformTool === 'crop' &&
                                            transformForm.crop_enabled &&
                                            canTransformSelectedFile
                                        "
                                        class="media-library-modal__crop-box"
                                        :style="transformCropStyle"
                                        @pointerdown.prevent="
                                            startTransformDrag('move', $event)
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="media-library-modal__crop-handle is-nw"
                                            @pointerdown.stop.prevent="
                                                startTransformDrag('nw', $event)
                                            "
                                        ></button>
                                        <button
                                            type="button"
                                            class="media-library-modal__crop-handle is-ne"
                                            @pointerdown.stop.prevent="
                                                startTransformDrag('ne', $event)
                                            "
                                        ></button>
                                        <button
                                            type="button"
                                            class="media-library-modal__crop-handle is-se"
                                            @pointerdown.stop.prevent="
                                                startTransformDrag('se', $event)
                                            "
                                        ></button>
                                        <button
                                            type="button"
                                            class="media-library-modal__crop-handle is-sw"
                                            @pointerdown.stop.prevent="
                                                startTransformDrag('sw', $event)
                                            "
                                        ></button>
                                    </div>
                                </div>
                            </div>

                            <div class="media-library-modal__editor-facts">
                                <div>
                                    <span>Исходник</span
                                    ><strong>{{
                                        selectedFile.width &&
                                        selectedFile.height
                                            ? `${selectedFile.width} x ${selectedFile.height}`
                                            : "—"
                                    }}</strong>
                                </div>
                                <div>
                                    <span>После кадрирования</span
                                    ><strong>{{
                                        transformSourceDimensions.width &&
                                        transformSourceDimensions.height
                                            ? `${transformSourceDimensions.width} x ${transformSourceDimensions.height}`
                                            : "—"
                                    }}</strong>
                                </div>
                                <div>
                                    <span>Итог</span
                                    ><strong>{{
                                        transformOutputDimensions.width &&
                                        transformOutputDimensions.height
                                            ? `${transformOutputDimensions.width} x ${transformOutputDimensions.height}`
                                            : "Без изменения размера"
                                    }}</strong>
                                </div>
                            </div>

                            <div class="media-library-modal__tool-switcher">
                                <button
                                    type="button"
                                    class="media-library-modal__tool-button"
                                    :class="{
                                        'is-active': transformTool === 'crop',
                                    }"
                                    @click="toggleTransformTool('crop')"
                                >
                                    Кадрировать
                                </button>
                                <button
                                    type="button"
                                    class="media-library-modal__tool-button"
                                    :class="{
                                        'is-active': transformTool === 'resize',
                                    }"
                                    @click="toggleTransformTool('resize')"
                                >
                                    Размер
                                </button>
                                <button
                                    type="button"
                                    class="media-library-modal__tool-button"
                                    :class="{
                                        'is-active': transformTool === 'format',
                                    }"
                                    @click="toggleTransformTool('format')"
                                >
                                    Формат
                                </button>
                                <button
                                    type="button"
                                    class="media-library-modal__tool-button"
                                    :class="{
                                        'is-active':
                                            transformTool === 'quality',
                                    }"
                                    @click="toggleTransformTool('quality')"
                                >
                                    Качество
                                </button>
                            </div>

                            <div
                                v-if="transformTool"
                                class="admin-form-stack media-library-modal__tool-panel"
                            >
                                <p
                                    v-if="transformTool === 'crop'"
                                    class="muted"
                                >
                                    Тяните рамку мышкой за область или уголки,
                                    чтобы обрезать изображение прямо в этой
                                    модалке.
                                </p>

                                <label
                                    v-if="transformTool === 'crop'"
                                    class="admin-form-label"
                                >
                                    <span
                                        ><input
                                            v-model="transformForm.crop_enabled"
                                            type="checkbox"
                                        />
                                        Включить кадрирование</span
                                    >
                                </label>

                                <template v-if="transformTool === 'resize'">
                                    <label class="admin-form-label">
                                        <span
                                            ><input
                                                v-model="
                                                    transformForm.maintain_aspect_ratio
                                                "
                                                type="checkbox"
                                            />
                                            Сохранять пропорции</span
                                        >
                                    </label>

                                    <div
                                        class="media-library-modal__editor-grid"
                                    >
                                        <label class="admin-form-label">
                                            <span>Ширина</span>
                                            <input
                                                v-model.number="
                                                    transformForm.resize_width
                                                "
                                                class="admin-input"
                                                type="number"
                                                min="1"
                                                step="1"
                                                @input="handleResizeWidthInput"
                                            />
                                        </label>

                                        <label class="admin-form-label">
                                            <span>Высота</span>
                                            <input
                                                v-model.number="
                                                    transformForm.resize_height
                                                "
                                                class="admin-input"
                                                type="number"
                                                min="1"
                                                step="1"
                                                @input="handleResizeHeightInput"
                                            />
                                        </label>
                                    </div>
                                </template>

                                <label
                                    v-if="transformTool === 'format'"
                                    class="admin-form-label"
                                >
                                    <span>Формат</span>
                                    <select
                                        v-model="transformForm.format"
                                        class="admin-select"
                                    >
                                        <option
                                            v-for="option in transformFormatOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </label>

                                <label
                                    v-if="transformTool === 'quality'"
                                    class="admin-form-label"
                                >
                                    <span>Качество</span>
                                    <input
                                        v-model.number="transformForm.quality"
                                        class="admin-input"
                                        type="number"
                                        min="30"
                                        max="100"
                                        step="1"
                                        placeholder="По умолчанию из настроек"
                                    />
                                </label>

                                <div
                                    class="admin-actions-row media-library-modal__editor-actions"
                                >
                                    <AdminButton
                                        type="button"
                                        variant="primary"
                                        :disabled="transforming"
                                        @click="applyTransform"
                                    >
                                        {{
                                            transforming
                                                ? "Обработка..."
                                                : "Применить изменения"
                                        }}
                                    </AdminButton>
                                    <button
                                        type="button"
                                        class="button-link"
                                        :disabled="transforming"
                                        @click="setActiveTab('library')"
                                    >
                                        К библиотеке
                                    </button>
                                </div>
                            </div>
                        </div>

                        <MediaSidebar
                            :file="selectedFile"
                            :move-folder-options="moveFolderOptions"
                            :saving="saving"
                            :editable="canTransformSelectedFile"
                            @close="activeFileId = null"
                            @edit="openEditorTab"
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
    </Teleport>
</template>
