<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { fetchCurrentUser } from '../../api/auth'
import MediaFilePickerModal from '../../components/media/MediaFilePickerModal.vue'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { clearCmsCache, fetchSiteSettings, updateSiteSettings } from '../../api/settings'
import { rememberCmsSettings } from '../../composables/useCmsSettings'

const loading = ref(true)
const saving = ref(false)
const clearingCache = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const activeSettingsTab = ref('general')
const adminPathNoticeUrl = ref('')
const faviconPickerOpen = ref(false)
const currentFavicon = ref(null)
const canManageAdditionalFields = ref(false)
const canManageGeneral = ref(false)
const canManageAppearance = ref(false)
const canManageCache = ref(false)
const canManageSecurity = ref(false)
const options = reactive({
    date_formats: [],
    time_formats: [],
    themes: [],
    media_variants: [],
    cms_palettes: [],
    admin_theme_modes: [],
    admin_light_palettes: [],
    admin_dark_palettes: [],
    home_pages: [],
    favicon_files: [],
})

const cacheStats = reactive({
    site_version: null,
    cms_version: null,
    last_cleared_at: null,
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
    admin_theme_mode: 'dark',
    admin_light_palette: 'slate',
    admin_dark_palette: 'midnight',
    cms_palette: 'slate',
    theme_assets_minify_css: true,
    theme_assets_minify_js: true,
    theme_assets_combine_css: true,
    theme_assets_combine_js: true,
    theme_assets_defer_scripts: true,
    theme_assets_use_hash: true,
    cache_data_enabled: true,
    cache_response_enabled: true,
    cache_response_ttl: 0,
    admin_entry_path: 'admin',
    security_rate_limit_enabled: true,
    security_rate_limit_per_minute: 180,
    security_rate_limit_burst_per_10s: 45,
    security_ip_ban_seconds: 900,
    security_login_max_attempts: 5,
    security_login_decay_seconds: 120,
    security_login_ban_seconds: 1800,
    security_emergency_mode: false,
    security_emergency_message: 'Сайт временно переведен в аварийный режим обслуживания. Попробуйте позже.',
})

const selectedFavicon = computed(() => {
    if (currentFavicon.value && String(currentFavicon.value.value) === String(form.favicon_media_id)) {
        return currentFavicon.value
    }

    return null
})

const adminEntryPreviewUrl = computed(() => {
    const raw = String(form.admin_entry_path ?? '').trim().toLowerCase()
    const normalized = raw.replace(/^\/+|\/+$/g, '')
    const fallback = 'admin'
    const safe = normalized.length > 0 ? normalized : fallback
    const origin = typeof window !== 'undefined' ? window.location.origin : ''

    return `${origin}/${safe}`
})

const securityProfiles = [
    {
        key: 'low',
        label: 'Low traffic',
        hint: 'Небольшой сайт до ~20 req/min на IP',
        values: {
            security_rate_limit_enabled: true,
            security_rate_limit_per_minute: 120,
            security_rate_limit_burst_per_10s: 30,
            security_ip_ban_seconds: 600,
            security_login_max_attempts: 5,
            security_login_decay_seconds: 120,
            security_login_ban_seconds: 1200,
        },
    },
    {
        key: 'medium',
        label: 'Medium traffic',
        hint: 'Типовой прод с умеренной нагрузкой',
        values: {
            security_rate_limit_enabled: true,
            security_rate_limit_per_minute: 220,
            security_rate_limit_burst_per_10s: 60,
            security_ip_ban_seconds: 900,
            security_login_max_attempts: 5,
            security_login_decay_seconds: 120,
            security_login_ban_seconds: 1800,
        },
    },
    {
        key: 'high',
        label: 'High traffic',
        hint: 'Высокий трафик/бот-нагрузка, агрессивнее ban',
        values: {
            security_rate_limit_enabled: true,
            security_rate_limit_per_minute: 300,
            security_rate_limit_burst_per_10s: 80,
            security_ip_ban_seconds: 1800,
            security_login_max_attempts: 4,
            security_login_decay_seconds: 180,
            security_login_ban_seconds: 3600,
        },
    },
]

function applySecurityProfile(profile) {
    Object.assign(form, profile.values)
}

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
    form.admin_theme_mode = settings.admin_theme_mode ?? 'dark'
    form.admin_light_palette = settings.admin_light_palette ?? 'slate'
    form.admin_dark_palette = settings.admin_dark_palette ?? 'midnight'
    form.cms_palette = settings.cms_palette ?? 'slate'
    form.theme_assets_minify_css = settings.theme_assets_minify_css ?? true
    form.theme_assets_minify_js = settings.theme_assets_minify_js ?? true
    form.theme_assets_combine_css = settings.theme_assets_combine_css ?? true
    form.theme_assets_combine_js = settings.theme_assets_combine_js ?? true
    form.theme_assets_defer_scripts = settings.theme_assets_defer_scripts ?? true
    form.theme_assets_use_hash = settings.theme_assets_use_hash ?? true
    const legacyCacheEnabled = settings.cache_enabled ?? true
    form.cache_data_enabled = settings.cache_data_enabled ?? legacyCacheEnabled
    form.cache_response_enabled = settings.cache_response_enabled ?? legacyCacheEnabled
    form.cache_response_ttl = settings.cache_response_ttl ?? 0
    form.admin_entry_path = settings.admin_entry_path ?? 'admin'
    form.security_rate_limit_enabled = settings.security_rate_limit_enabled ?? true
    form.security_rate_limit_per_minute = settings.security_rate_limit_per_minute ?? 180
    form.security_rate_limit_burst_per_10s = settings.security_rate_limit_burst_per_10s ?? 45
    form.security_ip_ban_seconds = settings.security_ip_ban_seconds ?? 900
    form.security_login_max_attempts = settings.security_login_max_attempts ?? 5
    form.security_login_decay_seconds = settings.security_login_decay_seconds ?? 120
    form.security_login_ban_seconds = settings.security_login_ban_seconds ?? 1800
    form.security_emergency_mode = settings.security_emergency_mode ?? false
    form.security_emergency_message = settings.security_emergency_message ?? 'Сайт временно переведен в аварийный режим обслуживания. Попробуйте позже.'
    currentFavicon.value = payload.current_favicon ?? null
    Object.assign(cacheStats, payload.cache ?? {
        site_version: null,
        cms_version: null,
        last_cleared_at: null,
    })

    Object.assign(options, payload.options ?? {})
}

async function loadSettings() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [payload, currentUserPayload] = await Promise.all([
            fetchSiteSettings(),
            fetchCurrentUser(),
        ])

        fillForm(payload.data)
        rememberCmsSettings(payload.data)
        canManageAdditionalFields.value = (currentUserPayload.data?.permissions ?? []).includes('pages.additional_fields.manage')
        canManageGeneral.value = payload.data?.permissions?.['settings.general.manage'] === true
        canManageAppearance.value = payload.data?.permissions?.['settings.appearance.manage'] === true
        canManageCache.value = payload.data?.permissions?.['settings.cache.manage'] === true
        canManageSecurity.value = payload.data?.permissions?.['settings.security.manage'] === true

        if (
            (activeSettingsTab.value === 'general' && !canManageGeneral.value)
            || (activeSettingsTab.value === 'appearance' && !canManageAppearance.value)
            || (activeSettingsTab.value === 'cache' && !canManageCache.value)
            || (activeSettingsTab.value === 'security' && !canManageSecurity.value)
        ) {
            if (canManageGeneral.value) {
                activeSettingsTab.value = 'general'
            } else if (canManageAppearance.value) {
                activeSettingsTab.value = 'appearance'
            } else if (canManageCache.value) {
                activeSettingsTab.value = 'cache'
            } else if (canManageSecurity.value) {
                activeSettingsTab.value = 'security'
            }
        }
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
    adminPathNoticeUrl.value = ''

    try {
        const submitPayload = { ...form }

        submitPayload.cms_palette = submitPayload.admin_theme_mode === 'dark'
            ? submitPayload.admin_dark_palette
            : submitPayload.admin_light_palette

        if (!canManageGeneral.value) {
            delete submitPayload.site_name
            delete submitPayload.favicon_media_id
            delete submitPayload.date_format
            delete submitPayload.time_format
            delete submitPayload.home_page_id
            delete submitPayload.site_theme
        }

        if (!canManageAppearance.value) {
            delete submitPayload.site_featured_media_variant
            delete submitPayload.media_default_insert_variant
            delete submitPayload.admin_theme_mode
            delete submitPayload.admin_light_palette
            delete submitPayload.admin_dark_palette
            delete submitPayload.cms_palette
            delete submitPayload.theme_assets_minify_css
            delete submitPayload.theme_assets_minify_js
            delete submitPayload.theme_assets_combine_css
            delete submitPayload.theme_assets_combine_js
            delete submitPayload.theme_assets_defer_scripts
            delete submitPayload.theme_assets_use_hash
        }

        if (!canManageCache.value) {
            delete submitPayload.cache_enabled
            delete submitPayload.cache_data_enabled
            delete submitPayload.cache_response_enabled
            delete submitPayload.cache_response_ttl
        }

        if (!canManageSecurity.value) {
            delete submitPayload.admin_entry_path
            delete submitPayload.security_rate_limit_enabled
            delete submitPayload.security_rate_limit_per_minute
            delete submitPayload.security_rate_limit_burst_per_10s
            delete submitPayload.security_ip_ban_seconds
            delete submitPayload.security_login_max_attempts
            delete submitPayload.security_login_decay_seconds
            delete submitPayload.security_login_ban_seconds
            delete submitPayload.security_emergency_mode
            delete submitPayload.security_emergency_message
        }

        const payload = await updateSiteSettings(submitPayload)
        fillForm(payload.data)
        rememberCmsSettings(payload.data)

        const nextUrl = payload.data?.admin_path?.new_url_once

        if (typeof nextUrl === 'string' && nextUrl !== '') {
            adminPathNoticeUrl.value = nextUrl
            successMessage.value = 'Путь входа изменен. Новый URL показан один раз ниже.'
            setTimeout(() => {
                window.location.href = nextUrl
            }, 1200)
        } else {
            successMessage.value = 'Настройки сохранены.'
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось сохранить настройки.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function clearCache() {
    clearingCache.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const payload = await clearCmsCache()
        Object.assign(cacheStats, payload.data?.cache ?? {})
        successMessage.value = payload.message ?? 'Кэш очищен.'
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось очистить кэш.'
        console.error(error)
    } finally {
        clearingCache.value = false
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
        <template #actions>
            <RouterLink v-if="canManageAdditionalFields" :to="{ name: 'content-structure' }" class="button-link">
                Структура контента
            </RouterLink>
        </template>

        <section class="admin-page-grid">
            <AdminCard>
                <p v-if="loading" class="muted">Загрузка настроек...</p>

                <form v-else class="admin-form-stack" @submit.prevent="submitForm">
                    <div class="admin-tabs" role="tablist" aria-label="Разделы настроек">
                        <button v-if="canManageGeneral" type="button" class="admin-tab" :class="{ 'is-active': activeSettingsTab === 'general' }" @click="activeSettingsTab = 'general'">
                            Общие
                        </button>
                        <button v-if="canManageAppearance" type="button" class="admin-tab" :class="{ 'is-active': activeSettingsTab === 'appearance' }" @click="activeSettingsTab = 'appearance'">
                            Внешний вид
                        </button>
                        <button v-if="canManageCache" type="button" class="admin-tab" :class="{ 'is-active': activeSettingsTab === 'cache' }" @click="activeSettingsTab = 'cache'">
                            Кэш
                        </button>
                        <button v-if="canManageSecurity" type="button" class="admin-tab" :class="{ 'is-active': activeSettingsTab === 'security' }" @click="activeSettingsTab = 'security'">
                            Безопасность
                        </button>
                    </div>

                    <section v-show="activeSettingsTab === 'general'" class="settings-tab-panel">
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
                    </section>

                    <section v-show="activeSettingsTab === 'appearance'" class="settings-tab-panel">
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
                            <span>Режим темы админки</span>
                            <select v-model="form.admin_theme_mode" class="admin-select">
                                <option v-for="mode in options.admin_theme_modes" :key="mode.value" :value="mode.value">
                                    {{ mode.label }}
                                </option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Палитра для светлой темы</span>
                            <select v-model="form.admin_light_palette" class="admin-select">
                                <option v-for="palette in options.admin_light_palettes" :key="palette.value" :value="palette.value">
                                    {{ palette.label }}
                                </option>
                            </select>
                        </label>

                        <label class="admin-form-label">
                            <span>Палитра для темной темы</span>
                            <select v-model="form.admin_dark_palette" class="admin-select">
                                <option v-for="palette in options.admin_dark_palettes" :key="palette.value" :value="palette.value">
                                    {{ palette.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="settings-cache-card">
                        <p class="settings-cache-card__title">Ассеты темы (CSS/JS)</p>
                        <div class="page-meta-grid">
                            <label class="admin-form-label">
                                <span><input v-model="form.theme_assets_minify_css" type="checkbox"> Сжимать CSS</span>
                            </label>
                            <label class="admin-form-label">
                                <span><input v-model="form.theme_assets_minify_js" type="checkbox"> Сжимать JS</span>
                            </label>
                            <label class="admin-form-label">
                                <span><input v-model="form.theme_assets_combine_css" type="checkbox"> Объединять CSS</span>
                            </label>
                            <label class="admin-form-label">
                                <span><input v-model="form.theme_assets_combine_js" type="checkbox"> Объединять JS</span>
                            </label>
                            <label class="admin-form-label">
                                <span><input v-model="form.theme_assets_defer_scripts" type="checkbox"> Defer для JS по умолчанию</span>
                            </label>
                            <label class="admin-form-label">
                                <span><input v-model="form.theme_assets_use_hash" type="checkbox"> Добавлять hash в URL</span>
                            </label>
                        </div>
                    </div>
                    </section>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>
                    <p v-if="successMessage" class="muted">{{ successMessage }}</p>
                    <p v-if="adminPathNoticeUrl" class="muted">
                        Новый URL админки: <a :href="adminPathNoticeUrl">{{ adminPathNoticeUrl }}</a>
                    </p>

                    <section v-show="activeSettingsTab === 'cache'" class="settings-tab-panel">
                        <div class="settings-cache-card">
                        <p class="settings-cache-card__title">Кэш сайта и CMS</p>
                        <div class="page-meta-grid">
                            <label class="admin-form-label">
                                <span><input v-model="form.cache_data_enabled" type="checkbox"> Data cache (модули, настройки, вычисления)</span>
                            </label>

                            <label class="admin-form-label">
                                <span><input v-model="form.cache_response_enabled" type="checkbox"> Full-page cache (готовый HTML)</span>
                            </label>

                            <label class="admin-form-label">
                                <span>TTL full-page cache (сек)</span>
                                <input v-model.number="form.cache_response_ttl" class="admin-input" type="number" min="0" step="1" :disabled="!form.cache_response_enabled">
                                <small class="muted">0 = бессрочно, с инвалидацией по изменениям контента/настроек.</small>
                            </label>
                        </div>
                        <p class="muted">Data cache (форма): {{ form.cache_data_enabled ? 'включен' : 'выключен' }} | Full-page (форма): {{ form.cache_response_enabled ? 'включен' : 'выключен' }}</p>
                        <p class="muted">Data cache (сервер): {{ cacheStats.data_enabled === true ? 'включен' : cacheStats.data_enabled === false ? 'выключен' : '—' }} | Full-page (сервер): {{ cacheStats.response_enabled === true ? 'включен' : cacheStats.response_enabled === false ? 'выключен' : '—' }}</p>
                        <p class="muted">Site version: {{ cacheStats.site_version ?? '—' }} | CMS version: {{ cacheStats.cms_version ?? '—' }}</p>
                        <p class="muted">Последняя очистка: {{ cacheStats.last_cleared_at ?? 'нет данных' }}</p>
                    </div>
                    </section>

                    <section v-show="activeSettingsTab === 'security'" class="settings-tab-panel">
                        <div class="settings-cache-card">
                        <p class="settings-cache-card__title">Путь входа в админ-панель</p>
                        <div class="page-meta-grid">
                            <label class="admin-form-label">
                                <span>Путь входа в админ-панель</span>
                                <input v-model.trim="form.admin_entry_path" class="admin-input" type="text" placeholder="secure-panel">
                                <small class="muted">Только латиница, цифры и дефис. Без / и без служебных путей вроде api, login, storage.</small>
                                <small class="muted">Итоговая ссылка: {{ adminEntryPreviewUrl }}</small>
                            </label>
                        </div>
                    </div>

                        <div class="settings-cache-card">
                        <p class="settings-cache-card__title">Защита CMS (DDoS / Brute-force / Emergency)</p>
                        <div class="admin-stack">
                            <p class="muted">Готовые профили для быстрого старта:</p>
                            <div class="admin-actions-row">
                                <AdminButton v-for="profile in securityProfiles" :key="profile.key" type="button" @click="applySecurityProfile(profile)">
                                    {{ profile.label }}
                                </AdminButton>
                            </div>
                            <p class="muted" v-for="profile in securityProfiles" :key="`hint-${profile.key}`">{{ profile.label }}: {{ profile.hint }}</p>
                        </div>
                        <div class="page-meta-grid">
                            <label class="admin-form-label">
                                <span><input v-model="form.security_rate_limit_enabled" type="checkbox"> Включить runtime rate-limit</span>
                            </label>

                            <label class="admin-form-label">
                                <span>Лимит запросов в минуту (IP)</span>
                                <input v-model.number="form.security_rate_limit_per_minute" class="admin-input" type="number" min="30" max="2000" step="1" :disabled="!form.security_rate_limit_enabled">
                            </label>

                            <label class="admin-form-label">
                                <span>Burst за 10 сек (IP)</span>
                                <input v-model.number="form.security_rate_limit_burst_per_10s" class="admin-input" type="number" min="10" max="1000" step="1" :disabled="!form.security_rate_limit_enabled">
                            </label>

                            <label class="admin-form-label">
                                <span>Блок IP (сек) после превышения</span>
                                <input v-model.number="form.security_ip_ban_seconds" class="admin-input" type="number" min="60" max="86400" step="1" :disabled="!form.security_rate_limit_enabled">
                            </label>

                            <label class="admin-form-label">
                                <span>Неудачных логинов до lock</span>
                                <input v-model.number="form.security_login_max_attempts" class="admin-input" type="number" min="3" max="20" step="1">
                            </label>

                            <label class="admin-form-label">
                                <span>Окно подсчета login-fail (сек)</span>
                                <input v-model.number="form.security_login_decay_seconds" class="admin-input" type="number" min="30" max="3600" step="1">
                            </label>

                            <label class="admin-form-label">
                                <span>Блок логина (сек)</span>
                                <input v-model.number="form.security_login_ban_seconds" class="admin-input" type="number" min="60" max="86400" step="1">
                            </label>

                            <label class="admin-form-label">
                                <span><input v-model="form.security_emergency_mode" type="checkbox"> Emergency mode (503 для публичной части)</span>
                            </label>

                            <label class="admin-form-label">
                                <span>Текст emergency-режима</span>
                                <textarea v-model="form.security_emergency_message" class="admin-textarea" rows="3" :disabled="!form.security_emergency_mode"></textarea>
                            </label>
                        </div>
                    </div>
                    </section>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving">
                            {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
                        </AdminButton>
                        <AdminButton type="button" variant="danger" :disabled="clearingCache || !canManageCache" @click="clearCache">
                            {{ clearingCache ? 'Очистка...' : 'Очистить кэш сайта и CMS' }}
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