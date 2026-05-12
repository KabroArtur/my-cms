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
import { RouterLink, useRoute, useRouter } from "vue-router";
import { fetchCurrentUser } from "../../api/auth";
import CustomFieldRenderer from "../../components/custom-fields/CustomFieldRenderer.vue";
import {
    cloneValue,
    defaultValueForField,
} from "../../components/custom-fields/customFields";
import MediaPickerField from "../../components/media/MediaPickerField.vue";
import PageContentToolbar from "../../components/ui/PageContentToolbar.vue";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
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
const headingLevels = [1, 2, 3, 4, 5, 6];
const { notifyError, notifySuccess } = useAdminNotifications();

const form = reactive({
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
const availableParents = computed(() =>
    allPages.value.filter((page) => String(page.id) !== String(pageId.value)),
);
const resolvedParent = computed(
    () =>
        availableParents.value.find(
            (page) => String(page.id) === String(form.parent_id),
        ) ?? null,
);
const publicUrl = computed(() => {
    if (form.is_home) {
        return "/";
    }

    const segments = [];

    if (resolvedParent.value?.path) {
        segments.push(resolvedParent.value.path);
    }

    if (form.slug) {
        segments.push(form.slug);
    }

    return segments.length > 0 ? `/${segments.join("/")}` : "—";
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
    } catch (error) {
        console.error(error);
    }
}

function setFeaturedMedia(file) {
    form.featured_media_id = file.id;
}

function resetForm() {
    fillForm({});
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

onBeforeUnmount(() => {
    contentEditor.value?.destroy();
});
</script>

<template>
    <AdminPage
        eyebrow="Pages"
        :title="pageTitle"
        description="Карточка страницы для полного редактирования полей и контента."
    >
        <template #actions>
            <div class="admin-actions-row">
                <a
                    v-if="canOpenPublicPage"
                    :href="publicUrl"
                    class="button-link"
                    target="_blank"
                    rel="noopener"
                >
                    Перейти на страницу
                </a>

                <RouterLink :to="{ name: 'pages' }" class="button-link">
                    К списку
                </RouterLink>
            </div>
        </template>

        <div class="page-editor-grid">
            <AdminCard>
                <p v-if="loading" class="muted">Загрузка страницы...</p>

                <form
                    v-else
                    class="admin-form-stack"
                    @submit.prevent="submitForm"
                >
                    <label class="admin-form-label">
                        <span>Заголовок</span>
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
                            >{{ validationErrors.title[0] }}</small
                        >
                    </label>

                    <label class="admin-form-label">
                        <span>Slug</span>
                        <input
                            v-model="form.slug"
                            class="admin-input"
                            type="text"
                            placeholder="review"
                            @input="handleSlugInput"
                        />
                        <small
                            v-if="validationErrors.slug"
                            class="error-text"
                            >{{ validationErrors.slug[0] }}</small
                        >
                        <small class="muted"
                            >Slug хранится как отдельный сегмент URL. Полный
                            путь строится из родительской страницы.</small
                        >
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
                            >{{ validationErrors.excerpt[0] }}</small
                        >
                    </label>

                    <div class="page-meta-grid">
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
                                    {{ page.title }} ({{
                                        page.is_home
                                            ? "/"
                                            : `/${page.path || page.slug}`
                                    }})
                                </option>
                            </select>
                            <small
                                v-if="validationErrors.parent_id"
                                class="error-text"
                                >{{ validationErrors.parent_id[0] }}</small
                            >
                        </label>
                    </div>

                    <label class="admin-form-label">
                        <span>Meta title</span>
                        <input
                            v-model="form.meta_title"
                            class="admin-input"
                            type="text"
                            placeholder="SEO заголовок страницы"
                        />
                    </label>

                    <label class="admin-form-label">
                        <span>Meta description</span>
                        <textarea
                            v-model="form.meta_description"
                            class="admin-textarea"
                            rows="3"
                            placeholder="SEO описание страницы"
                        ></textarea>
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

                    <label class="admin-form-label">
                        <span>Контент</span>
                        <PageContentToolbar
                            :editor="contentEditor"
                            :heading-levels="headingLevels"
                        />
                        <EditorContent
                            :editor="contentEditor"
                            class="admin-editor tiptap-editor"
                        />
                    </label>

                    <section class="additional-fields-block">
                        <div class="additional-fields-block__header">
                            <div>
                                <h2>Дополнительные поля</h2>
                                <p class="muted">
                                    Локальные хаотичные поля лучше не
                                    использовать. Для массового сценария
                                    создавайте наборы в структуре контента.
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
                                <small class="muted"
                                    >{{
                                        Array.isArray(group.fields)
                                            ? group.fields.length
                                            : 0
                                    }}
                                    полей</small
                                >
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

                    <div class="admin-actions-row">
                        <AdminButton
                            type="submit"
                            variant="primary"
                            :disabled="
                                saving || (!isCreateMode && !canUpdatePage)
                            "
                        >
                            {{
                                saving ? "Сохранение..." : "Сохранить страницу"
                            }}
                        </AdminButton>

                        <RouterLink :to="{ name: 'pages' }" class="button-link">
                            Закрыть
                        </RouterLink>
                    </div>
                </form>
            </AdminCard>

            <AdminCard>
                <div class="admin-stack">
                    <div class="page-preview-box page-editor-aside-box">
                        <p class="eyebrow">Публикация</p>
                        <p class="page-preview-box__value">{{ publicUrl }}</p>
                        <p class="muted">Создал: {{ creatorLabel }}</p>

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
                                >{{ validationErrors.status[0] }}</small
                            >
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
                                >{{ validationErrors.visibility[0] }}</small
                            >
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
                                >{{ validationErrors.published_at[0] }}</small
                            >
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
                                >{{ validationErrors.template[0] }}</small
                            >
                        </label>
                    </div>
                </div>
            </AdminCard>
        </div>
    </AdminPage>
</template>
