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
import AdminSelect from "../../components/ui/AdminSelect.vue";
import AdminCheckbox from "../../components/ui/AdminCheckbox.vue";
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
const additionalFieldsVisible = ref(true);
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
    const items = (Array.isArray(selection) ? selection : [selection]).filter(
        Boolean,
    );

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
        notifyError("Не удалось загрузить страницу.");
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function submitForm() {
    if (!isCreateMode.value && !canUpdatePage.value) {
        notifyError("У вас нет прав на редактирование этой страницы.");

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

            notifyError("Проверьте корректность заполненных полей.");
        } else {
            notifyError("Не удалось сохранить страницу.");
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

const toggleContentMode = () => {
    contentMode.value = contentMode.value === "source" ? "visual" : "source";
};
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
                    variant="secondary"
                    :disabled="saving || (!isCreateMode && !canUpdatePage)"
                >
                    <Icon name="save" width="18" height="18" />{{
                        saving ? "Сохранение..." : "Сохранить страницу"
                    }}
                </AdminButton>

                <a
                    v-if="canOpenPublicPage"
                    :href="publicUrl"
                    class="button-link"
                    target="_blank"
                    rel="noopener"
                >
                    <Icon name="show" width="22" height="22" />
                </a>

                <RouterLink :to="{ name: 'pages' }" class="button-base">
                    <Icon name="return" width="20" height="20" />
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
                                <h2 class="title-tooltip-down">
                                    Основное
                                    <Icon name="info" width="16" height="16" />
                                    <strong>
                                        Название, адрес страницы и краткое
                                        описание для списков и
                                        предпросмотра.</strong
                                    >
                                </h2>
                            </div>
                        </div>

                        <div class="page-editor">
                            <div
                                class="page-editor__label name"
                                data-label="Название страницы:"
                            >
                                <input
                                    v-model="form.title"
                                    class="admin-input"
                                    type="text"
                                    placeholder="Например, О компании"
                                    @input="syncSlugFromTitle"
                                />
                                <small
                                    v-if="validationErrors.title"
                                    class="error-text absolute"
                                >
                                    {{ validationErrors.title[0] }}
                                </small>
                            </div>

                            <div
                                class="page-editor__label url"
                                data-label="URL:"
                            >
                                <input
                                    v-model="form.slug"
                                    class="admin-input"
                                    type="text"
                                    placeholder="o-kompanii"
                                    @input="handleSlugInput"
                                />
                                <small
                                    v-if="validationErrors.slug"
                                    class="error-text absolute"
                                >
                                    {{ validationErrors.slug[0] }}
                                </small>
                                <small class="page-editor__full-url"
                                    >Полный адрес:
                                    <strong>{{ publicUrl }}</strong></small
                                >
                            </div>

                            <div class="page-editor__row">
                                <div>
                                    <AdminSelect
                                        class="page-editor__label lang"
                                        data-label="Язык:"
                                        v-model="form.language_id"
                                        :options="
                                            languageOptions.map((l) => ({
                                                value: l.value,
                                                label: `${l.label} (${l.code})`,
                                            }))
                                        "
                                    />
                                    <small
                                        v-if="validationErrors.language_id"
                                        class="error-text absolute"
                                    >
                                        {{ validationErrors.language_id[0] }}
                                    </small>
                                </div>

                                <div
                                    class="page-editor__label group"
                                    data-label="Группа переводов:"
                                >
                                    <input
                                        v-model="form.translation_group_id"
                                        class="admin-input"
                                        type="text"
                                        placeholder="UUID группы переводов"
                                    />
                                    <small
                                        v-if="
                                            validationErrors.translation_group_id
                                        "
                                        class="error-text absolute"
                                    >
                                        {{
                                            validationErrors
                                                .translation_group_id[0]
                                        }}
                                    </small>
                                </div>
                            </div>

                            <div
                                class="page-editor__label textarea-wrapper"
                                data-label="Краткое описание страницы:"
                            >
                                <textarea
                                    v-model="form.excerpt"
                                    class="admin-textarea"
                                    rows="4"
                                    placeholder=""
                                ></textarea>
                                <small
                                    v-if="validationErrors.excerpt"
                                    class="error-text absolute"
                                >
                                    {{ validationErrors.excerpt[0] }}
                                </small>
                            </div>
                        </div>
                    </section>
                </AdminCard>

                <AdminCard>
                    <section class="page-editor-section">
                        <div class="page-editor-section__header">
                            <div>
                                <h2 class="title-tooltip-down">
                                    Контент
                                    <Icon name="info" width="16" height="16" />
                                    <strong>
                                        Основное содержимое страницы. Панель
                                        инструментов остаётся рядом с
                                        редактором.</strong
                                    >
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="button-link content-toggle"
                                @click="contentVisible = !contentVisible"
                            >
                                <Icon
                                    name="arrow-down"
                                    width="20"
                                    height="20"
                                    class="content-toggle__icon"
                                    :class="{ 'is-open': contentVisible }"
                                />
                            </button>
                        </div>
                        <transition name="content">
                            <div
                                v-show="contentVisible"
                                class="page-editor__label page-editor-content-field"
                            >
                                <div class="page-editor-content-field__head">
                                    <div
                                        class="page-editor-content-field__title"
                                    >
                                        <p class="title-tooltip">
                                            Содержимое страницы
                                            <Icon
                                                v-if="contentMode === 'source'"
                                                name="info"
                                                width="16"
                                                height="16"
                                            />
                                            <span>
                                                Режим исходного кода редактирует
                                                содержимое напрямую как
                                                HTML-разметку.</span
                                            >
                                        </p>
                                    </div>

                                    <div class="page-editor-content-mode">
                                        <button
                                            type="button"
                                            class="button-base button-secondary"
                                            :class="{
                                                'is-active':
                                                    contentMode === 'source',
                                            }"
                                            @click.stop="toggleContentMode"
                                        >
                                            <Icon
                                                name="code"
                                                width="20"
                                                height="20"
                                            />

                                            <span
                                                v-if="contentMode === 'source'"
                                            >
                                                Код
                                            </span>

                                            <span v-else>
                                                Текстовый редактор
                                            </span>
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
                                        placeholder="<p>HTML, inline script и любая разметка страницы.</p>"
                                    ></textarea>
                                </template>
                            </div>
                        </transition>

                        <p v-show="!contentVisible" class="page-editor__info">
                            Блок контента скрыт. Его можно снова открыть кнопкой
                            в заголовке секции.
                        </p>
                    </section>
                </AdminCard>

                <AdminCard>
                    <section class="page-editor-section">
                        <div class="page-editor-section__header">
                            <div>
                                <h2 class="title-tooltip-down">
                                    Дополнительные поля
                                    <Icon name="info" width="16" height="16" />
                                    <strong
                                        >Поля текущего шаблона и структуры
                                        контента. Сгруппированы отдельно от
                                        основного текста, чтобы не мешать
                                        редактированию.</strong
                                    >
                                </h2>
                            </div>
                            <button
                                type="button"
                                class="button-link content-toggle"
                                @click="
                                    additionalFieldsVisible =
                                        !additionalFieldsVisible
                                "
                            >
                                <Icon
                                    name="arrow-down"
                                    width="20"
                                    height="20"
                                    class="content-toggle__icon"
                                    :class="{
                                        'is-open': additionalFieldsVisible,
                                    }"
                                />
                            </button>
                        </div>
                        <transition name="content">
                            <div
                                v-show="additionalFieldsVisible"
                                class="page-editor"
                            >
                                <div class="page-editor__header-end">
                                    <RouterLink
                                        v-if="canManageAdditionalFields"
                                        :to="{ name: 'content-structure' }"
                                        class="button-base"
                                    >
                                        <Icon
                                            name="structure"
                                            width="18"
                                            height="18"
                                        />
                                        Настроить структуру
                                    </RouterLink>
                                </div>

                                <p
                                    v-if="additionalFieldGroups.length === 0"
                                    class="text-center muted"
                                >
                                    Для текущего шаблона нет подключенных
                                    наборов полей.
                                </p>

                                <details
                                    v-for="group in additionalFieldGroups"
                                    :key="group.id"
                                    class="additional-fields-group"
                                    open
                                >
                                    <summary
                                        class="additional-fields-group__summary"
                                    >
                                        <div>
                                            <h3>
                                                {{ group.name }}
                                                <small class="muted">
                                                    (
                                                    {{
                                                        Array.isArray(
                                                            group.fields,
                                                        )
                                                            ? group.fields
                                                                  .length
                                                            : 0
                                                    }}
                                                    полей )
                                                </small>
                                            </h3>
                                            <p
                                                v-if="group.description"
                                                class="muted"
                                            >
                                                {{ group.description }}
                                            </p>
                                        </div>
                                        <span
                                            aria-hidden="true"
                                            title="Свернуть/развернуть"
                                            class="button-link content-toggle"
                                        >
                                            <Icon
                                                name="arrow-down"
                                                width="20"
                                                height="20"
                                                class="content-toggle__icon"
                                            />
                                        </span>
                                    </summary>

                                    <div class="additional-fields-group__body">
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
                                </details></div
                        ></transition>
                        <p
                            v-show="!additionalFieldsVisible"
                            class="page-editor__info"
                        >
                            Дополнительные поля скрыты.
                        </p>
                    </section>
                </AdminCard>
            </div>

            <div class="page-editor-layout__aside">
                <AdminCard>
                    <section class="page-editor-section">
                        <div class="page-editor-section__header">
                            <div>
                                <h2 class="title-tooltip-down">
                                    Публикация
                                    <Icon name="info" width="16" height="16" />
                                    <strong
                                        >Статус, видимость, дата и место
                                        страницы в структуре.</strong
                                    >
                                </h2>
                            </div>
                        </div>
                        <div class="page-editor">
                            <div class="page-preview-box page-editor-aside-box">
                                <p class="eyebrow">Страница</p>
                                <p class="page-preview-box__value">
                                    {{ form.title || "Без названия" }}
                                </p>
                                <p class="eyebrow text-muted">
                                    Создал:{{ creatorLabel }}
                                </p>
                            </div>

                            <div>
                                <AdminSelect
                                    class="status"
                                    data-label="Статус:"
                                    v-model="form.status"
                                    :options="[
                                        { value: 'draft', label: 'Черновик' },
                                        {
                                            value: 'pending_review',
                                            label: 'На проверке',
                                        },
                                        {
                                            value: 'scheduled',
                                            label: 'Запланирована',
                                        },
                                        {
                                            value: 'published',
                                            label: 'Опубликована',
                                        },
                                        { value: 'archived', label: 'Архив' },
                                    ]"
                                />
                                <small
                                    v-if="validationErrors.status"
                                    class="error-text"
                                >
                                    {{ validationErrors.status[0] }}
                                </small>
                            </div>

                            <div>
                                <AdminSelect
                                    class="visibility"
                                    data-label="Видимость:"
                                    v-model="form.visibility"
                                    :options="[
                                        { value: 'public', label: 'Публичная' },
                                        { value: 'private', label: 'Скрытая' },
                                    ]"
                                />
                                <small
                                    v-if="validationErrors.visibility"
                                    class="error-text"
                                >
                                    {{ validationErrors.visibility[0] }}
                                </small>
                            </div>

                            <label
                                class="page-editor__label date"
                                data-label="Дата публикации:"
                            >
                                <div class="admin-input-wrapper">
                                    <input
                                        v-model="form.published_at"
                                        class="admin-input"
                                        type="datetime-local"
                                    />

                                    <Icon
                                        name="calendar"
                                        width="18"
                                        height="18"
                                    />
                                </div>
                                <small
                                    v-if="validationErrors.published_at"
                                    class="error-text absolute"
                                >
                                    {{ validationErrors.published_at[0] }}
                                </small>
                            </label>

                            <div>
                                <AdminSelect
                                    class="parent"
                                    data-label="Родительская страница:"
                                    v-model="form.parent_id"
                                    :options="[
                                        { value: '', label: 'Без родителя' },
                                        ...availableParents.map((page) => ({
                                            value: page.id,
                                            label: `${page.title} (${page.is_home ? '/' : `/${page.path || page.slug}`})`,
                                        })),
                                    ]"
                                />
                                <small
                                    v-if="validationErrors.parent_id"
                                    class="error-text"
                                >
                                    {{ validationErrors.parent_id[0] }}
                                </small>
                            </div>

                            <div>
                                <AdminSelect
                                    class="shab"
                                    data-label="Шаблон:"
                                    v-model="form.template"
                                    :options="
                                        templateOptions.map((option) => ({
                                            value: option.value,
                                            label: option.label,
                                        }))
                                    "
                                />
                                <small
                                    v-if="validationErrors.template"
                                    class="error-text"
                                >
                                    {{ validationErrors.template[0] }}
                                </small>
                            </div>
                        </div>
                    </section>
                </AdminCard>

                <AdminCard>
                    <section class="page-editor-section">
                        <div class="page-editor-section__header">
                            <div>
                                <h2 class="title-tooltip-down">
                                    SEO и обложка
                                    <Icon name="info" width="16" height="16" />
                                    <strong
                                        >Поисковый сниппет и визуальная обложка
                                        собраны в одном боковом блоке.</strong
                                    >
                                </h2>
                            </div>
                        </div>
                        <div class="page-editor">
                            <label
                                class="page-editor__label"
                                data-label="SEO заголовок страницы:"
                            >
                                <textarea
                                    v-model="form.meta_title"
                                    class="admin-input"
                                    type="text"
                                    rows="1"
                                    placeholder=""
                                ></textarea>
                            </label>

                            <label
                                class="page-editor__label"
                                data-label="SEO описание страницы:"
                            >
                                <textarea
                                    v-model="form.meta_description"
                                    class="admin-textarea mt-0"
                                    rows="4"
                                    placeholder=""
                                ></textarea>
                            </label>

                            <AdminCheckbox v-model="form.seo_noindex">
                                <p class="title-tooltip-down">
                                    Не индексировать страницу

                                    <Icon name="info" width="16" height="16" />

                                    <strong>
                                        Страница получит meta robots noindex и
                                        автоматически выпадет из sitemap.
                                    </strong>
                                </p>
                            </AdminCheckbox>
                            <AdminCheckbox v-model="form.seo_nofollow">
                                <p class="title-tooltip-down">
                                    Не передавать follow по ссылкам страницы

                                    <Icon name="info" width="16" height="16" />

                                    <strong>
                                        Переопределяет только follow-часть
                                        robots для текущей страницы.
                                    </strong>
                                </p>
                            </AdminCheckbox>

                            <div class="page-featured-media">
                                <p class="eyebrow">Обложка страницы</p>
                                <MediaPickerField
                                    v-model="form.featured_media_id"
                                    title="Выбрать обложку страницы"
                                    return-type="id"
                                    :allow-upload="true"
                                />
                            </div>
                        </div>
                    </section>
                </AdminCard>
            </div>
        </form>
    </AdminPage>
</template>
