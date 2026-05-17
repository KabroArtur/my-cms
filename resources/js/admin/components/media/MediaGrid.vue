<script setup>
import { computed } from "vue";

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
    accentIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["select", "remove-upload"]);

const selectedMap = computed(
    () => new Set(props.selectedIds.map((id) => String(id))),
);
const accentMap = computed(
    () => new Set(props.accentIds.map((id) => String(id))),
);

function isUploadPlaceholder(file) {
    return Boolean(file?.is_upload_placeholder);
}

function canRemoveUpload(file) {
    return isUploadPlaceholder(file) && file.upload_status !== "uploading";
}
</script>

<template>
    <div class="media-grid">
        <component
            v-for="file in files"
            :key="file.id"
            :is="isUploadPlaceholder(file) ? 'article' : 'button'"
            :type="isUploadPlaceholder(file) ? undefined : 'button'"
            class="media-grid__item"
            :class="{
                'is-selected': selectedMap.has(String(file.id)),
                'is-recent': accentMap.has(String(file.id)),
                'is-upload-placeholder': isUploadPlaceholder(file),
                'is-upload-error': file.upload_status === 'error',
            }"
            @click="!isUploadPlaceholder(file) && emit('select', file)"
        >
            <div class="media-grid__preview">
                <img
                    v-if="file.preview_url || file.url"
                    :src="file.preview_url || file.url"
                    :alt="file.alt_text || file.original_name"
                />

                <div v-else class="media-grid__preview-fallback">
                    {{ file.extension || file.mime_type || 'FILE' }}
                </div>

                <div
                    v-if="isUploadPlaceholder(file) && file.upload_status === 'uploading'"
                    class="media-grid__upload-overlay"
                >
                    <span class="media-grid__spinner"></span>
                </div>
            </div>

            <div
                class="media-grid__meta"
                :class="{ 'media-grid__meta--upload': isUploadPlaceholder(file) }"
            >
                <strong>{{ file.original_name }}</strong>
                <span
                    >{{ file.size_human
                    }}<template v-if="file.width && file.height">
                        | {{ file.width }} x {{ file.height }}</template
                    ></span
                >

                <span v-if="isUploadPlaceholder(file)">
                    {{
                        file.upload_status === 'uploading'
                            ? `Загрузка ${file.upload_progress}%`
                            : file.upload_status === 'error'
                              ? file.upload_error || 'Не удалось загрузить файл.'
                              : 'Ожидает загрузки'
                    }}
                </span>

                <button
                    v-if="canRemoveUpload(file)"
                    type="button"
                    class="button-link media-grid__upload-remove"
                    @click.stop="emit('remove-upload', file.id)"
                >
                    Убрать
                </button>
            </div>
        </component>
    </div>
</template>
