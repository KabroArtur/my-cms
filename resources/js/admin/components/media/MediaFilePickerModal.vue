<script setup>
import { computed, ref, watch } from 'vue'
import AdminButton from '../ui/AdminButton.vue'
import { fetchMediaLibrary } from '../../api/media'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    modelValue: {
        type: [Number, String, null],
        default: '',
    },
    title: {
        type: String,
        default: 'Выбрать изображение',
    },
})

const emit = defineEmits(['update:modelValue', 'select', 'close'])

const loading = ref(false)
const errorMessage = ref('')
const searchQuery = ref('')
const currentFolder = ref(null)
const breadcrumbs = ref([])
const folders = ref([])
const files = ref([])

const filteredFiles = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (query === '') {
        return files.value
    }

    return files.value.filter((file) => {
        return [file.original_name, file.title, file.alt_text]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query))
    })
})

watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        await loadLibrary(null)
    }
})

async function loadLibrary(folderId = null) {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchMediaLibrary(folderId)
        currentFolder.value = payload.data?.current_folder ?? null
        breadcrumbs.value = payload.data?.breadcrumbs ?? []
        folders.value = payload.data?.folders ?? []
        files.value = payload.data?.files ?? []
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить медиафайлы.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function openFolder(folder) {
    await loadLibrary(folder.id)
}

async function openRoot() {
    await loadLibrary(null)
}

function closeModal() {
    emit('close')
}

function selectFile(file) {
    const selection = {
        value: file.id,
        label: file.title || file.original_name,
        preview_url: file.preview_url || file.url,
        url: file.url,
        original_name: file.original_name,
    }

    emit('update:modelValue', selection.value)
    emit('select', selection)
    closeModal()
}
</script>

<template>
    <div v-if="open" class="admin-modal" @click.self="closeModal">
        <div class="admin-modal__dialog admin-modal__dialog--wide">
            <div class="admin-modal__header">
                <div>
                    <p class="eyebrow">Media</p>
                    <h2>{{ title }}</h2>
                </div>

                <AdminButton type="button" @click="closeModal">
                    Закрыть
                </AdminButton>
            </div>

            <div class="admin-modal__body media-picker-modal">
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

                <label class="admin-form-label">
                    <span>Поиск</span>
                    <input v-model="searchQuery" class="admin-input" type="search" placeholder="Найти изображение по имени или alt">
                </label>

                <p v-if="loading" class="muted">Загрузка медиатеки...</p>
                <p v-else-if="errorMessage" class="error-text">{{ errorMessage }}</p>

                <div v-if="folders.length > 0" class="page-media-picker__folders">
                    <button
                        v-for="folder in folders"
                        :key="folder.id"
                        type="button"
                        class="page-media-picker__folder"
                        @click="openFolder(folder)"
                    >
                        {{ folder.name }}
                    </button>
                </div>

                <div v-if="filteredFiles.length > 0" class="media-picker-modal__grid">
                    <button
                        v-for="file in filteredFiles"
                        :key="file.id"
                        type="button"
                        class="media-picker-modal__item"
                        :class="{ 'is-active': String(file.id) === String(modelValue) }"
                        @click="selectFile(file)"
                    >
                        <div class="media-picker-modal__preview">
                            <img :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
                        </div>

                        <div class="media-picker-modal__meta">
                            <strong>{{ file.title || file.original_name }}</strong>
                            <span>{{ file.original_name }}</span>
                        </div>
                    </button>
                </div>

                <p v-else-if="!loading" class="muted">В этой папке пока нет изображений.</p>
            </div>
        </div>
    </div>
</template>