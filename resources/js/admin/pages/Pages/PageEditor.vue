<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import { EditorContent, useEditor } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";
import Image from "@tiptap/extension-image";
import Underline from "@tiptap/extension-underline";
import TextAlign from "@tiptap/extension-text-align";
import Highlight from "@tiptap/extension-highlight";
import TaskList from "@tiptap/extension-task-list";
import TaskItem from "@tiptap/extension-task-item";
import HorizontalRule from "@tiptap/extension-horizontal-rule";
import Subscript from "@tiptap/extension-subscript";
import Superscript from "@tiptap/extension-superscript";
import Placeholder from "@tiptap/extension-placeholder";
import {
    Table,
    TableRow,
    TableHeader,
    TableCell,
} from "@tiptap/extension-table";
import VideoNode from "../../components/editor/VideoNode";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { fetchCurrentUser } from "../../api/auth";
import CustomFieldRenderer from "../../components/custom-fields/CustomFieldRenderer.vue";
import MediaLibraryModal from "../../components/media/MediaLibraryModal.vue";
import {
    cloneValue,
    defaultValueForField,
} from "../../components/custom-fields/customFields";
import MediaPickerField from "../../components/media/MediaPickerField.vue";
import { DEFAULT_MEDIA_LIBRARY_ACCEPT } from "../../components/media/mediaHelpers";
import { buildEmbeddedMediaMarkup } from "../../components/media/mediaEmbeds";
import PageContentToolbar from "../../components/ui/PageContentToolbar.vue";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import Icon from "../../components/ui/Icon.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import { fetchApplicableAdditionalFields } from "../../api/additionalFields";
import { loadCmsSettings } from "../../composables/useCmsSettings";
import {
    createPage,
    fetchPage,
    fetchPageTree,
    updatePage,
} from "../../api/pages";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref("");
const validationErrors = ref({});
const slugLocked = ref(false);
const allPages = ref([]);
const creatorLabel = ref("Не указан");
const canUpdatePage = ref(true);
const additionalFieldGroups = ref([]);
const additionalFieldValues = ref({});
const additionalFieldsRequestToken = ref(0);
const canManageAdditionalFields = ref(false);
const templateOptions = ref([
    {
        value: "default",
        label: "По умолчанию",
        description: "Основной шаблон темы",
    },
]);
const languageOptions = ref([]);
const trailingSlashEnabled = ref(false);
const contentVisible = ref(true);
const contentMode = ref("visual");
const contentMediaModalOpen = ref(false);
const headingLevels = [1, 2, 3, 4, 5, 6];
const { notifyError, notifySuccess } = useAdminNotifications();

const form = reactive({
    language_id: "",
    translation_group_id: "",
    title: "",
    slug: "",
    parent_id: "",
    excerpt: "",
    status: "draft",
    visibility: "public",
    is_home: false,
    published_at: "",
    template: "default",
    meta_title: "",
    meta_description: "",
    seo_noindex: false,
    seo_nofollow: false,
    featured_media_id: "",
    content: "",
});

const contentEditor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3, 4, 5, 6],
            },
        }),
        Underline,
        Highlight,
        Subscript,
        Superscript,
        HorizontalRule,
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        TextAlign.configure({
            types: ["heading", "paragraph"],
            alignments: ["left", "center", "right", "justify"],
            defaultAlignment: "left",
        }),
        Placeholder.configure({
            placeholder: "Начните писать контент страницы...",
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            protocols: ["http", "https", "mailto", "tel"],
        }),
        Image,
        VideoNode,
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    content: "",
    onUpdate({ editor }) {
        form.content = editor.getHTML();
    },
});

const isCreateMode = computed(() => route.name === "page-create");
const pageId = computed(() => route.params.id);
const pageTitle = computed(() =>
    isCreateMode.value ? "Новая страница" : `Страница #${pageId.value}`,
);
const selectedLanguage = computed(
    () =>
        languageOptions.value.find(
            (language) => String(language.value) === String(form.language_id),
        ) ?? null,
);
const availableParents = computed(() =>
    allPages.value.filter(
        (page) =>
            String(page.id) !== String(pageId.value) &&
            (form.language_id === "" ||
                String(page.language_id) === String(form.language_id)),
    ),
);
const resolvedParent = computed(
    () =>
        availableParents.value.find(
            (page) => String(page.id) === String(form.parent_id),
        ) ?? null,
);
const publicUrl = computed(() => {
    const segments = [];
    const usesPrefix =
        selectedLanguage.value && !selectedLanguage.value.is_default;

    if (usesPrefix) {
        segments.push(selectedLanguage.value.code);
    }

    if (form.is_home) {
        return segments.length > 0 ? `/${segments.join("/")}` : "/";
    }

    if (resolvedParent.value?.path) {
        segments.push(resolvedParent.value.path);
    }

    if (form.slug) {
        segments.push(form.slug);
    }

    if (segments.length === 0) {
        return "—";
    }

    const path = `/${segments.join("/")}`;

    return trailingSlashEnabled.value ? `${path.replace(/\/+$/, "")}/` : path;
});
const canOpenPublicPage = computed(
    () => form.is_home || form.slug.trim() !== "",
);

const transliterationMap = {
    А: "A",
    а: "a",
    Б: "B",
    б: "b",
    В: "V",
    в: "v",
    Г: "G",
    г: "g",
    Д: "D",
    д: "d",
    Е: "E",
    е: "e",
    Ё: "E",
    ё: "e",
    Ж: "Zh",
    ж: "zh",
    З: "Z",
    з: "z",
    И: "I",
    и: "i",
    Й: "Y",
    й: "y",
    К: "K",
    к: "k",
    Л: "L",
    л: "l",
    М: "M",
    м: "m",
    Н: "N",
    н: "n",
    О: "O",
    о: "o",
    П: "P",
    п: "p",
    Р: "R",
    р: "r",
    С: "S",
    с: "s",
    Т: "T",
    т: "t",
    У: "U",
    у: "u",
    Ф: "F",
    ф: "f",
    Х: "Kh",
    х: "kh",
    Ц: "Ts",
    ц: "ts",
    Ч: "Ch",
    ч: "ch",
    Ш: "Sh",
    ш: "sh",
    Щ: "Shch",
    щ: "shch",
    Ъ: "",
    ъ: "",
    Ы: "Y",
    ы: "y",
    Ь: "",
    ь: "",
    Э: "E",
    э: "e",
    Ю: "Yu",
    ю: "yu",
    Я: "Ya",
    я: "ya",
    І: "I",
    і: "i",
    Ї: "Yi",
    ї: "yi",
    Є: "Ye",
    є: "ye",
    Ґ: "G",
    ґ: "g",
};

function formatDateTimeLocalValue(value) {
    if (!value) {
        return "";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "";
    }

    const timezoneOffset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
}

function normalizeSlug(value, { trimEdges = true } = {}) {
    const source = String(value)
        .split("")
        .map((character) => transliterationMap[character] ?? character)
        .join("");

    const normalized = source
        .toLowerCase()
        .trim()
        .replace(/['’]+/g, "")
        .replace(/\//g, "-")
        .replace(/\s+/g, "-")
        .replace(/[^a-z0-9-]+/g, "-")
        .replace(/-+/g, "-");

    if (!trimEdges) {
        return normalized;
    }

    return normalized.replace(/^-|-$/g, "");
}

function syncSlugFromTitle() {
    if (slugLocked.value) {
        return;
    }

    form.slug = normalizeSlug(form.title);
}

function handleSlugInput() {
    form.slug = normalizeSlug(form.slug, { trimEdges: false });
    slugLocked.value = form.slug.trim() !== "";
}

function fillForm(page) {
    form.language_id =
        page.language_id ??
        languageOptions.value.find((language) => language.is_default)?.value ??
        "";
    form.translation_group_id = page.translation_group_id ?? "";
    form.title = page.title ?? "";
    form.slug = page.slug ?? "";
    form.parent_id = page.parent_id ?? "";
    form.excerpt = page.excerpt ?? "";
    form.status = page.status ?? "draft";
    form.visibility = page.visibility ?? "public";
    form.is_home = page.is_home ?? false;
    form.published_at = formatDateTimeLocalValue(page.published_at);
    form.template = page.template || "default";
    form.meta_title = page.meta_title ?? "";
    form.meta_description = page.meta_description ?? "";
    form.seo_noindex = page.seo_noindex ?? false;
    form.seo_nofollow = page.seo_nofollow ?? false;
    form.featured_media_id = page.featured_media_id ?? "";
    form.content = page.content ?? "";
    creatorLabel.value =
        page.creator?.name || page.creator?.username || "Не указан";
    canUpdatePage.value = page.can?.update ?? true;
    slugLocked.value = form.slug.trim() !== "";

    if (contentEditor.value) {
        contentEditor.value.commands.setContent(form.content || "", false);
    }

    additionalFieldGroups.value = page.additional_fields?.groups ?? [];
    additionalFieldValues.value = page.additional_fields?.values ?? {};
}

function ensureFieldValue(field) {
    const key = String(field?.key ?? "");

    if (key === "") {
        return;
    }

    if (!(key in additionalFieldValues.value)) {
        additionalFieldValues.value[key] = defaultValueForField(field);
    }
}

function hydrateAdditionalFields(groups, values = {}) {
    additionalFieldGroups.value = Array.isArray(groups) ? groups : [];
    additionalFieldValues.value = Object.fromEntries(
        Object.entries(values || {}).map(([key, value]) => [
            key,
            cloneValue(value),
        ]),
    );

    additionalFieldGroups.value.forEach((group) => {
        const fields = Array.isArray(group.fields) ? group.fields : [];

        fields.forEach((field) => ensureFieldValue(field));
    });
}

function updateAdditionalFieldValue(key, value) {
    additionalFieldValues.value = {
        ...additionalFieldValues.value,
        [key]: cloneValue(value),
    };
}

function applicableFieldKeys(groups) {
    return new Set(
        (Array.isArray(groups) ? groups : [])
            .flatMap((group) =>
                Array.isArray(group.fields) ? group.fields : [],
            )
            .map((field) => String(field?.key ?? ""))
            .filter(Boolean),
    );
}

function valuesForApplicableGroups(values, groups) {
    const keys = applicableFieldKeys(groups);

    return Object.fromEntries(
        Object.entries(values || {}).filter(([key]) => keys.has(key)),
    );
}

async function loadApplicableAdditionalFields() {
    const requestToken = additionalFieldsRequestToken.value + 1;
    additionalFieldsRequestToken.value = requestToken;

    try {
        const payload = await fetchApplicableAdditionalFields({
            page_id: isCreateMode.value ? undefined : pageId.value,
            template: form.template || "default",
        });

        if (additionalFieldsRequestToken.value !== requestToken) {
            return;
        }

        const groups = payload.data?.groups ?? [];
        const values = payload.data?.values ?? {};
        const currentValues = valuesForApplicableGroups(
            additionalFieldValues.value,
            groups,
        );

        hydrateAdditionalFields(groups, {
            ...values,
            ...currentValues,
        });
    } catch (error) {
        console.error(error);
    }
}

async function loadEditorSettings() {
    try {
        const settingsPayload = await loadCmsSettings();
        templateOptions.value =
            settingsPayload.options?.page_templates ?? templateOptions.value;
        languageOptions.value = settingsPayload.options?.languages ?? [];
        trailingSlashEnabled.value =
            settingsPayload.settings?.seo_trailing_slash === true;

        if (isCreateMode.value && !form.language_id) {
            form.language_id =
                languageOptions.value.find((language) => language.is_default)
                    ?.value ?? "";
        }
    } catch (error) {
        console.error(error);
    }
}

function resetForm() {
    fillForm({});
    form.language_id =
        languageOptions.value.find((language) => language.is_default)?.value ??
        "";
    form.translation_group_id = "";
    form.status = "draft";
    form.visibility = "public";
    form.is_home = false;
    creatorLabel.value = "Вы";
    canUpdatePage.value = true;
    slugLocked.value = false;
    additionalFieldGroups.value = [];
    additionalFieldValues.value = {};
    validationErrors.value = {};
    errorMessage.value = "";
}

function setContentMode(mode) {
    if (!["visual", "source"].includes(mode)) {
        return;
    }

    if (mode === "visual" && contentEditor.value) {
        const currentHtml = contentEditor.value.getHTML();

        if (currentHtml !== (form.content || "")) {
            contentEditor.value.commands.setContent(form.content || "", false);
        }
    }

    contentMode.value = mode;
}

function openContentMediaModal() {
    contentMediaModalOpen.value = true;
}

function handleContentMediaInsert(selection) {
    const items = (Array.isArray(selection) ? selection : [selection]).filter(Boolean);

    if (items.length === 0 || !contentEditor.value) {
        contentMediaModalOpen.value = false;
        return;
    }

    const markup = items.map((item) => buildEmbeddedMediaMarkup(item)).join("");

    if (markup !== "") {
        contentEditor.value.chain().focus().insertContent(markup).run();
        form.content = contentEditor.value.getHTML();
    }

    contentMediaModalOpen.value = false;
}

async function loadPage() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const currentUserPromise = fetchCurrentUser();
        const pagesPayloadPromise = fetchPageTree();

        if (isCreateMode.value) {
            const pagesPayload = await pagesPayloadPromise;
            const currentUserPayload = await currentUserPromise;
            allPages.value = pagesPayload.data ?? [];
            canManageAdditionalFields.value = (
                currentUserPayload.data?.permissions ?? []
            ).includes("pages.additional_fields.manage");
            resetForm();
            await loadApplicableAdditionalFields();

            return;
        }

        const [payload, pagesPayload] = await Promise.all([
            fetchPage(pageId.value),
            pagesPayloadPromise,
        ]);
        const currentUserPayload = await currentUserPromise;

        allPages.value = pagesPayload.data ?? [];
        canManageAdditionalFields.value = (
            currentUserPayload.data?.permissions ?? []
        ).includes("pages.additional_fields.manage");
        fillForm(payload.data);
        await loadApplicableAdditionalFields();
    } catch (error) {
        errorMessage.value = "Не удалось загрузить страницу.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function submitForm() {
    if (!isCreateMode.value && !canUpdatePage.value) {
        errorMessage.value = "У вас нет прав на редактирование этой страницы.";

        return;
    }

    saving.value = true;
    errorMessage.value = "";
    validationErrors.value = {};

    try {
        const submitPayload = {
            ...form,
            template: form.template === "default" ? "" : form.template,
            additional_fields: additionalFieldValues.value,
        };

        const payload = isCreateMode.value
            ? await createPage(submitPayload)
            : await updatePage(pageId.value, submitPayload);

        if (isCreateMode.value) {
            await router.replace({
                name: "page-edit",
                params: { id: payload.data.id },
            });
            await loadPage();
            notifySuccess("Страница создана.");
        } else {
            fillForm(payload.data);
            notifySuccess("Страница сохранена.");
        }
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {};
        } else {
            errorMessage.value = "Не удалось сохранить страницу.";
            notifyError(errorMessage.value);
        }

        console.error(error);
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    loadEditorSettings();
    loadPage();
});

watch(
    () => form.template,
    () => {
        loadApplicableAdditionalFields();
    },
);

watch(
    () => form.language_id,
    () => {
        if (
            resolvedParent.value &&
            String(resolvedParent.value.language_id) !==
                String(form.language_id)
        ) {
            form.parent_id = "";
        }
    },
);

watch(
    () => form.content,
    (value) => {
        if (contentMode.value !== "visual" || !contentEditor.value) {
            return;
        }

        const next = value || "";

        if (contentEditor.value.getHTML() === next) {
            return;
        }

        contentEditor.value.commands.setContent(next, false);
    },
);

onBeforeUnmount(() => {
    contentEditor.value?.destroy();
});
</script>

<template>
    <AdminPage
        description="Карточка страницы для полного редактирования полей и контента."
    >
        <template #actions>
            <div class="admin-actions-row">
                <AdminButton
                    form="page-editor-form"
                    type="submit"
                    variant="primary"
                    :disabled="saving || (!isCreateMode && !canUpdatePage)"
                >
                    {{ saving ? "Сохранение..." : "Сохранить страницу" }}
                </AdminButton>

                <a
                    v-if="canOpenPublicPage"
                    :href="publicUrl"
                    class="button-link"
                    target="_blank"
                    rel="noopener"
                >
                    Перейти на страницу
                </a>

                <RouterLink :to="{ name: 'pages' }" class="button-base">
                    <Icon name="return" width="18" height="18" /> К списку
                </RouterLink>
            </div>
        </template>

        <p v-if="loading" class="muted">Загрузка страницы...</p>

        <form
            v-else
            id="page-editor-form"
            class="page-editor-layout"
            @submit.prevent="submitForm"
        >
            <div class="page-editor-layout__main">
                <AdminCard>
                    <section class="page-editor-section">
                        <div class="page-editor-section__header">
                            <div>
                                <h2>Основное</h2>
                                <p class="muted">
                                    Название, адрес страницы и краткое описание
                                    для списков и предпросмотра.
                                </p>
                            </div>
                        </div>

                        <div class="page-identity-grid">
                            <label class="admin-form-label">
                                <span>Язык</span>
                                <select
                                    v-model="form.language_id"
                                    class="admin-select"
                                >
                                    <option
                                        v-for="language in languageOptions"
                                        :key="language.value"
                                        :value="language.value"
                                    >
                                        {{ language.label }} ({{
                                            language.code
                                        }})
                                    </option>
                                </select>
                                <small
                                    v-if="validationErrors.language_id"
                                    class="error-text"
                                >
                                    {{ validationErrors.language_id[0] }}
                                </small>
                            </label>

                            <label class="admin-form-label">
                                <span>Название страницы</span>
                                <input
                                    v-model="form.title"
                                    class="admin-input"
                                    type="text"
                                    placeholder="Например, О компании"
                                    @input="syncSlugFromTitle"
                                />
                                <small
                                    v-if="validationErrors.title"
                                    class="error-text"
                                >
                                    {{ validationErrors.title[0] }}
                                </small>
                            </label>

                            <label class="admin-form-label">
                                <span>URL</span>
                                <input
                                    v-model="form.slug"
                                    class="admin-input"
                                    type="text"
                                    placeholder="o-kompanii"
                                    @input="handleSlugInput"
                                />
                                <small
                                    v-if="validationErrors.slug"
                                    class="error-text"
                                >
                                    {{ validationErrors.slug[0] }}
                                </small>
                                <small class="muted"
                                    >Полный адрес: {{ publicUrl }}</small
                                >
                            </label>
                        </div>

                        <label class="admin-form-label">
                            <span>Группа переводов</span>
                            <input
                                v-model="form.translation_group_id"
                                class="admin-input"
                                type="text"
                                placeholder="UUID группы переводов"
                            />
                            <small
                                v-if="validationErrors.translation_group_id"
                                class="error-text"
                            >
                                {{ validationErrors.translation_group_id[0] }}
                            </small>
                        </label>

                        <label class="admin-form-label">
                            <span>Описание</span>
                            <textarea
                                v-model="form.excerpt"
                                class="admin-textarea"
                                rows="4"
                                placeholder="Краткое описание страницы"
                            ></textarea>
                            <small
                                v-if="validationErrors.excerpt"
                                class="error-text"
                            >
                                {{ validationErrors.excerpt[0] }}
                            </small>
                        </label>
                    </section>
                </AdminCard>

                <AdminCard>
                    <section class="page-editor-section">
                        <div class="page-editor-section__header">
                            <div>
                                <h2>Контент</h2>
                                <p class="muted">
                                    Основное содержимое страницы. Панель
                                    инструментов остаётся рядом с редактором.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="button-link"
                                @click="contentVisible = !contentVisible"
                            >
                                {{
                                    contentVisible
                                        ? "Скрыть контент"
                                        : "Показать контент"
                                }}
                            </button>
                        </div>

                        <label
                            v-show="contentVisible"
                            class="admin-form-label page-editor-content-field"
                        >
                            <div class="page-editor-content-field__head">
                                <span>Содержимое страницы</span>

                                <div class="page-editor-content-mode">
                                    <button
                                        type="button"
                                        class="button-base button-secondary"
                                        :class="{
                                            'is-active':
                                                contentMode === 'visual',
                                        }"
                                        @click="setContentMode('visual')"
                                    >
                                        Визуально
                                    </button>
                                    <button
                                        type="button"
                                        class="button-base button-secondary"
                                        :class="{
                                            'is-active': contentMode === 'source',
                                        }"
                                        @click="setContentMode('source')"
                                    >
                                        Код
                                    </button>
                                </div>
                            </div>

                            <template v-if="contentMode === 'visual'">
                                <PageContentToolbar
                                    :editor="contentEditor"
                                    :heading-levels="headingLevels"
                                    @open-media="openContentMediaModal"
                                />
                                <EditorContent
                                    :editor="contentEditor"
                                    class="admin-editor tiptap-editor"
                                />

                                <MediaLibraryModal
                                    :open="contentMediaModalOpen"
                                    title="Вставить медиа в контент"
                                    :multiple="true"
                                    :accept="DEFAULT_MEDIA_LIBRARY_ACCEPT"
                                    :allow-upload="true"
                                    @close="contentMediaModalOpen = false"
                                    @select="handleContentMediaInsert"
                                />
                            </template>

                            <template v-else>
                                <textarea
                                    v-model="form.content"
                                    class="admin-textarea page-editor-source"
                                    rows="16"
                                    spellcheck="false"
                                    placeholder="<p>HTML, inline script и любая разметка страницы...</p>"
                                ></textarea>
                                <small class="muted">
                                    Режим исходного кода редактирует содержимое
                                    напрямую как HTML-разметку.
                                </small>
                            </template>
                        </label>

                        <p v-if="!contentVisible" class="muted">
                            Блок контента скрыт. Его можно снова открыть кнопкой
                            в заголовке секции.
                        </p>
                    </section>
                </AdminCard>

                <AdminCard>
                    <section
                        class="page-editor-section additional-fields-block"
                    >
                        <div class="additional-fields-block__header">
                            <div>
                                <h2>Дополнительные поля</h2>
                                <p class="muted">
                                    Поля текущего шаблона и структуры контента.
                                    Сгруппированы отдельно от основного текста,
                                    чтобы не мешать редактированию.
                                </p>
                            </div>

                            <RouterLink
                                v-if="canManageAdditionalFields"
                                :to="{ name: 'content-structure' }"
                                class="button-link"
                            >
                                Настроить структуру
                            </RouterLink>
                        </div>

                        <p
                            v-if="additionalFieldGroups.length === 0"
                            class="muted"
                        >
                            Для текущего шаблона нет подключенных наборов полей.
                        </p>

                        <details
                            v-for="group in additionalFieldGroups"
                            :key="group.id"
                            class="additional-fields-group"
                            open
                        >
                            <summary class="additional-fields-group__summary">
                                <div>
                                    <h3>{{ group.name }}</h3>
                                    <p v-if="group.description" class="muted">
                                        {{ group.description }}
                                    </p>
                                </div>
                                <small class="muted">
                                    {{
                                        Array.isArray(group.fields)
                                            ? group.fields.length
                                            : 0
                                    }}
                                    полей
                                </small>
                            </summary>

                            <div
                                class="admin-stack additional-fields-group__body"
                            >
                                <CustomFieldRenderer
                                    v-for="field in group.fields"
                                    :key="field.key"
                                    :field="field"
                                    :model-value="
                                        additionalFieldValues[field.key]
                                    "
                                    :errors="validationErrors"
                                    :path="`additional_fields.${field.key}`"
                                    @update:model-value="
                                        (value) =>
                                            updateAdditionalFieldValue(
                                                field.key,
                                                value,
                                            )
                                    "
                                />
                            </div>
                        </details>
                    </section>
                </AdminCard>

                <div class="page-editor-footer">
                    <p v-if="errorMessage" class="error-text">
                        {{ errorMessage }}
                    </p>
                    <p
                        v-if="!isCreateMode && !canUpdatePage"
                        class="error-text"
                    >
                        Только автор страницы или администратор может ее
                        изменять.
                    </p>
                </div>
            </div>

            <div class="page-editor-layout__aside">
                <AdminCard>
                    <section
                        class="page-editor-section page-editor-aside-section"
                    >
                        <div class="page-editor-section__header">
                            <div>
                                <h2>Публикация</h2>
                                <p class="muted">
                                    Статус, видимость, дата и место страницы в
                                    структуре.
                                </p>
                            </div>
                        </div>

                        <div class="page-preview-box page-editor-aside-box">
                            <p class="eyebrow">Страница</p>
                            <p class="page-preview-box__value">
                                {{ form.title || "Без названия" }}
                            </p>
                            <p class="muted">Создал: {{ creatorLabel }}</p>
                        </div>

                        <label class="admin-form-label">
                            <span>Статус публикации</span>
                            <select v-model="form.status" class="admin-select">
                                <option value="draft">Черновик</option>
                                <option value="pending_review">
                                    На проверке
                                </option>
                                <option value="scheduled">Запланирована</option>
                                <option value="published">Опубликована</option>
                                <option value="archived">Архив</option>
                            </select>
                            <small
                                v-if="validationErrors.status"
                                class="error-text"
                            >
                                {{ validationErrors.status[0] }}
                            </small>
                        </label>

                        <label class="admin-form-label">
                            <span>Видимость</span>
                            <select
                                v-model="form.visibility"
                                class="admin-select"
                            >
                                <option value="public">Публичная</option>
                                <option value="private">Скрытая</option>
                            </select>
                            <small
                                v-if="validationErrors.visibility"
                                class="error-text"
                            >
                                {{ validationErrors.visibility[0] }}
                            </small>
                        </label>

                        <label class="admin-form-label">
                            <span>Дата публикации</span>
                            <input
                                v-model="form.published_at"
                                class="admin-input"
                                type="datetime-local"
                            />
                            <small
                                v-if="validationErrors.published_at"
                                class="error-text"
                            >
                                {{ validationErrors.published_at[0] }}
                            </small>
                        </label>

                        <label class="admin-form-label">
                            <span>Родительская страница</span>
                            <select
                                v-model="form.parent_id"
                                class="admin-select"
                            >
                                <option value="">Без родителя</option>
                                <option
                                    v-for="page in availableParents"
                                    :key="page.id"
                                    :value="page.id"
                                >
                                    {{ page.title }}
                                    ({{
                                        page.is_home
                                            ? "/"
                                            : `/${page.path || page.slug}`
                                    }})
                                </option>
                            </select>
                            <small
                                v-if="validationErrors.parent_id"
                                class="error-text"
                            >
                                {{ validationErrors.parent_id[0] }}
                            </small>
                        </label>

                        <label class="admin-form-label">
                            <span>Шаблон</span>
                            <select
                                v-model="form.template"
                                class="admin-select"
                            >
                                <option
                                    v-for="option in templateOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <small
                                v-if="validationErrors.template"
                                class="error-text"
                            >
                                {{ validationErrors.template[0] }}
                            </small>
                        </label>
                    </section>
                </AdminCard>

                <AdminCard>
                    <section
                        class="page-editor-section page-editor-aside-section"
                    >
                        <div class="page-editor-section__header">
                            <div>
                                <h2>SEO и обложка</h2>
                                <p class="muted">
                                    Поисковый сниппет и визуальная обложка
                                    собраны в одном боковом блоке.
                                </p>
                            </div>
                        </div>

                        <label class="admin-form-label">
                            <span>SEO заголовок</span>
                            <input
                                v-model="form.meta_title"
                                class="admin-input"
                                type="text"
                                placeholder="SEO заголовок страницы"
                            />
                        </label>

                        <label class="admin-form-label">
                            <span>SEO описание</span>
                            <textarea
                                v-model="form.meta_description"
                                class="admin-textarea"
                                rows="4"
                                placeholder="SEO описание страницы"
                            ></textarea>
                        </label>

                        <label
                            class="admin-form-label page-editor-checkbox-label"
                        >
                            <span class="page-editor-checkbox-text">
                                <input
                                    v-model="form.seo_noindex"
                                    type="checkbox"
                                />
                                Не индексировать страницу
                            </span>
                            <small class="muted">
                                Страница получит meta robots noindex и
                                автоматически выпадет из sitemap.
                            </small>
                        </label>

                        <label
                            class="admin-form-label page-editor-checkbox-label"
                        >
                            <span class="page-editor-checkbox-text">
                                <input
                                    v-model="form.seo_nofollow"
                                    type="checkbox"
                                />
                                Не передавать follow по ссылкам страницы
                            </span>
                            <small class="muted">
                                Переопределяет только follow-часть robots для
                                текущей страницы.
                            </small>
                        </label>

                        <div class="page-featured-media">
                            <span>Обложка страницы</span>
                            <MediaPickerField
                                v-model="form.featured_media_id"
                                title="Выбрать обложку страницы"
                                return-type="id"
                                :allow-upload="true"
                            />
                        </div>
                    </section>
                </AdminCard>
            </div>
        </form>
    </AdminPage>
</template>

<style scoped>
.page-editor-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.9fr);
    gap: 1rem;
    align-items: start;
}

.page-editor-layout__main,
.page-editor-layout__aside {
    display: grid;
    gap: 1rem;
}

.page-editor-section {
    display: grid;
    gap: 1rem;
}

.page-editor-section :deep(.admin-form-label) {
    display: grid;
    align-items: start;
    gap: 0.5rem;
    min-width: 0;
}

.page-editor-section :deep(.admin-form-label > span) {
    display: block;
    min-width: 0;
    line-height: 1.4;
}

.page-editor-section :deep(.admin-form-label small) {
    line-height: 1.45;
}

.page-editor-section__header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 1rem;
}

.page-editor-section__header h2 {
    margin: 0;
    font-size: 1rem;
}

.page-editor-section__header p {
    margin: 0.25rem 0 0;
}

.page-identity-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.page-editor-aside-section {
    display: grid;
    gap: 1rem;
}

.page-editor-footer {
    display: grid;
    gap: 0.85rem;
}

.page-featured-media {
    display: grid;
    gap: 0.65rem;
}

.page-featured-media > span {
    font-size: 0.95rem;
    font-weight: 600;
}

.page-preview-box__value {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.page-editor-checkbox-label {
    display: flex !important;
    align-items: flex-start !important;
    gap: 0.45rem;
}

.page-editor-checkbox-text {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    font-weight: 600;
}

.page-editor-content-field {
    min-width: 0;
}

.page-editor-content-field__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.page-editor-content-mode {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.page-editor-content-mode .button-base.is-active,
.page-editor-content-field :deep(.tiptap-toolbar-btn.button-base.is-active) {
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.18);
    filter: brightness(1.08) saturate(1.22);
}

.page-editor-content-field :deep(.admin-editor-toolbar) {
    margin-bottom: 0;
}

.page-editor-content-field :deep(.tiptap-toolbar-btn.button-base) {
    transition:
        filter 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.page-editor-content-field :deep(.tiptap-toolbar-btn.button-base.is-active) {
    transform: translateY(-1px);
}

.page-editor-content-field :deep(.tiptap-editor) {
    min-width: 0;
}

.page-editor-content-field :deep(.tiptap),
.page-editor-content-field :deep(.ProseMirror) {
    cursor: text;
}

.page-editor-content-field :deep(.tiptap video),
.page-editor-content-field :deep(.ProseMirror video) {
    display: block;
    width: 100%;
    max-width: 100%;
    border-radius: 16px;
    background: #0f172a;
}

.page-editor-content-field :deep(.tiptap .page-content-embed),
.page-editor-content-field :deep(.ProseMirror .page-content-embed) {
    display: grid;
    gap: 0.6rem;
    margin: 1rem 0;
}

.page-editor-content-field :deep(.tiptap .page-content-embed figcaption),
.page-editor-content-field :deep(.ProseMirror .page-content-embed figcaption) {
    color: rgba(71, 85, 105, 0.92);
    font-size: 0.9rem;
}

.page-editor-content-field :deep(.ProseMirror) {
    min-height: 320px;
}

.page-editor-source {
    min-height: 360px;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        "Liberation Mono", "Courier New", monospace;
    line-height: 1.5;
    resize: vertical;
}

.additional-fields-group {
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 20px;
    background: rgba(248, 250, 252, 0.9);
}

.additional-fields-group + .additional-fields-group {
    margin-top: 0.9rem;
}

.additional-fields-group__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    cursor: pointer;
    list-style: none;
}

.additional-fields-group__summary::-webkit-details-marker {
    display: none;
}

.additional-fields-group__body {
    padding: 0 1.1rem 1.1rem;
}

@media (max-width: 1080px) {
    .page-editor-layout {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 900px) {
    .page-identity-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .page-identity-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .page-editor-section__header {
        flex-direction: column;
    }

    .page-editor-content-field__head {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
