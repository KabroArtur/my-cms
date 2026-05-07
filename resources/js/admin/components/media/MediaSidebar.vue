<script setup>
import { reactive, watch } from 'vue'
import AdminButton from '../ui/AdminButton.vue'
import { stripExtension } from './mediaHelpers'

const props = defineProps({
    file: {
        type: Object,
        default: null,
    },
    moveFolderOptions: {
        type: Array,
        default: () => [],
    },
    saving: {
        type: Boolean,
        default: false,
    },
    selecting: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'save', 'select', 'copy-url', 'delete', 'move-file'])

const form = reactive({
    original_name: '',
    alt_text: '',
    folder_id: '',
})

watch(() => props.file, (file) => {
    form.original_name = stripExtension(file?.original_name || file?.filename || '')
    form.alt_text = file?.alt_text ?? ''
    form.folder_id = file?.folder_id ?? ''
}, { immediate: true })

function submit() {
    if (!props.file) {
        return
    }

    emit('save', {
        original_name: form.original_name,
        alt_text: form.alt_text,
    })
}

function moveFile(event) {
    if (!props.file) {
        return
    }

    const value = event.target.value === '' ? null : Number(event.target.value)
    emit('move-file', {
        file: props.file,
        folder_id: value,
    })
}
</script>

<template>
    <aside v-if="file" class="media-sidebar">
        <div class="media-sidebar__header">
            <div>
                <p class="eyebrow">Файл</p>
                <h3>{{ file.original_name }}</h3>
            </div>

            <button type="button" class="button-link" @click="emit('close')">
                Закрыть
            </button>
        </div>

        <div class="media-sidebar__preview">
            <img :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
        </div>

        <div class="media-sidebar__facts">
            <div>
                <span>URL</span>
                <strong>{{ file.url }}</strong>
            </div>
            <div>
                <span>Размер</span>
                <strong>{{ file.size_human }}</strong>
            </div>
            <div>
                <span>Размеры</span>
                <strong>{{ file.width && file.height ? `${file.width} x ${file.height}` : 'Неизвестно' }}</strong>
            </div>
            <div>
                <span>Папка</span>
                <strong>{{ file.folder_name || 'Корень' }}</strong>
            </div>
            <div>
                <span>Дата загрузки</span>
                <strong>{{ file.created_at ? new Date(file.created_at).toLocaleString() : 'Неизвестно' }}</strong>
            </div>
        </div>

        <form class="admin-form-stack" @submit.prevent="submit">
            <label class="admin-form-label">
                <span>Название файла</span>
                <input v-model="form.original_name" class="admin-input" type="text">
            </label>

            <label class="admin-form-label">
                <span>Alt текст</span>
                <input v-model="form.alt_text" class="admin-input" type="text">
            </label>

            <label class="admin-form-label">
                <span>Переместить в папку</span>
                <select class="admin-select" :value="form.folder_id" @change="moveFile">
                    <option
                        v-for="folderOption in moveFolderOptions"
                        :key="folderOption.id ?? 'root'"
                        :value="folderOption.id ?? ''"
                    >
                        {{ folderOption.path || folderOption.name }}
                    </option>
                </select>
            </label>

            <div class="admin-actions-row media-sidebar__actions">
                <AdminButton type="submit" variant="primary" :disabled="saving">
                    {{ saving ? 'Сохранение...' : 'Сохранить' }}
                </AdminButton>

                <AdminButton type="button" :disabled="selecting" @click="emit('select', file)">
                    Выбрать
                </AdminButton>

                <button type="button" class="button-link" @click="emit('copy-url', file)">
                    Скопировать URL
                </button>

                <button type="button" class="button-link media-sidebar__danger" @click="emit('delete', file)">
                    Удалить
                </button>
            </div>
        </form>
    </aside>
</template>

<style scoped>
.media-sidebar {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border-left: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(248, 250, 252, 0.92);
}

.media-sidebar__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.media-sidebar__header h3 {
    margin: 0.15rem 0 0;
    font-size: 1rem;
    word-break: break-word;
}

.media-sidebar__preview {
    overflow: hidden;
    border-radius: 16px;
    background: white;
    border: 1px solid rgba(148, 163, 184, 0.18);
}

.media-sidebar__preview img {
    display: block;
    width: 100%;
    max-height: 260px;
    object-fit: contain;
    background: linear-gradient(135deg, rgba(226, 232, 240, 0.88), rgba(248, 250, 252, 0.98));
}

.media-sidebar__facts {
    display: grid;
    gap: 0.75rem;
}

.media-sidebar__facts div {
    display: grid;
    gap: 0.2rem;
}

.media-sidebar__facts span {
    color: rgba(100, 116, 139, 0.9);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.media-sidebar__facts strong {
    font-size: 0.92rem;
    word-break: break-word;
}

.media-sidebar__actions {
    flex-wrap: wrap;
}

.media-sidebar__danger {
    color: #b91c1c;
}
</style>