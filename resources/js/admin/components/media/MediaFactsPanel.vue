<script setup>
import { computed } from "vue";

const props = defineProps({
    file: {
        type: Object,
        default: null,
    },
    croppedDimensions: {
        type: Object,
        default: null,
    },
    outputDimensions: {
        type: Object,
        default: null,
    },
    showTransformFacts: {
        type: Boolean,
        default: false,
    },
});

const originalDimensionsLabel = computed(() => formatDimensions(props.file));
const croppedDimensionsLabel = computed(() =>
    formatDimensions(props.croppedDimensions, "—"),
);
const outputDimensionsLabel = computed(() =>
    formatDimensions(props.outputDimensions, "Без изменения размера"),
);
const uploadedAtLabel = computed(() => {
    if (!props.file?.created_at) {
        return "Неизвестно";
    }

    return new Date(props.file.created_at).toLocaleString();
});

function formatDimensions(source, fallback = "Не указаны") {
    const width = Number(source?.width || 0);
    const height = Number(source?.height || 0);

    if (!width || !height) {
        return fallback;
    }

    return `${width} x ${height}`;
}
</script>

<template>
    <div v-if="file" class="media-facts-panel">
        <div class="media-facts-panel__grid">
            <div class="media-facts-panel__item">
                <span>Размер</span>
                <strong>{{ file.size_human || "Неизвестно" }}</strong>
            </div>
            <div class="media-facts-panel__item">
                <span>MIME</span>
                <strong>{{ file.mime_type || "Неизвестно" }}</strong>
            </div>
            <div class="media-facts-panel__item">
                <span>Размеры</span>
                <strong>{{ originalDimensionsLabel }}</strong>
            </div>
            <div class="media-facts-panel__item">
                <span>Дата загрузки</span>
                <strong>{{ uploadedAtLabel }}</strong>
            </div>
            <div v-if="showTransformFacts" class="media-facts-panel__item">
                <span>Исходник</span>
                <strong>{{ originalDimensionsLabel }}</strong>
            </div>
            <div v-if="showTransformFacts" class="media-facts-panel__item">
                <span>После кадрирования</span>
                <strong>{{ croppedDimensionsLabel }}</strong>
            </div>
            <div v-if="showTransformFacts" class="media-facts-panel__item">
                <span>Итог</span>
                <strong>{{ outputDimensionsLabel }}</strong>
            </div>
        </div>
    </div>
</template>
