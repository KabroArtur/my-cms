<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { RouterLink } from "vue-router";
import { fetchCurrentUser } from "../../api/auth";
import MediaPickerField from "../../components/media/MediaPickerField.vue";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import AdminSelect from "../../components/ui/AdminSelect.vue";
import AdminCheckbox from "../../components/ui/AdminCheckbox.vue";
import Icon from "../../components/ui/Icon.vue";
import AdminModal from "../../components/ui/AdminModal.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import {
    clearCmsCache,
    createLanguage,
    deleteLanguage,
    fetchSiteSettings,
    updateLanguage,
    updateSiteSettings,
} from "../../api/settings";
import { rememberCmsSettings } from "../../composables/useCmsSettings";

const loading = ref(true);
const saving = ref(false);
const clearingCache = ref(false);
const errorMessage = ref("");
const activeSettingsTab = ref("general");
const adminPathNoticeUrl = ref("");
const canManageAdditionalFields = ref(false);
const canManageGeneral = ref(false);
const canManageAppearance = ref(false);
const canManageCache = ref(false);
const canManageSecurity = ref(false);
const canManageSeo = computed(() => canManageGeneral.value);
const languages = ref([]);
const editingLanguageId = ref(null);
const deleteLanguageModal = ref(false);
const languageToDelete = ref(null);
const defaultLanguage = computed(
    () => languages.value.find((language) => language.is_default) ?? null,
);
const options = reactive({
    date_formats: [],
    time_formats: [],
    themes: [],
    media_variants: [],
    cms_palettes: [],
    admin_theme_modes: [],
    admin_light_palettes: [],
    admin_dark_palettes: [],
    theme_asset_obfuscation_presets: [],
    seo_sitemap_change_frequencies: [],
    seo_canonical_schemes: [],
    seo_canonical_www_modes: [],
    seo_social_networks: [],
    home_pages: [],
    favicon_files: [],
    site_featured_media_files: [],
});

const cacheStats = reactive({
    site_version: null,
    cms_version: null,
    last_cleared_at: null,
});

const languageForm = reactive({
    name: "",
    native_name: "",
    code: "",
    locale: "",
    direction: "ltr",
    is_default: false,
    is_active: true,
    sort_order: 0,
});

const form = reactive({
    site_name: "My CMS",
    favicon_media_id: "",
    site_default_featured_media_id: "",
    date_format: "d.m.Y",
    time_format: "H:i",
    home_page_id: "",
    home_page_ids: {},
    site_theme: "default",
    site_featured_media_variant: "original",
    media_default_insert_variant: "original",
    media_image_optimize: true,
    media_image_max_width: 1920,
    media_image_max_height: 1920,
    media_image_jpg_quality: 82,
    media_image_webp_quality: 80,
    media_image_convert_to_webp: true,
    media_image_keep_original: true,
    media_image_create_thumbnails: true,
    admin_theme_mode: "dark",
    admin_light_palette: "slate",
    admin_dark_palette: "midnight",
    cms_palette: "slate",
    theme_assets_minify_css: true,
    theme_assets_minify_js: true,
    theme_assets_obfuscate_js: false,
    theme_assets_obfuscation_preset: "balanced",
    theme_assets_combine_css: true,
    theme_assets_combine_js: true,
    theme_assets_defer_scripts: true,
    theme_assets_use_hash: true,
    cache_data_enabled: true,
    cache_response_enabled: true,
    cache_response_ttl: 0,
    seo_allow_indexing: true,
    seo_allow_following: true,
    seo_sitemap_enabled: true,
    seo_sitemap_change_frequency: "weekly",
    seo_canonical_scheme: "https",
    seo_canonical_www_mode: "preserve",
    seo_trailing_slash: false,
    seo_open_graph_enabled: true,
    seo_social_networks: [],
    seo_hreflang_enabled: true,
    seo_favicon_enabled: true,
    seo_sitemap_excluded_paths: "",
    seo_robots_custom_rules: "",
    admin_entry_path: "admin",
    security_rate_limit_enabled: true,
    security_rate_limit_per_minute: 180,
    security_rate_limit_burst_per_10s: 45,
    security_ip_ban_seconds: 900,
    security_login_max_attempts: 5,
    security_login_decay_seconds: 120,
    security_login_ban_seconds: 1800,
    security_emergency_mode: false,
    security_emergency_message:
        "Сайт временно переведен в аварийный режим обслуживания. Попробуйте позже.",
});

const adminEntryPreviewUrl = computed(() => {
    const raw = String(form.admin_entry_path ?? "")
        .trim()
        .toLowerCase();
    const normalized = raw.replace(/^\/+|\/+$/g, "");
    const fallback = "admin";
    const safe = normalized.length > 0 ? normalized : fallback;
    const origin = typeof window !== "undefined" ? window.location.origin : "";

    return `${origin}/${safe}`;
});

const publicSiteOrigin = computed(() =>
    typeof window !== "undefined" ? window.location.origin : "",
);
const robotsPreviewUrl = computed(() => `${publicSiteOrigin.value}/robots.txt`);
const sitemapPreviewUrl = computed(
    () => `${publicSiteOrigin.value}/sitemap.xml`,
);

const securityProfiles = [
    {
        key: "low",
        label: "Low traffic",
        hint: "Небольшой сайт до ~20 req/min на IP",
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
        key: "medium",
        label: "Medium traffic",
        hint: "Типовой прод с умеренной нагрузкой",
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
        key: "high",
        label: "High traffic",
        hint: "Высокий трафик/бот-нагрузка, агрессивнее ban",
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
];
const { notifyError, notifySuccess } = useAdminNotifications();

function applySecurityProfile(profile) {
    Object.assign(form, profile.values);
}

function resetLanguageForm() {
    editingLanguageId.value = null;
    languageForm.name = "";
    languageForm.native_name = "";
    languageForm.code = "";
    languageForm.locale = "";
    languageForm.direction = "ltr";
    languageForm.is_active = true;
    languageForm.sort_order = 0;
}

function fillLanguageForm(language) {
    editingLanguageId.value = language?.id ?? null;
    languageForm.name = language?.name ?? "";
    languageForm.native_name = language?.native_name ?? "";
    languageForm.code = language?.code ?? "";
    languageForm.locale = language?.locale ?? "";
    languageForm.direction = language?.direction ?? "ltr";
    languageForm.is_active = language?.is_active ?? true;
    languageForm.sort_order = language?.sort_order ?? 0;
}

let isSwitchingLanguage = false;

async function setDefaultLanguage(language) {
    if (!language || language.is_default || isSwitchingLanguage) return;

    isSwitchingLanguage = true;
    errorMessage.value = "";

    try {
        await updateLanguage(language.id, {
            name: language.name,
            native_name: language.native_name,
            code: language.code,
            locale: language.locale,
            direction: language.direction,
            is_default: true,
            is_active: true,
            sort_order: language.sort_order,
        });

        notifySuccess(`Главный язык переключен на ${language.native_name}.`);

        await loadSettings();
    } catch (error) {
        const msg =
            error.response?.data?.message ??
            Object.values(error.response?.data?.errors ?? {})?.flat?.()[0] ??
            "Не удалось изменить язык по умолчанию.";

        errorMessage.value = msg;
        notifyError(msg);

        console.error(error);
    } finally {
        isSwitchingLanguage = false;
    }
}

function homePageOptionsForLanguage(languageId) {
    return (options.home_pages ?? []).filter(
        (page) => String(page.language_id) === String(languageId),
    );
}

function fillForm(payload) {
    const settings = payload.settings ?? {};

    form.site_name = settings.site_name ?? "My CMS";
    form.favicon_media_id = settings.favicon_media_id ?? "";
    form.site_default_featured_media_id =
        settings.site_default_featured_media_id ?? "";
    form.date_format = settings.date_format ?? "d.m.Y";
    form.time_format = settings.time_format ?? "H:i";
    form.home_page_id = settings.home_page_id ?? "";
    form.home_page_ids = settings.home_page_ids ?? {};
    form.site_theme = settings.site_theme ?? "default";
    form.site_featured_media_variant =
        settings.site_featured_media_variant ?? "original";
    form.media_default_insert_variant =
        settings.media_default_insert_variant ?? "original";
    form.media_image_optimize = settings.media_image_optimize ?? true;
    form.media_image_max_width = settings.media_image_max_width ?? 1920;
    form.media_image_max_height = settings.media_image_max_height ?? 1920;
    form.media_image_jpg_quality = settings.media_image_jpg_quality ?? 82;
    form.media_image_webp_quality = settings.media_image_webp_quality ?? 80;
    form.media_image_convert_to_webp =
        settings.media_image_convert_to_webp ?? true;
    form.media_image_keep_original = settings.media_image_keep_original ?? true;
    form.media_image_create_thumbnails =
        settings.media_image_create_thumbnails ?? true;
    form.admin_theme_mode = settings.admin_theme_mode ?? "dark";
    form.admin_light_palette = settings.admin_light_palette ?? "slate";
    form.admin_dark_palette = settings.admin_dark_palette ?? "midnight";
    form.cms_palette = settings.cms_palette ?? "slate";
    form.theme_assets_minify_css = settings.theme_assets_minify_css ?? true;
    form.theme_assets_minify_js = settings.theme_assets_minify_js ?? true;
    form.theme_assets_obfuscate_js =
        settings.theme_assets_obfuscate_js ?? false;
    form.theme_assets_obfuscation_preset =
        settings.theme_assets_obfuscation_preset ?? "balanced";
    form.theme_assets_combine_css = settings.theme_assets_combine_css ?? true;
    form.theme_assets_combine_js = settings.theme_assets_combine_js ?? true;
    form.theme_assets_defer_scripts =
        settings.theme_assets_defer_scripts ?? true;
    form.theme_assets_use_hash = settings.theme_assets_use_hash ?? true;
    const legacyCacheEnabled = settings.cache_enabled ?? true;
    form.cache_data_enabled = settings.cache_data_enabled ?? legacyCacheEnabled;
    form.cache_response_enabled =
        settings.cache_response_enabled ?? legacyCacheEnabled;
    form.cache_response_ttl = settings.cache_response_ttl ?? 0;
    form.seo_allow_indexing = settings.seo_allow_indexing ?? true;
    form.seo_allow_following = settings.seo_allow_following ?? true;
    form.seo_sitemap_enabled = settings.seo_sitemap_enabled ?? true;
    form.seo_sitemap_change_frequency =
        settings.seo_sitemap_change_frequency ?? "weekly";
    form.seo_canonical_scheme = settings.seo_canonical_scheme ?? "https";
    form.seo_canonical_www_mode = settings.seo_canonical_www_mode ?? "preserve";
    form.seo_trailing_slash = settings.seo_trailing_slash ?? false;
    form.seo_open_graph_enabled = settings.seo_open_graph_enabled ?? true;
    form.seo_social_networks = Array.isArray(settings.seo_social_networks)
        ? [...settings.seo_social_networks]
        : [];
    form.seo_hreflang_enabled = settings.seo_hreflang_enabled ?? true;
    form.seo_favicon_enabled = settings.seo_favicon_enabled ?? true;
    form.seo_sitemap_excluded_paths = settings.seo_sitemap_excluded_paths ?? "";
    form.seo_robots_custom_rules = settings.seo_robots_custom_rules ?? "";
    form.admin_entry_path = settings.admin_entry_path ?? "admin";
    form.security_rate_limit_enabled =
        settings.security_rate_limit_enabled ?? true;
    form.security_rate_limit_per_minute =
        settings.security_rate_limit_per_minute ?? 180;
    form.security_rate_limit_burst_per_10s =
        settings.security_rate_limit_burst_per_10s ?? 45;
    form.security_ip_ban_seconds = settings.security_ip_ban_seconds ?? 900;
    form.security_login_max_attempts =
        settings.security_login_max_attempts ?? 5;
    form.security_login_decay_seconds =
        settings.security_login_decay_seconds ?? 120;
    form.security_login_ban_seconds =
        settings.security_login_ban_seconds ?? 1800;
    form.security_emergency_mode = settings.security_emergency_mode ?? false;
    form.security_emergency_message =
        settings.security_emergency_message ??
        "Сайт временно переведен в аварийный режим обслуживания. Попробуйте позже.";
    Object.assign(
        cacheStats,
        payload.cache ?? {
            site_version: null,
            cms_version: null,
            last_cleared_at: null,
        },
    );
    languages.value = payload.languages ?? [];

    Object.assign(options, payload.options ?? {});
}

async function loadSettings() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const [payload, currentUserPayload] = await Promise.all([
            fetchSiteSettings(),
            fetchCurrentUser(),
        ]);

        fillForm(payload.data);
        rememberCmsSettings(payload.data);
        canManageAdditionalFields.value = (
            currentUserPayload.data?.permissions ?? []
        ).includes("pages.additional_fields.manage");
        canManageGeneral.value =
            payload.data?.permissions?.["settings.general.manage"] === true;
        canManageAppearance.value =
            payload.data?.permissions?.["settings.appearance.manage"] === true;
        canManageCache.value =
            payload.data?.permissions?.["settings.cache.manage"] === true;
        canManageSecurity.value =
            payload.data?.permissions?.["settings.security.manage"] === true;

        if (
            (activeSettingsTab.value === "general" &&
                !canManageGeneral.value) ||
            (activeSettingsTab.value === "appearance" &&
                !canManageAppearance.value) ||
            (activeSettingsTab.value === "languages" &&
                !canManageGeneral.value) ||
            (activeSettingsTab.value === "seo" && !canManageSeo.value) ||
            (activeSettingsTab.value === "cache" && !canManageCache.value) ||
            (activeSettingsTab.value === "security" && !canManageSecurity.value)
        ) {
            if (canManageGeneral.value) {
                activeSettingsTab.value = "general";
            } else if (canManageAppearance.value) {
                activeSettingsTab.value = "appearance";
            } else if (canManageCache.value) {
                activeSettingsTab.value = "cache";
            } else if (canManageSecurity.value) {
                activeSettingsTab.value = "security";
            }
        }
    } catch (error) {
        errorMessage.value = "Не удалось загрузить настройки.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function submitForm() {
    saving.value = true;
    errorMessage.value = "";
    adminPathNoticeUrl.value = "";

    try {
        const submitPayload = { ...form };

        submitPayload.home_page_ids = { ...(form.home_page_ids ?? {}) };
        submitPayload.seo_social_networks = Array.isArray(
            form.seo_social_networks,
        )
            ? [...form.seo_social_networks]
            : [];

        if (defaultLanguage.value) {
            submitPayload.home_page_id =
                submitPayload.home_page_ids[defaultLanguage.value.id] ?? "";
        }

        submitPayload.cms_palette =
            submitPayload.admin_theme_mode === "dark"
                ? submitPayload.admin_dark_palette
                : submitPayload.admin_light_palette;

        if (!canManageGeneral.value) {
            delete submitPayload.site_name;
            delete submitPayload.favicon_media_id;
            delete submitPayload.date_format;
            delete submitPayload.time_format;
            delete submitPayload.home_page_id;
            delete submitPayload.home_page_ids;
            delete submitPayload.site_theme;
            delete submitPayload.seo_allow_indexing;
            delete submitPayload.seo_allow_following;
            delete submitPayload.seo_sitemap_enabled;
            delete submitPayload.seo_sitemap_change_frequency;
            delete submitPayload.seo_canonical_scheme;
            delete submitPayload.seo_canonical_www_mode;
            delete submitPayload.seo_trailing_slash;
            delete submitPayload.seo_open_graph_enabled;
            delete submitPayload.seo_social_networks;
            delete submitPayload.seo_hreflang_enabled;
            delete submitPayload.seo_favicon_enabled;
            delete submitPayload.seo_sitemap_excluded_paths;
            delete submitPayload.seo_robots_custom_rules;
        }

        if (!canManageAppearance.value) {
            delete submitPayload.site_default_featured_media_id;
            delete submitPayload.site_featured_media_variant;
            delete submitPayload.media_default_insert_variant;
            delete submitPayload.media_image_optimize;
            delete submitPayload.media_image_max_width;
            delete submitPayload.media_image_max_height;
            delete submitPayload.media_image_jpg_quality;
            delete submitPayload.media_image_webp_quality;
            delete submitPayload.media_image_convert_to_webp;
            delete submitPayload.media_image_keep_original;
            delete submitPayload.media_image_create_thumbnails;
            delete submitPayload.admin_theme_mode;
            delete submitPayload.admin_light_palette;
            delete submitPayload.admin_dark_palette;
            delete submitPayload.cms_palette;
            delete submitPayload.theme_assets_minify_css;
            delete submitPayload.theme_assets_minify_js;
            delete submitPayload.theme_assets_obfuscate_js;
            delete submitPayload.theme_assets_obfuscation_preset;
            delete submitPayload.theme_assets_combine_css;
            delete submitPayload.theme_assets_combine_js;
            delete submitPayload.theme_assets_defer_scripts;
            delete submitPayload.theme_assets_use_hash;
        }

        if (!canManageCache.value) {
            delete submitPayload.cache_enabled;
            delete submitPayload.cache_data_enabled;
            delete submitPayload.cache_response_enabled;
            delete submitPayload.cache_response_ttl;
        }

        if (!canManageSecurity.value) {
            delete submitPayload.admin_entry_path;
            delete submitPayload.security_rate_limit_enabled;
            delete submitPayload.security_rate_limit_per_minute;
            delete submitPayload.security_rate_limit_burst_per_10s;
            delete submitPayload.security_ip_ban_seconds;
            delete submitPayload.security_login_max_attempts;
            delete submitPayload.security_login_decay_seconds;
            delete submitPayload.security_login_ban_seconds;
            delete submitPayload.security_emergency_mode;
            delete submitPayload.security_emergency_message;
        }

        const payload = await updateSiteSettings(submitPayload);
        fillForm(payload.data);
        rememberCmsSettings(payload.data);

        const nextUrl = payload.data?.admin_path?.new_url_once;

        if (typeof nextUrl === "string" && nextUrl !== "") {
            adminPathNoticeUrl.value = nextUrl;
            notifySuccess("Путь входа изменен. Новый URL показан ниже.", {
                duration: 7000,
            });
            setTimeout(() => {
                window.location.href = nextUrl;
            }, 1200);
        } else {
            notifySuccess("Настройки сохранены.");
        }
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось сохранить настройки.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function clearCache() {
    clearingCache.value = true;
    errorMessage.value = "";

    try {
        const payload = await clearCmsCache();
        Object.assign(cacheStats, payload.data?.cache ?? {});
        notifySuccess(payload.message ?? "Кэш очищен.");
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось очистить кэш.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        clearingCache.value = false;
    }
}

async function submitLanguageForm() {
    errorMessage.value = "";

    try {
        const payload = {
            ...languageForm,
            code: languageForm.code.trim().toLowerCase(),
            locale: languageForm.locale.trim(),
        };

        delete payload.is_default;

        if (editingLanguageId.value) {
            await updateLanguage(editingLanguageId.value, payload);
            notifySuccess("Язык сохранен.");
        } else {
            await createLanguage(payload);
            notifySuccess("Язык добавлен.");
        }

        resetLanguageForm();
        await loadSettings();
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            Object.values(error.response?.data?.errors ?? {})?.flat?.()[0] ??
            "Не удалось сохранить язык.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

function askRemoveLanguage(language) {
    if (language.is_default) {
        notifyError("Нельзя удалить основной язык.");
        return;
    }

    languageToDelete.value = language;
    deleteLanguageModal.value = true;
}

async function confirmRemoveLanguage() {
    if (!languageToDelete.value) return;

    errorMessage.value = "";

    try {
        await deleteLanguage(languageToDelete.value.id);

        notifySuccess("Язык успешно удалён.");

        deleteLanguageModal.value = false;
        languageToDelete.value = null;

        await loadSettings();
    } catch (error) {
        const msg =
            error.response?.data?.message ??
            Object.values(error.response?.data?.errors ?? {})?.flat?.()[0] ??
            "Не удалось удалить язык.";

        errorMessage.value = msg;
        notifyError(msg);

        console.error(error.response ?? error);
    }
}

onMounted(loadSettings);

function languageLabel(language) {
    return `${language.native_name} (${language.code}${
        language.is_default ? ", основной" : ""
    })`;
}

function measureText(text) {
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    const style = getComputedStyle(document.body);

    ctx.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;

    return ctx.measureText(text).width;
}

function measureLanguageWidth(language) {
    let width = measureText(languageLabel(language));

    if (language.is_default) {
        width += 10;
    }

    return Math.ceil(width);
}
</script>

<template>
    <AdminPage
        description="Базовые настройки сайта, медиаслоя и внешнего вида административной системы."
    >
        <template #actions>
            <div class="admin-actions-row">
                <RouterLink
                    v-if="canManageAdditionalFields"
                    :to="{ name: 'content-structure' }"
                    class="button-base"
                >
                    <Icon name="structure" width="18" height="18" />Структура
                    контента
                </RouterLink>
            </div>
        </template>

        <section class="admin-page-grid setting">
            <AdminCard>
                <p v-if="loading" class="muted text-center">
                    Загрузка настроек...
                </p>

                <form
                    v-else
                    class="admin-form-stack pb"
                    @submit.prevent="submitForm"
                >
                    <div class="admin-page-head">
                        <div
                            class="admin-tabs"
                            role="tablist"
                            aria-label="Разделы настроек"
                        >
                            <button
                                v-if="canManageGeneral"
                                type="button"
                                class="admin-tab"
                                :class="{
                                    'is-active':
                                        activeSettingsTab === 'general',
                                }"
                                @click="activeSettingsTab = 'general'"
                            >
                                Общие
                            </button>
                            <button
                                v-if="canManageAppearance"
                                type="button"
                                class="admin-tab"
                                :class="{
                                    'is-active':
                                        activeSettingsTab === 'appearance',
                                }"
                                @click="activeSettingsTab = 'appearance'"
                            >
                                Внешний вид
                            </button>
                            <button
                                v-if="canManageGeneral"
                                type="button"
                                class="admin-tab"
                                :class="{
                                    'is-active':
                                        activeSettingsTab === 'languages',
                                }"
                                @click="activeSettingsTab = 'languages'"
                            >
                                Языки
                            </button>
                            <button
                                v-if="canManageSeo"
                                type="button"
                                class="admin-tab"
                                :class="{
                                    'is-active': activeSettingsTab === 'seo',
                                }"
                                @click="activeSettingsTab = 'seo'"
                            >
                                SEO
                            </button>
                            <button
                                v-if="canManageCache"
                                type="button"
                                class="admin-tab"
                                :class="{
                                    'is-active': activeSettingsTab === 'cache',
                                }"
                                @click="activeSettingsTab = 'cache'"
                            >
                                Кэш
                            </button>
                            <button
                                v-if="canManageSecurity"
                                type="button"
                                class="admin-tab"
                                :class="{
                                    'is-active':
                                        activeSettingsTab === 'security',
                                }"
                                @click="activeSettingsTab = 'security'"
                            >
                                Безопасность
                            </button>
                        </div>
                    </div>

                    <section
                        v-show="activeSettingsTab === 'general'"
                        class="admin-label-block"
                    >
                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3>Основные параметры сайта</h3>
                            </div>
                            <div class="page-meta-grid">
                                <label
                                    class="admin-form-label name-site"
                                    data-label="Название сайта:"
                                >
                                    <input
                                        v-model="form.site_name"
                                        class="admin-input"
                                        type="text"
                                        placeholder="My CMS"
                                    />
                                </label>

                                <AdminSelect
                                    class="data"
                                    data-label="Формат даты:"
                                    :model-value="form.date_format"
                                    :options="options.date_formats"
                                    @update:modelValue="
                                        form.date_format = $event
                                    "
                                />

                                <AdminSelect
                                    data-label="Формат времени:"
                                    :model-value="form.time_format"
                                    :options="options.time_formats"
                                    @update:modelValue="
                                        form.time_format = $event
                                    "
                                    class="time"
                                />
                            </div>
                        </div>

                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3 class="title-tooltip">
                                    Тема и оформление сайта
                                    <Icon
                                        name="info"
                                        width="16"
                                        height="16"
                                    /><span>{{
                                        options.themes.find(
                                            (theme) =>
                                                theme.value === form.site_theme,
                                        )?.description ||
                                        "Выбирает blade-тему публичного сайта."
                                    }}</span>
                                </h3>
                            </div>
                            <div class="page-meta-grid">
                                <AdminSelect
                                    class="topic"
                                    data-label="Тема сайта:"
                                    :model-value="form.site_theme"
                                    :options="options.themes"
                                    @update:modelValue="
                                        form.site_theme = $event
                                    "
                                />
                            </div>
                        </div>

                        <div class="settings-cache-card palette">
                            <div class="admin-stack__head mb">
                                <h3 class="title-tooltip">
                                    Оформление админки
                                    <Icon
                                        name="info"
                                        width="16"
                                        height="16"
                                    /><span
                                        >Определяет цветовую схему и внешний вид
                                        административной панели.</span
                                    >
                                </h3>
                            </div>
                            <div class="page-meta-grid">
                                <AdminSelect
                                    class="mode"
                                    data-label="Режим темы админки:"
                                    v-model="form.admin_theme_mode"
                                    :options="options.admin_theme_modes"
                                />

                                <AdminSelect
                                    class="palette light"
                                    data-label="Палитра для светлой темы:"
                                    v-model="form.admin_light_palette"
                                    :options="options.admin_light_palettes"
                                />

                                <AdminSelect
                                    class="palette dark"
                                    data-label="Палитра для темной темы:"
                                    v-model="form.admin_dark_palette"
                                    :options="options.admin_dark_palettes"
                                />
                            </div>
                        </div>
                    </section>

                    <section
                        v-show="activeSettingsTab === 'appearance'"
                        class="settings-tab-panel settings admin-label-block"
                    >
                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3>Медиа и визуальная идентика</h3>
                            </div>
                            <div class="page-meta-grid settings">
                                <label
                                    class="admin-form-label"
                                    @click.stop.prevent
                                >
                                    <p class="title-tooltip">
                                        Favicon сайта
                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        /><span
                                            >Используется как основной favicon и
                                            база для touch icons и других
                                            размеров.</span
                                        >
                                    </p>
                                    <MediaPickerField
                                        v-model="form.favicon_media_id"
                                        title="Выбрать favicon"
                                        return-type="id"
                                        :allow-upload="true"
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    @click.stop.prevent
                                >
                                    <p class="title-tooltip">
                                        Общая обложка сайта
                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        /><span
                                            >Используется как fallback для
                                            страницы без своей обложки. Если у
                                            страницы задано собственное
                                            изображение, оно имеет приоритет и в
                                            шаблоне, и в Open Graph.</span
                                        >
                                    </p>
                                    <MediaPickerField
                                        v-model="
                                            form.site_default_featured_media_id
                                        "
                                        title="Выбрать общую обложку сайта"
                                        return-type="id"
                                        :allow-upload="true"
                                    />
                                </label>
                            </div>
                            <div class="page-meta-grid mt-16">
                                <AdminSelect
                                    class="size"
                                    data-label="Размер обложки на сайте:"
                                    :model-value="
                                        form.site_featured_media_variant
                                    "
                                    :options="options.media_variants"
                                    @update:modelValue="
                                        form.site_featured_media_variant =
                                            $event
                                    "
                                />

                                <AdminSelect
                                    class="size-default"
                                    data-label="Размер по умолчанию в контенте:"
                                    :model-value="
                                        form.media_default_insert_variant
                                    "
                                    :options="options.media_variants"
                                    @update:modelValue="
                                        form.media_default_insert_variant =
                                            $event
                                    "
                                />
                            </div>
                        </div>

                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3 class="title-tooltip">
                                    Обработка изображений
                                    <Icon
                                        name="info"
                                        width="16"
                                        height="16"
                                    /><span
                                        >Дополнительные размеры берутся из
                                        конфигурации media.images.sizes. Здесь
                                        вы управляете оптимизацией, ограничением
                                        исходника и созданием производных
                                        файлов.</span
                                    >
                                </h3>
                            </div>
                            <div class="page-meta-grid">
                                <AdminCheckbox
                                    :model-value="form.media_image_optimize"
                                    @update:modelValue="
                                        form.media_image_optimize = $event
                                    "
                                >
                                    Автоматическая оптимизация изображений
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="
                                        form.media_image_keep_original
                                    "
                                    @update:modelValue="
                                        form.media_image_keep_original = $event
                                    "
                                >
                                    Сохранять оригинал
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="
                                        form.media_image_convert_to_webp
                                    "
                                    @update:modelValue="
                                        form.media_image_convert_to_webp =
                                            $event
                                    "
                                >
                                    Конвертировать в WebP
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="
                                        form.media_image_create_thumbnails
                                    "
                                    @update:modelValue="
                                        form.media_image_create_thumbnails =
                                            $event
                                    "
                                >
                                    Создавать дополнительные размеры
                                </AdminCheckbox>

                                <label
                                    class="admin-form-label"
                                    data-label="Максимальная ширина:"
                                >
                                    <input
                                        v-model.number="
                                            form.media_image_max_width
                                        "
                                        class="admin-input max"
                                        type="number"
                                        min="320"
                                        max="12000"
                                        step="1"
                                        :disabled="!form.media_image_optimize"
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Максимальная высота:"
                                >
                                    <input
                                        v-model.number="
                                            form.media_image_max_height
                                        "
                                        class="admin-input max"
                                        type="number"
                                        min="320"
                                        max="12000"
                                        step="1"
                                        :disabled="!form.media_image_optimize"
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Качество JPG:"
                                >
                                    <input
                                        v-model.number="
                                            form.media_image_jpg_quality
                                        "
                                        class="admin-input quality"
                                        type="number"
                                        min="30"
                                        max="100"
                                        step="1"
                                        :disabled="!form.media_image_optimize"
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Качество WebP:"
                                >
                                    <input
                                        v-model.number="
                                            form.media_image_webp_quality
                                        "
                                        class="admin-input webp"
                                        type="number"
                                        min="30"
                                        max="100"
                                        step="1"
                                        :disabled="
                                            !form.media_image_optimize ||
                                            !form.media_image_convert_to_webp
                                        "
                                    />
                                </label>
                            </div>
                        </div>

                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3 class="title-tooltip">
                                    Ассеты темы (CSS/JS)<Icon
                                        name="info"
                                        width="16"
                                        height="16"
                                    /><span>
                                        Obfuscation применяется только к
                                        JS-ассетам темы. Для работы нужен
                                        установленный javascript-obfuscator на
                                        сервере; если runtime-obfuscator
                                        недоступен, CMS автоматически вернется к
                                        обычному minify без падения сайта.</span
                                    >
                                </h3>
                            </div>
                            <div class="page-meta-grid">
                                <AdminCheckbox
                                    :model-value="
                                        form.theme_assets_obfuscate_js
                                    "
                                    @update:modelValue="
                                        form.theme_assets_obfuscate_js = $event
                                    "
                                >
                                    Обфусцировать JS
                                </AdminCheckbox>

                                <AdminSelect
                                    class="mode"
                                    data-label="Профиль обфускации:"
                                    :model-value="
                                        form.theme_assets_obfuscation_preset
                                    "
                                    :options="
                                        options.theme_asset_obfuscation_presets
                                    "
                                    :disabled="!form.theme_assets_obfuscate_js"
                                    @update:modelValue="
                                        form.theme_assets_obfuscation_preset =
                                            $event
                                    "
                                />
                                <AdminCheckbox
                                    :model-value="form.theme_assets_minify_css"
                                    @update:modelValue="
                                        form.theme_assets_minify_css = $event
                                    "
                                >
                                    Сжимать CSS
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="form.theme_assets_minify_js"
                                    @update:modelValue="
                                        form.theme_assets_minify_js = $event
                                    "
                                >
                                    Сжимать JS
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="form.theme_assets_combine_css"
                                    @update:modelValue="
                                        form.theme_assets_combine_css = $event
                                    "
                                >
                                    Объединять CSS
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="form.theme_assets_combine_js"
                                    @update:modelValue="
                                        form.theme_assets_combine_js = $event
                                    "
                                >
                                    Объединять JS
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="
                                        form.theme_assets_defer_scripts
                                    "
                                    @update:modelValue="
                                        form.theme_assets_defer_scripts = $event
                                    "
                                >
                                    Defer для JS по умолчанию
                                </AdminCheckbox>

                                <AdminCheckbox
                                    :model-value="form.theme_assets_use_hash"
                                    @update:modelValue="
                                        form.theme_assets_use_hash = $event
                                    "
                                >
                                    Добавлять hash в URL
                                </AdminCheckbox>
                            </div>
                        </div>
                    </section>

                    <section
                        v-show="activeSettingsTab === 'languages'"
                        class="settings-tab-panel settings admin-label-block"
                    >
                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3>Языки сайта</h3>
                            </div>
                            <div class="page-meta-grid">
                                <label
                                    class="admin-form-label language"
                                    data-label="Название:"
                                >
                                    <input
                                        v-model="languageForm.name"
                                        class="admin-input"
                                        type="text"
                                        placeholder="English"
                                    />
                                </label>

                                <label
                                    class="admin-form-label native"
                                    data-label="Нативное название:"
                                >
                                    <input
                                        v-model="languageForm.native_name"
                                        class="admin-input"
                                        type="text"
                                        placeholder="English / Українська"
                                    />
                                </label>

                                <label
                                    class="admin-form-label code"
                                    data-label="Код языка:"
                                >
                                    <input
                                        v-model="languageForm.code"
                                        class="admin-input"
                                        type="text"
                                        placeholder="en"
                                    />
                                </label>

                                <label
                                    class="admin-form-label locale"
                                    data-label="Locale:"
                                >
                                    <input
                                        v-model="languageForm.locale"
                                        class="admin-input"
                                        type="text"
                                        placeholder="en_US"
                                    />
                                </label>

                                <AdminSelect
                                    class="direction"
                                    data-label="Направление текста:"
                                    :model-value="languageForm.direction"
                                    :options="options.language_directions || []"
                                    @update:modelValue="
                                        languageForm.direction = $event
                                    "
                                />

                                <label
                                    class="admin-form-label sort-order"
                                    data-label="Порядок сортировки:"
                                >
                                    <input
                                        v-model.number="languageForm.sort_order"
                                        class="admin-input"
                                        type="number"
                                        min="0"
                                        step="1"
                                    />
                                </label>

                                <label class="admin-form-label">
                                    <AdminCheckbox
                                        :model-value="languageForm.is_active"
                                        @update:modelValue="
                                            languageForm.is_active = $event
                                        "
                                    >
                                        Активный язык
                                    </AdminCheckbox>
                                </label>
                                <div class="flex-end">
                                    <AdminButton
                                        v-if="editingLanguageId"
                                        type="button"
                                        @click="resetLanguageForm"
                                        class="button-danger"
                                    >
                                        <Icon
                                            name="cancel"
                                            width="18"
                                            height="18"
                                        />
                                        Отменить редактирование
                                    </AdminButton>
                                    <AdminButton
                                        type="button"
                                        @click="submitLanguageForm"
                                    >
                                        <Icon
                                            :name="
                                                editingLanguageId
                                                    ? 'save'
                                                    : 'new'
                                            "
                                            width="18"
                                            height="18"
                                        />

                                        {{
                                            editingLanguageId
                                                ? "Сохранить язык"
                                                : "Добавить язык"
                                        }}
                                    </AdminButton>
                                </div>
                            </div>
                        </div>

                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <p class="title-tooltip">
                                    Маршрутизация и главные страницы по языкам
                                    <Icon
                                        name="info"
                                        width="16"
                                        height="16"
                                    /><span
                                        >Для default-языка главная живет на `/`,
                                        для остальных используется код языка:
                                        например `/ua/`, `/en/` и т.д.</span
                                    >
                                </p>
                            </div>
                            <div class="page-meta-grid">
                                <AdminSelect
                                    v-for="language in languages"
                                    :key="language.id"
                                    class="language-homepage"
                                    :data-label="languageLabel(language)"
                                    :style="{
                                        '--label-width': `${measureLanguageWidth(language)}px`,
                                    }"
                                    :model-value="
                                        form.home_page_ids[language.id]
                                    "
                                    :options="[
                                        {
                                            label: 'Автовыбор',
                                            value: '',
                                        },
                                        ...homePageOptionsForLanguage(
                                            language.id,
                                        ),
                                    ]"
                                    @update:modelValue="
                                        form.home_page_ids[language.id] = $event
                                    "
                                />
                            </div>
                        </div>

                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3>Список языков</h3>
                            </div>
                            <p v-if="languages.length === 0" class="muted">
                                Языки пока не добавлены.
                            </p>
                            <div v-else class="page-trash-grid">
                                <article
                                    v-for="language in languages"
                                    :key="language.id"
                                    class="page-trash-card settings"
                                >
                                    <div class="cell-page">
                                        <h3>
                                            {{ language.native_name }}
                                            <span>({{ language.code }})</span>
                                        </h3>
                                        <p>
                                            {{ language.name }} |
                                            {{ language.locale }} |
                                            {{ language.direction }} | sort
                                            {{ language.sort_order }}
                                        </p>
                                    </div>

                                    <p class="plugin-status">
                                        <small>
                                            {{
                                                language.is_default
                                                    ? "По умолчанию"
                                                    : "Не по умолчанию"
                                            }}
                                            |</small
                                        >
                                        <span
                                            class="plugin-status-badge"
                                            :class="
                                                language.is_active
                                                    ? 'is-enabled'
                                                    : 'is-disabled'
                                            "
                                        >
                                            {{
                                                language.is_active
                                                    ? "Активен"
                                                    : "Выключен"
                                            }}
                                        </span>
                                    </p>

                                    <div class="cell-actions">
                                        <label
                                            class="settings-default-language-toggle"
                                            :class="{
                                                active: language.is_default,
                                            }"
                                            @click="
                                                setDefaultLanguage(language)
                                            "
                                        >
                                            <input
                                                type="radio"
                                                name="default-language"
                                                :checked="language.is_default"
                                            />

                                            <span class="fake-radio"></span>

                                            <span class="text">Главный</span>
                                        </label>
                                        <div class="flex-center">
                                            <AdminButton
                                                type="button"
                                                @click="
                                                    fillLanguageForm(language)
                                                "
                                                title="Редактировать"
                                                class="button-link"
                                            >
                                                <Icon
                                                    name="pencil"
                                                    width="20"
                                                    height="20"
                                                />
                                            </AdminButton>
                                            <AdminButton
                                                type="button"
                                                variant="danger"
                                                @click="
                                                    askRemoveLanguage(language)
                                                "
                                                title="Удалить"
                                            >
                                                <Icon
                                                    name="trash"
                                                    width="20"
                                                    height="20"
                                                />
                                            </AdminButton>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section
                        v-show="activeSettingsTab === 'seo'"
                        class="settings-tab-panel settings admin-label-block"
                    >
                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3>Индексация и базовые meta-правила</h3>
                            </div>
                            <div class="page-meta-grid">
                                <AdminCheckbox
                                    v-model="form.seo_allow_indexing"
                                >
                                    <p class="title-tooltip-down">
                                        Разрешить индексирование страниц

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            Управляет `meta robots` и главным
                                            правилом в robots.txt автоматически.
                                        </strong>
                                    </p>
                                </AdminCheckbox>

                                <AdminCheckbox
                                    v-model="form.seo_open_graph_enabled"
                                >
                                    <p class="title-tooltip-down">
                                        Выводить Open Graph / social meta

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            Добавляет og:title, og:description,
                                            og:image и, при выборе X,
                                            twitter-card теги.
                                        </strong>
                                    </p>
                                </AdminCheckbox>

                                <AdminCheckbox
                                    v-model="form.seo_allow_following"
                                >
                                    <p class="title-tooltip-down">
                                        Разрешить роботам переходить по ссылкам

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            При выключении страницы
                                            автоматически отдают `nofollow`.
                                        </strong>
                                    </p>
                                </AdminCheckbox>

                                <AdminCheckbox
                                    v-model="form.seo_favicon_enabled"
                                >
                                    <p class="title-tooltip-down">
                                        Выводить favicon и touch icons

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            При сохранении CMS подготовит набор
                                            размеров 16, 32, 180, 192 и 512 px
                                            для выбранного favicon.
                                        </strong>
                                    </p>
                                </AdminCheckbox>

                                <AdminCheckbox
                                    v-model="form.seo_hreflang_enabled"
                                >
                                    <p class="title-tooltip-down">
                                        Выводить hreflang для мультиязычных
                                        страниц

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            Теги появятся только если активных
                                            языков больше одного и у страницы
                                            есть переводы.
                                        </strong>
                                    </p>
                                </AdminCheckbox>
                            </div>
                            <label class="admin-form-label mt-28">
                                <p class="title-tooltip">
                                    Соцсети, для которых готовить разметку
                                    <Icon name="info" width="16" height="16" />
                                    <span
                                        >Facebook, LinkedIn, Telegram и WhatsApp
                                        используют OG-теги. Для X дополнительно
                                        выводятся twitter-card теги.
                                    </span>
                                </p>

                                <div class="settings-checkbox-grid">
                                    <AdminCheckbox
                                        v-for="network in options.seo_social_networks ||
                                        []"
                                        :key="network.value"
                                        v-model="form.seo_social_networks"
                                        :value="network.value"
                                    >
                                        {{ network.label }}
                                    </AdminCheckbox>
                                </div>
                            </label>
                        </div>

                        <div class="settings-cache-card mt-16">
                            <div class="admin-stack__head mb">
                                <h3>Канонический домен и структура URL</h3>
                            </div>
                            <div class="page-meta-grid url">
                                <div class="mr-12">
                                    <AdminSelect
                                        class="protocol"
                                        data-label="Основной протокол:"
                                        v-model="form.seo_canonical_scheme"
                                        :options="options.seo_canonical_schemes"
                                    />
                                    <p class="title-tooltip">
                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <span>
                                            Используется в canonical URL,
                                            robots.txt и sitemap, чтобы не было
                                            смешения http и https.
                                        </span>
                                    </p>
                                </div>

                                <div class="mr-12">
                                    <AdminSelect
                                        class="www"
                                        data-label="Режим www:"
                                        v-model="form.seo_canonical_www_mode"
                                        :options="
                                            options.seo_canonical_www_modes
                                        "
                                    />

                                    <p class="title-tooltip-left">
                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <span>
                                            Переключает основной домен на
                                            вариант с www или без www поверх
                                            APP_URL.
                                        </span>
                                    </p>
                                </div>

                                <AdminCheckbox
                                    v-model="form.seo_trailing_slash"
                                >
                                    <p class="title-tooltip-down">
                                        Добавлять завершающий слеш в URL и
                                        canonical для всех страниц, кроме
                                        главной

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            Влияет на canonical, hreflang,
                                            sitemap и публичные ссылки CMS.
                                            Главные страницы языков остаются без
                                            дополнительного хвостового слеша.
                                        </strong>
                                    </p>
                                </AdminCheckbox>
                            </div>
                        </div>

                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb mt-16">
                                <p class="title-tooltip">
                                    Sitemap и robots.txt
                                    <Icon name="info" width="16" height="16" />

                                    <span>
                                        Sitemap отдает index-файл и при росте
                                        количества страниц автоматически
                                        разбивается на несколько частей.
                                    </span>
                                </p>
                            </div>
                            <div class="admin-column">
                                <AdminCheckbox
                                    v-model="form.seo_sitemap_enabled"
                                >
                                    <p class="title-tooltip-down">
                                        Публиковать sitemap.xml автоматически

                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        />

                                        <strong>
                                            Карта сайта собирается автоматически
                                            из опубликованных публичных страниц
                                            CMS.
                                        </strong>
                                    </p>
                                </AdminCheckbox>
                                <div class="page-meta-grid">
                                    <div class="mr-12">
                                        <AdminSelect
                                            class="sitemap"
                                            data-label="Частота обновления sitemap:"
                                            v-model="
                                                form.seo_sitemap_change_frequency
                                            "
                                            :options="
                                                options.seo_sitemap_change_frequencies
                                            "
                                            :disabled="
                                                !form.seo_sitemap_enabled
                                            "
                                        />

                                        <p class="title-tooltip">
                                            <Icon
                                                name="info"
                                                width="16"
                                                height="16"
                                            />

                                            <span>
                                                Поле записывается в
                                                `changefreq`, чтобы поисковые
                                                системы понимали ожидаемую
                                                динамику сайта.
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <p class="title-tooltip">
                                    Исключить пути из sitemap
                                    <Icon name="info" width="16" height="16" />

                                    <span
                                        >По одному пути на строку, без домена.
                                        Например `privacy-policy` исключит
                                        страницу `/privacy-policy`.
                                    </span>
                                </p>
                                <textarea
                                    v-model="form.seo_sitemap_excluded_paths"
                                    class="admin-textarea"
                                    rows="2"
                                    placeholder="privacy-policy\nthanks\nsecret/landing"
                                ></textarea>

                                <p class="title-tooltip">
                                    Дополнительные правила robots.txt
                                    <Icon name="info" width="16" height="16" />

                                    <span
                                        >Необязательно. Этот блок будет добавлен
                                        после автоматически сгенерированных
                                        правил.
                                    </span>
                                </p>
                                <textarea
                                    v-model="form.seo_robots_custom_rules"
                                    class="admin-textarea"
                                    rows="3"
                                    placeholder="Disallow: /admin/\nAllow: /storage/\nCrawl-delay: 5"
                                ></textarea>
                            </div>

                            <p class="mt-16">
                                Robots:
                                <strong
                                    ><a
                                        :href="robotsPreviewUrl"
                                        target="_blank"
                                        rel="noopener"
                                        >{{ robotsPreviewUrl }}</a
                                    ></strong
                                >
                            </p>
                            <p>
                                Sitemap:
                                <strong
                                    ><a
                                        :href="sitemapPreviewUrl"
                                        target="_blank"
                                        rel="noopener"
                                        >{{ sitemapPreviewUrl }}</a
                                    ></strong
                                >
                            </p>
                        </div>
                    </section>

                    <p v-if="errorMessage" class="error-text">
                        {{ errorMessage }}
                    </p>
                    <p v-if="adminPathNoticeUrl" class="muted">
                        Новый URL админки:
                        <a :href="adminPathNoticeUrl">{{
                            adminPathNoticeUrl
                        }}</a>
                    </p>

                    <section
                        v-show="activeSettingsTab === 'cache'"
                        class="settings-tab-panel settings admin-label-block"
                    >
                        <div class="settings-cache-card settings">
                            <div class="admin-stack__head mb">
                                <h3>Кэш сайта и CMS</h3>
                            </div>
                            <div class="page-meta-grid">
                                <label
                                    class="admin-form-label cache mr-12"
                                    data-label="TTL full-page cache (сек):"
                                >
                                    <input
                                        v-model.number="form.cache_response_ttl"
                                        class="admin-input"
                                        type="number"
                                        min="0"
                                        step="1"
                                        :disabled="!form.cache_response_enabled"
                                    />
                                    <p class="title-tooltip">
                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        /><span
                                            >0 = бессрочно, с инвалидацией по
                                            изменениям контента/настроек.</span
                                        >
                                    </p>
                                </label>
                            </div>
                            <div class="cache-grid">
                                <p class="plugin-status">
                                    <small> Data cache (форма) | </small>

                                    <span
                                        class="plugin-status-badge"
                                        :class="
                                            form.cache_data_enabled
                                                ? 'is-enabled'
                                                : 'is-disabled'
                                        "
                                    >
                                        {{
                                            form.cache_data_enabled
                                                ? "включен"
                                                : "выключен"
                                        }}
                                    </span>
                                </p>

                                <p class="plugin-status">
                                    <small> Full-page (форма) | </small>

                                    <span
                                        class="plugin-status-badge"
                                        :class="
                                            form.cache_response_enabled
                                                ? 'is-enabled'
                                                : 'is-disabled'
                                        "
                                    >
                                        {{
                                            form.cache_response_enabled
                                                ? "включен"
                                                : "выключен"
                                        }}
                                    </span>
                                </p>

                                <p class="plugin-status">
                                    <small> Data cache (сервер) | </small>

                                    <span
                                        class="plugin-status-badge"
                                        :class="
                                            cacheStats.data_enabled === true
                                                ? 'is-enabled'
                                                : cacheStats.data_enabled ===
                                                    false
                                                  ? 'is-disabled'
                                                  : ''
                                        "
                                    >
                                        {{
                                            cacheStats.data_enabled === true
                                                ? "включен"
                                                : cacheStats.data_enabled ===
                                                    false
                                                  ? "выключен"
                                                  : "—"
                                        }}
                                    </span>
                                </p>

                                <p class="plugin-status">
                                    <small> Full-page (сервер) | </small>

                                    <span
                                        class="plugin-status-badge"
                                        :class="
                                            cacheStats.response_enabled === true
                                                ? 'is-enabled'
                                                : cacheStats.response_enabled ===
                                                    false
                                                  ? 'is-disabled'
                                                  : ''
                                        "
                                    >
                                        {{
                                            cacheStats.response_enabled === true
                                                ? "включен"
                                                : cacheStats.response_enabled ===
                                                    false
                                                  ? "выключен"
                                                  : "—"
                                        }}
                                    </span>
                                </p>

                                <p class="plugin-status">
                                    <small>
                                        Site version:
                                        {{ cacheStats.site_version ?? "—" }} |
                                        CMS version:
                                        {{ cacheStats.cms_version ?? "—" }}
                                    </small>
                                </p>
                            </div>

                            <div class="page-meta-grid">
                                <div>
                                    <AdminCheckbox
                                        v-model="form.cache_data_enabled"
                                    >
                                        Data cache (модули, настройки,
                                        вычисления)
                                    </AdminCheckbox>

                                    <AdminCheckbox
                                        v-model="form.cache_response_enabled"
                                    >
                                        Full-page cache (готовый HTML)
                                    </AdminCheckbox>
                                </div>
                                <p class="text-end">
                                    <strong>Последняя очистка:</strong>
                                    {{
                                        cacheStats.last_cleared_at ??
                                        "нет данных"
                                    }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-show="activeSettingsTab === 'security'"
                        class="settings-tab-panel settings admin-label-block"
                    >
                        <div class="settings-cache-card">
                            <div class="admin-stack__head mb">
                                <h3>Путь входа в админ-панель</h3>
                            </div>
                            <div class="page-meta-grid">
                                <label
                                    class="admin-form-label mr-12"
                                    data-label="Путь входа:"
                                >
                                    <input
                                        v-model.trim="form.admin_entry_path"
                                        class="admin-input path"
                                        type="text"
                                        placeholder="secure-panel"
                                    />
                                    <p class="title-tooltip">
                                        <Icon
                                            name="info"
                                            width="16"
                                            height="16"
                                        /><span
                                            >Только латиница, цифры и дефис. Без
                                            / и без служебных путей вроде api,
                                            login, storage.</span
                                        >
                                    </p>
                                    <p class="page-editor__full-url">
                                        Итоговая ссылка:
                                        <strong>{{
                                            adminEntryPreviewUrl
                                        }}</strong>
                                    </p>
                                </label>
                            </div>
                        </div>

                        <div class="settings-cache-card mt-16">
                            <div class="admin-stack__head mb">
                                <h3>
                                    Защита CMS (DDoS / Brute-force / Emergency)
                                </h3>
                            </div>
                            <div class="admin-stack pi-0">
                                <p>Готовые профили для быстрого старта:</p>
                                <div class="admin-tabs">
                                    <AdminButton
                                        v-for="profile in securityProfiles"
                                        :key="profile.key"
                                        type="button"
                                        class="admin-tab"
                                        :class="{
                                            'is-active':
                                                selectedSecurityProfile ===
                                                profile.key,
                                        }"
                                        @click="
                                            selectedSecurityProfile =
                                                profile.key;
                                            applySecurityProfile(profile);
                                        "
                                    >
                                        {{ profile.label }}
                                    </AdminButton>
                                </div>
                                <p
                                    v-for="profile in securityProfiles"
                                    :key="`hint-${profile.key}`"
                                >
                                    <strong>{{ profile.label }}</strong
                                    >: {{ profile.hint }}
                                </p>
                            </div>
                            <div class="page-meta-grid">
                                <label
                                    class="admin-form-label"
                                    data-label="Лимит запросов в минуту (IP):"
                                >
                                    <input
                                        v-model.number="
                                            form.security_rate_limit_per_minute
                                        "
                                        class="admin-input limit"
                                        type="number"
                                        min="30"
                                        max="2000"
                                        step="1"
                                        :disabled="
                                            !form.security_rate_limit_enabled
                                        "
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Burst за 10 сек (IP):"
                                >
                                    <input
                                        v-model.number="
                                            form.security_rate_limit_burst_per_10s
                                        "
                                        class="admin-input burst"
                                        type="number"
                                        min="10"
                                        max="1000"
                                        step="1"
                                        :disabled="
                                            !form.security_rate_limit_enabled
                                        "
                                    />
                                </label>

                                <label
                                    class="admin-form-label block"
                                    data-label="Блок IP (сек) после превышения:"
                                >
                                    <input
                                        v-model.number="
                                            form.security_ip_ban_seconds
                                        "
                                        class="admin-input block"
                                        type="number"
                                        min="60"
                                        max="86400"
                                        step="1"
                                        :disabled="
                                            !form.security_rate_limit_enabled
                                        "
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Неудачных логинов до lock:"
                                >
                                    <input
                                        v-model.number="
                                            form.security_login_max_attempts
                                        "
                                        class="admin-input lock"
                                        type="number"
                                        min="3"
                                        max="20"
                                        step="1"
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Окно подсчета login-fail (сек):"
                                >
                                    <input
                                        v-model.number="
                                            form.security_login_decay_seconds
                                        "
                                        class="admin-input login-fail"
                                        type="number"
                                        min="30"
                                        max="3600"
                                        step="1"
                                    />
                                </label>

                                <label
                                    class="admin-form-label"
                                    data-label="Блок логина (сек):"
                                >
                                    <input
                                        v-model.number="
                                            form.security_login_ban_seconds
                                        "
                                        class="admin-input log"
                                        type="number"
                                        min="60"
                                        max="86400"
                                        step="1"
                                    />
                                </label>

                                <AdminCheckbox
                                    v-model="form.security_rate_limit_enabled"
                                >
                                    Включить runtime rate-limit
                                </AdminCheckbox>

                                <AdminCheckbox
                                    v-model="form.security_emergency_mode"
                                >
                                    Emergency mode (503 для публичной части)
                                </AdminCheckbox>
                            </div>
                            <label
                                class="admin-form-label mt-16"
                                data-label="Текст для emergency-режима:"
                            >
                                <textarea
                                    v-model="form.security_emergency_message"
                                    class="admin-textarea"
                                    rows="3"
                                    :disabled="!form.security_emergency_mode"
                                    placeholder=""
                                ></textarea>
                            </label>
                        </div>
                    </section>

                    <div class="admin-buttons">
                        <AdminButton
                            type="submit"
                            variant="primary"
                            :disabled="saving"
                        >
                            <Icon name="save" width="18" height="18" />{{
                                saving ? "Сохранение..." : "Сохранить настройки"
                            }}
                        </AdminButton>
                        <AdminButton
                            type="button"
                            variant="danger"
                            :disabled="clearingCache || !canManageCache"
                            @click="clearCache"
                        >
                            <Icon name="clean" width="22" height="22" />{{
                                clearingCache
                                    ? "Очистка..."
                                    : "Очистить кэш сайта и CMS"
                            }}
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>
        </section>
    </AdminPage>
    <AdminModal
        :open="deleteLanguageModal"
        title="Удаление языка"
        @close="deleteLanguageModal = false"
    >
        <p v-if="languageToDelete">
            Удалить
            <strong>{{ languageToDelete.native_name }}</strong> язык?
        </p>

        <template #footer>
            <div class="admin-actions-row">
                <AdminButton type="button" @click="deleteLanguageModal = false">
                    Отмена
                </AdminButton>

                <AdminButton
                    type="button"
                    class="button-danger"
                    @click="confirmRemoveLanguage"
                >
                    Удалить
                </AdminButton>
            </div>
        </template>
    </AdminModal>
</template>
