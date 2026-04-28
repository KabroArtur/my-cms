<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import MediaFilePickerModal from '../../components/media/MediaFilePickerModal.vue'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { fetchSiteSettings, updateSiteSettings } from '../../api/settings'
import { rememberCmsSettings } from '../../composables/useCmsSettings'

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const faviconPickerOpen = ref(false)
const currentFavicon = ref(null)
const options = reactive({
    date_formats: [],
    time_formats: [],
    themes: [],
    media_variants: [],
    cms_palettes: [],
    home_pages: [],
    favicon_files: [],
})

const form = reactive({
    site_name: 'My CMS',
    favicon_media_id: '',
    date_format: 'd.m.Y',
    time_format: 'H:i',
    home_page_id: '',
    site_theme: 'default',
    site_featured_media_variant: 'original',
    media_default_insert_variant: 'original',
    cms_palette: 'slate',
})

const selectedFavicon = computed(() => {
    if (currentFavicon.value && String(currentFavicon.value.value) === String(form.favicon_media_id)) {
        return currentFavicon.value
    }

    return null
})

function pickFavicon(file) {
    form.favicon_media_id = file?.value ?? ''
    currentFavicon.value = file ?? null
}

function fillForm(payload) {
    const settings = payload.settings ?? {}

    form.site_name = settings.site_name ?? 'My CMS'
    form.favicon_media_id = settings.favicon_media_id ?? ''
    form.date_format = settings.date_format ?? 'd.m.Y'
    form.time_format = settings.time_format ?? 'H:i'
    form.home_page_id = settings.home_page_id ?? ''
    form.site_theme = settings.site_theme ?? 'default'
    form.site_featured_media_variant = settings.site_featured_media_variant ?? 'original'
    form.media_default_insert_variant = settings.media_default_insert_variant ?? 'original'
    form.cms_palette = settings.cms_palette ?? 'slate'
    currentFavicon.value = payload.current_favicon ?? null

    Object.assign(options, payload.options ?? {})
}

async function loadSettings() {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchSiteSettings()
        fillForm(payload.data)
        rememberCmsSettings(payload.data)
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить настройки.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitForm() {
    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const payload = await updateSiteSettings({ ...form })
        fillForm(payload.data)
        rememberCmsSettings(payload.data)
        successMessage.value = 'Настройки сохранены.'
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось сохранить настройки.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

onMounted(loadSettings)
</script>

<template>
    <AdminPage
        eyebrow="Settings"
        title="Настройки"
        description="Базовые настройки сайта, медиаслоя и внешнего вида административной системы."
    >
        <section class="admin-page-grid">
            <AdminCard>
                <p v-if="loading" class="muted">Загрузка настроек...</p>

                <form v-else class="admin-form-stack" @submit.prevent="submitForm">
                    <label class="admin-form-label">
                        <span>Название сайта</span>
                        <input v-model="form.site_name" class="admin-input" type="text" placeholder="My CMS">
                    </label>

                    <label class="admin-form-label">
                        <span>Favicon</span>
                        <div class="admin-actions-row">
                            <AdminButton type="button" @click="faviconPickerOpen = true">
                                Выбрать из медиатеки
                            </AdminButton>

                            <AdminButton v-if="form.favicon_media_id" type="button" @click="pickFavicon(null)">
                                Очистить
                            </AdminButton>
                        </div>
                        <small class="muted">Сейчас это отдельный компонент выбора изображения. Дальше его можно переиспользовать как полноценную modal-медиатеку и для других полей.</small>
                    </label>

                    <div class="settings-favicon-picker">
                        <div class="settings-favicon-picker__current">
                            <span class="eyebrow">Текущий favicon</span>
                            <div class="settings-favicon-picker__current-card">
                                <div class="settings-favicon-picker__preview">
                                    <img v-if="selectedFavicon" :src="selectedFavicon.preview_url || selectedFavicon.url" :alt="selectedFavicon.label">
                                    <span v-else>Нет</span>
                                </div>
                                <div>
                                    <strong>{{ selectedFavicon?.label || 'Favicon не выбран' }}</strong>
                                    <p class="muted">{{ selectedFavicon ? 'Источник: медиатека' : 'Будет использоваться favicon браузера по умолчанию.' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="page-meta-grid">
                        <label class="admin-form-label">
                            <span>Формат даты</span>
                            <select v-model="form.date_format" class="admin-select">
                                <option v-for="format in options.date_formats" :key="format.value" :value="format.value">
                                    {{ format.label }}
                                </option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Формат времени</span>
                            <select v-model="form.time_format" class="admin-select">
                                <option v-for="format in options.time_formats" :key="format.value" :value="format.value">
                                    {{ format.label }}
                                </option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Главная страница</span>
                            <select v-model="form.home_page_id" class="admin-select">
                                <option value="">Автовыбор</option>
                                <option v-for="page in options.home_pages" :key="page.value" :value="page.value">
                                    {{ page.label }}{{ page.path ? ` (/${page.path})` : ' (/)' }}
                                </option>
                            </select>
                            <small class="muted">Главная теперь выбирается здесь, а не в каждой странице отдельно.</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Тема сайта</span>
                            <select v-model="form.site_theme" class="admin-select">
                                <option v-for="theme in options.themes" :key="theme.value" :value="theme.value">
                                    {{ theme.label }}
                                </option>
                            </select>
                            <small class="muted">{{ options.themes.find((theme) => theme.value === form.site_theme)?.description || 'Выбирает blade-тему публичного сайта.' }}</small>
                        </label>
                    </div>

                    <div class="page-meta-grid">
                        <label class="admin-form-label">
                            <span>Размер обложки на сайте</span>
                            <select v-model="form.site_featured_media_variant" class="admin-select">
                                <option v-for="variant in options.media_variants" :key="variant.value" :value="variant.value">
                                    {{ variant.label }}
                                </option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Размер по умолчанию для вставки в контент</span>
                            <select v-model="form.media_default_insert_variant" class="admin-select">
                                <option v-for="variant in options.media_variants" :key="variant.value" :value="variant.value">
                                    {{ variant.label }}
                                </option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Палитра CMS</span>
                            <select v-model="form.cms_palette" class="admin-select">
                                <option v-for="palette in options.cms_palettes" :key="palette.value" :value="palette.value">
                                    {{ palette.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>
                    <p v-if="successMessage" class="muted">{{ successMessage }}</p>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving">
                            {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
                        </AdminButton>
                    </div>
                </form>

                <MediaFilePickerModal
                    :open="faviconPickerOpen"
                    :model-value="form.favicon_media_id"
                    title="Выбрать favicon"
                    @update:model-value="(value) => { form.favicon_media_id = value }"
                    @select="pickFavicon"
                    @close="faviconPickerOpen = false"
                />
            </AdminCard>
        </section>
    </AdminPage>
</template>