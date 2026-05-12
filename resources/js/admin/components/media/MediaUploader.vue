<script setup>
import { computed, ref } from "vue";
import AdminButton from "../ui/AdminButton.vue";

const props = defineProps({
    queue: {
        type: Array,
        default: () => [],
    },
    folderOptions: {
        type: Array,
        default: () => [],
    },
    uploading: {
        type: Boolean,
        default: false,
    },
    uploadProgress: {
        type: Number,
        default: 0,
    },
    accept: {
        type: String,
        default: "",
    },
});

const emit = defineEmits([
    "queue-files",
    "remove-item",
    "clear-queue",
    "upload",
    "update-item",
]);

const fileInput = ref(null);

const stats = computed(() => ({
    total: props.queue.length,
    queued: props.queue.filter((item) => item.status === "queued").length,
    uploading: props.queue.filter((item) => item.status === "uploading").length,
    success: props.queue.filter((item) => item.status === "success").length,
    error: props.queue.filter((item) => item.status === "error").length,
}));

function triggerFileDialog() {
    fileInput.value?.click();
}

function handleFileSelect(event) {
    const files = Array.from(event.target.files ?? []);

    if (files.length > 0) {
        emit("queue-files", files);
    }

    event.target.value = "";
}

function handleDrop(event) {
    const files = Array.from(event.dataTransfer?.files ?? []);

    if (files.length > 0) {
        emit("queue-files", files);
    }
}
</script>

<template>
    <section
        class="media-uploader"
        @dragover.prevent
        @drop.prevent="handleDrop"
    >
        <div class="media-uploader__header">
            <div>
                <h3>Очередь загрузки</h3>
                <p class="muted">
                    Добавьте файлы кнопкой или перетащите их в рабочую область
                    медиатеки. Загрузка начнется автоматически.
                </p>
            </div>

            <div class="admin-actions-row">
                <AdminButton type="button" @click="triggerFileDialog">
                    Добавить файлы
                </AdminButton>
            </div>
        </div>

        <input
            ref="fileInput"
            :accept="accept"
            class="media-uploader__input"
            type="file"
            multiple
            @change="handleFileSelect"
        />

        <div class="media-uploader__progress">
            <p class="muted">
                Всего: {{ stats.total }} | В очереди: {{ stats.queued }} |
                Загружается: {{ stats.uploading }} | Успешно:
                {{ stats.success }} | Ошибки: {{ stats.error }}
            </p>
            <div class="media-uploader__bar">
                <span :style="{ width: `${uploadProgress}%` }"></span>
            </div>
        </div>

        <p v-if="queue.length === 0" class="muted media-uploader__empty">
            Очередь пуста. После drag-and-drop или выбора файлов они появятся
            здесь.
        </p>

        <div v-if="queue.length > 0" class="media-uploader__queue">
            <article
                v-for="item in queue"
                :key="item.id"
                class="media-uploader__item"
                :class="`is-${item.status}`"
            >
                <div class="media-uploader__thumb">
                    <img
                        v-if="item.previewUrl"
                        :src="item.previewUrl"
                        :alt="item.originalName"
                    />
                    <span v-else>{{ item.extension.toUpperCase() }}</span>
                    <div
                        v-if="item.status === 'uploading'"
                        class="media-uploader__thumb-overlay"
                    >
                        <span class="media-uploader__spinner"></span>
                    </div>
                </div>

                <div class="media-uploader__body">
                    <div class="media-uploader__row">
                        <strong>{{ item.originalName }}</strong>
                        <span class="muted">{{ item.progress }}%</span>
                    </div>

                    <div class="media-uploader__fields">
                        <label class="admin-form-label">
                            <span>Имя файла</span>
                            <input
                                :value="item.renameBase"
                                class="admin-input"
                                type="text"
                                :disabled="
                                    uploading || item.status === 'success'
                                "
                                @input="
                                    emit('update-item', {
                                        id: item.id,
                                        changes: {
                                            renameBase: $event.target.value,
                                        },
                                    })
                                "
                            />
                        </label>

                        <label class="admin-form-label">
                            <span>Папка</span>
                            <select
                                class="admin-select"
                                :value="item.folderId ?? ''"
                                :disabled="
                                    uploading || item.status === 'success'
                                "
                                @change="
                                    emit('update-item', {
                                        id: item.id,
                                        changes: {
                                            folderId:
                                                $event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          $event.target.value,
                                                      ),
                                        },
                                    })
                                "
                            >
                                <option
                                    v-for="option in folderOptions"
                                    :key="option.id ?? 'root'"
                                    :value="option.id ?? ''"
                                >
                                    {{ option.path || option.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <p v-if="item.errorMessage" class="error-text">
                        {{ item.errorMessage }}
                    </p>
                </div>

                <button
                    type="button"
                    class="button-link"
                    :disabled="uploading"
                    @click="emit('remove-item', item.id)"
                >
                    Убрать
                </button>
            </article>
        </div>

        <div v-if="queue.length > 0" class="admin-actions-row">
            <button
                type="button"
                class="button-link"
                :disabled="uploading"
                @click="emit('clear-queue')"
            >
                Очистить очередь
            </button>
        </div>
    </section>
</template>
