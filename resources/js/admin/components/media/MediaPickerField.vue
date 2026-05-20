<script setup>
import { computed, ref, watch } from "vue";
import AdminButton from "../ui/AdminButton.vue";
import { fetchMediaFile } from "../../api/media";
import MediaLibraryModal from "./MediaLibraryModal.vue";
import {
    DEFAULT_MEDIA_ACCEPT,
    createExternalMediaReference,
    createMediaSelection,
    normalizeToArray,
    toNumericId,
} from "./mediaHelpers";

const props = defineProps({
    modelValue: {
        type: [Array, Object, Number, String, null],
        default: null,
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
    returnType: {
        type: String,
        default: "url",
    },
    allowUpload: {
        type: Boolean,
        default: true,
    },
    title: {
        type: String,
        default: "Выбрать изображение",
    },
});

const emit = defineEmits(["update:modelValue", "select"]);

const modalOpen = ref(false);
const loading = ref(false);
const errorMessage = ref("");
const resolvedItems = ref([]);
const manualUrl = ref("");
const syncToken = ref(0);

const allowManualUrl = computed(
    () => !props.multiple && props.returnType !== "id",
);
const previewItems = computed(() =>
    props.multiple ? resolvedItems.value : resolvedItems.value.slice(0, 1),
);
const selectedIds = computed(() =>
    resolvedItems.value.map((item) => item.id).filter((id) => id !== null),
);

function isImageItem(item) {
    return (
        String(item?.mime_type || "").startsWith("image/") ||
        /\.(avif|bmp|gif|jpe?g|png|svg|webp)$/i.test(String(item?.url || ""))
    );
}

watch(
    () => props.modelValue,
    () => {
        syncFromModelValue();
    },
    { immediate: true, deep: true },
);

async function syncFromModelValue() {
    const currentToken = syncToken.value + 1;
    syncToken.value = currentToken;
    loading.value = true;
    errorMessage.value = "";

    const items = [];
    const idsToFetch = [];

    for (const value of normalizeToArray(props.modelValue)) {
        if (value === null || value === undefined || value === "") {
            continue;
        }

        if (typeof value === "object") {
            if ((value.id ?? value.value) !== undefined && !value.url) {
                const id = toNumericId(value.id ?? value.value);

                if (id !== null) {
                    idsToFetch.push(id);
                    continue;
                }
            }

            items.push(createMediaSelection(value));
            continue;
        }

        const numericId = toNumericId(value);

        if (numericId !== null) {
            idsToFetch.push(numericId);
            continue;
        }

        if (typeof value === "string") {
            items.push(createExternalMediaReference(value));
        }
    }

    if (idsToFetch.length > 0) {
        try {
            const responses = await Promise.all(
                idsToFetch.map((id) => fetchMediaFile(id)),
            );

            for (const response of responses) {
                items.push(createMediaSelection(response.data ?? response));
            }
        } catch (error) {
            errorMessage.value = "Не удалось загрузить выбранный файл.";
            console.error(error);
        }
    }

    if (syncToken.value !== currentToken) {
        return;
    }

    resolvedItems.value = items;
    manualUrl.value = allowManualUrl.value ? (items[0]?.url ?? "") : "";
    loading.value = false;
}

function encodeSelection(items) {
    const normalizedItems = props.multiple ? items : items.slice(0, 1);

    const encodeSingle = (item) => {
        if (!item) {
            return props.returnType === "object" ? null : "";
        }

        if (props.returnType === "id") {
            return item.id ?? "";
        }

        if (props.returnType === "object") {
            return item;
        }

        return item.url ?? "";
    };

    if (props.multiple) {
        return normalizedItems.map(encodeSingle);
    }

    return encodeSingle(normalizedItems[0] ?? null);
}

function handleModalSelect(selection) {
    const items = normalizeToArray(selection).map((item) =>
        createMediaSelection(item),
    );

    modalOpen.value = false;
    resolvedItems.value = items;
    manualUrl.value = allowManualUrl.value ? (items[0]?.url ?? "") : "";

    emit("update:modelValue", encodeSelection(items));
    emit("select", props.multiple ? items : (items[0] ?? null));
}

function handleManualUrlInput(event) {
    const nextUrl = String(event.target.value ?? "").trim();
    manualUrl.value = nextUrl;

    if (nextUrl === "") {
        resolvedItems.value = [];
        emit("update:modelValue", encodeSelection([]));
        return;
    }

    const item = createExternalMediaReference(nextUrl);
    resolvedItems.value = [item];
    emit("update:modelValue", encodeSelection([item]));
    emit("select", item);
}

function clearSelection() {
    resolvedItems.value = [];
    manualUrl.value = "";
    emit("update:modelValue", encodeSelection([]));
}
</script>

<template>
    <div class="media-picker-field">
        <div class="media-picker-field__toolbar">
            <div class="admin-actions-row">
                <AdminButton
                    type="button"
                    variant="primary"
                    @click="modalOpen = true"
                >
                    Выбрать из медиатеки
                </AdminButton>

                <AdminButton
                    v-if="previewItems.length > 0 || modelValue"
                    type="button"
                    @click="clearSelection"
                >
                    Очистить
                </AdminButton>
            </div>

            <p v-if="loading" class="muted">Загрузка выбранного файла...</p>
            <p v-else-if="errorMessage" class="error-text">
                {{ errorMessage }}
            </p>
        </div>

        <label v-if="allowManualUrl" class="admin-form-label">
            <span>URL вручную</span>
            <input
                :value="manualUrl"
                class="admin-input"
                type="url"
                placeholder="https://example.com/image.jpg"
                @input="handleManualUrlInput"
            />
        </label>

        <div
            v-if="previewItems.length > 0"
            class="media-picker-field__previews"
            :class="{ 'is-multiple': multiple }"
        >
            <article
                v-for="item in previewItems"
                :key="item.id ?? item.url"
                class="media-picker-field__preview-card"
            >
                <div
                    class="media-picker-field__preview-image"
                    :class="{ 'is-file': !isImageItem(item) }"
                >
                    <img
                        v-if="isImageItem(item)"
                        :src="item.preview_url || item.url"
                        :alt="item.alt_text || item.original_name"
                    />
                    <span v-else>{{ item.extension || "file" }}</span>
                </div>
                <div>
                    <strong>{{ item.original_name }}</strong>
                    <p class="muted">{{ item.url }}</p>
                </div>
            </article>
        </div>

        <p v-else class="text-center">Файл пока не выбран.</p>

        <MediaLibraryModal
            :open="modalOpen"
            :title="title"
            :multiple="multiple"
            :accept="accept"
            :folder="folder"
            :allow-upload="allowUpload"
            :selected-ids="selectedIds"
            @close="modalOpen = false"
            @select="handleModalSelect"
        />
    </div>
</template>
