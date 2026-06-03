<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { RouterLink, useRoute } from "vue-router";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminSelect from "../../components/ui/AdminSelect.vue";
import AdminCheckbox from "../../components/ui/AdminCheckbox.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import Icon from "../../components/ui/Icon.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import AdminModal from "../../components/ui/AdminModal.vue";
import AdminMultiSelect from "../../components/ui/AdminMultiSelect.vue";
import {
    createSectionCategory,
    createSectionRecord,
    createSectionTag,
    deleteSectionCategory,
    deleteSectionRecord,
    deleteSectionTag,
    duplicateSectionRecord,
    fetchRecordSections,
    fetchSectionCategories,
    fetchSectionRecords,
    fetchSectionTags,
    publishSectionRecord,
    unpublishSectionRecord,
    updateRecordSection,
} from "../../api/records";

const route = useRoute();
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref("");
const section = ref(null);
const records = ref([]);
const categories = ref([]);
const tags = ref([]);
const { notifyError, notifySuccess } = useAdminNotifications();

const sectionSlug = computed(() => String(route.params.sectionSlug || ""));

const activeTab = computed(() => {
    const name = String(route.name || "");

    if (name === "records-categories") {
        return "categories";
    }

    if (name === "records-tags") {
        return "tags";
    }

    if (name === "records-settings") {
        return "settings";
    }

    return "posts";
});

watch(
    () => route.name,
    () => {
        loadWorkspace();
    },
);

const confirmModal = reactive({
    open: false,
    id: null,
    type: "record",
});

function askRemoveRecord(id) {
    confirmModal.open = true;
    confirmModal.id = id;
    confirmModal.type = "record";
}

function closeModal() {
    confirmModal.open = false;
    confirmModal.id = null;
}

async function confirmDelete() {
    if (!confirmModal.id) return;

    try {
        if (confirmModal.type === "record") {
            await deleteSectionRecord(sectionSlug.value, confirmModal.id);
        }

        await loadWorkspace();
        notifySuccess("Запись удалена.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить запись.";
        notifyError(errorMessage.value);
    } finally {
        closeModal();
    }
}

const recordForm = reactive({
    title: "",
    slug: "",
    excerpt: "",
    content: "",
    category_id: "",
    tag_ids: [],
    status: "draft",
});

const categoryForm = reactive({ name: "", slug: "" });
const tagForm = reactive({ name: "", slug: "" });

const settingsForm = reactive({
    title: "",
    slug: "",
    has_categories: true,
    has_tags: true,
    has_seo: true,
    has_featured_image: true,
    status: "active",
});

const tabLinks = computed(() => [
    { name: "records-posts", label: "Записи" },
    { name: "records-categories", label: "Категории" },
    { name: "records-tags", label: "Теги" },
    { name: "records-settings", label: "Настройки раздела" },
]);

function hydrateSettings() {
    if (!section.value) {
        return;
    }

    settingsForm.title = section.value.name;
    settingsForm.slug = section.value.slug;
    settingsForm.has_categories = Boolean(section.value.has_categories);
    settingsForm.has_tags = Boolean(section.value.has_tags);
    settingsForm.has_seo = Boolean(section.value.has_seo);
    settingsForm.has_featured_image = Boolean(section.value.has_featured_image);
    settingsForm.status = section.value.status;
}

async function loadWorkspace() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const sectionsPayload = await fetchRecordSections();
        const allSections = Array.isArray(sectionsPayload.items)
            ? sectionsPayload.items
            : [];
        section.value =
            allSections.find((item) => item.slug === sectionSlug.value) || null;

        if (!section.value) {
            errorMessage.value = "Раздел не найден.";
            return;
        }

        hydrateSettings();

        if (activeTab.value === "posts") {
            const [recordsPayload, categoriesPayload, tagsPayload] =
                await Promise.all([
                    fetchSectionRecords(sectionSlug.value),
                    fetchSectionCategories(sectionSlug.value),
                    fetchSectionTags(sectionSlug.value),
                ]);
            records.value = Array.isArray(recordsPayload.items)
                ? recordsPayload.items
                : [];
            categories.value = Array.isArray(categoriesPayload.items)
                ? categoriesPayload.items
                : [];
            tags.value = Array.isArray(tagsPayload.items)
                ? tagsPayload.items
                : [];
        }

        if (activeTab.value === "categories") {
            const payload = await fetchSectionCategories(sectionSlug.value);
            categories.value = Array.isArray(payload.items)
                ? payload.items
                : [];
        }

        if (activeTab.value === "tags") {
            const payload = await fetchSectionTags(sectionSlug.value);
            tags.value = Array.isArray(payload.items) ? payload.items : [];
        }
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Не удалось загрузить рабочую область раздела.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function submitRecord() {
    saving.value = true;
    errorMessage.value = "";

    try {
        await createSectionRecord(sectionSlug.value, {
            title: recordForm.title,
            slug: recordForm.slug || null,
            excerpt: recordForm.excerpt || null,
            content: recordForm.content || null,
            category_id: recordForm.category_id || null,
            tag_ids: recordForm.tag_ids,
            status: recordForm.status,
        });

        recordForm.title = "";
        recordForm.slug = "";
        recordForm.excerpt = "";
        recordForm.content = "";
        recordForm.category_id = "";
        recordForm.tag_ids = [];
        recordForm.status = "draft";

        await loadWorkspace();
        notifySuccess("Запись создана.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось создать запись.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function removeRecord(id) {
    try {
        await deleteSectionRecord(sectionSlug.value, id);
        await loadWorkspace();
        notifySuccess("Запись удалена.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить запись.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function duplicateRecord(id) {
    try {
        await duplicateSectionRecord(sectionSlug.value, id);
        await loadWorkspace();
        notifySuccess("Запись продублирована.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Не удалось продублировать запись.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function publishRecord(id) {
    try {
        await publishSectionRecord(sectionSlug.value, id);
        await loadWorkspace();
        notifySuccess("Запись опубликована.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось опубликовать запись.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function unpublishRecord(id) {
    try {
        await unpublishSectionRecord(sectionSlug.value, id);
        await loadWorkspace();
        notifySuccess("Публикация записи снята.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Не удалось снять запись с публикации.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function submitCategory() {
    try {
        await createSectionCategory(sectionSlug.value, {
            name: categoryForm.name,
            slug: categoryForm.slug || null,
        });

        categoryForm.name = "";
        categoryForm.slug = "";
        await loadWorkspace();
        notifySuccess("Категория создана.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось создать категорию.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function removeCategory(id) {
    try {
        await deleteSectionCategory(sectionSlug.value, id);
        await loadWorkspace();
        notifySuccess("Категория удалена.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить категорию.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function submitTag() {
    try {
        await createSectionTag(sectionSlug.value, {
            name: tagForm.name,
            slug: tagForm.slug || null,
        });

        tagForm.name = "";
        tagForm.slug = "";
        await loadWorkspace();
        notifySuccess("Тег создан.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось создать тег.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function removeTag(id) {
    try {
        await deleteSectionTag(sectionSlug.value, id);
        await loadWorkspace();
        notifySuccess("Тег удален.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить тег.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function saveSettings() {
    saving.value = true;
    errorMessage.value = "";

    try {
        await updateRecordSection(sectionSlug.value, {
            title: settingsForm.title,
            slug: settingsForm.slug || null,
            has_categories: settingsForm.has_categories,
            has_tags: settingsForm.has_tags,
            has_seo: settingsForm.has_seo,
            has_featured_image: settingsForm.has_featured_image,
            status: settingsForm.status,
        });

        await loadWorkspace();
        notifySuccess("Настройки раздела сохранены.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Не удалось сохранить настройки раздела.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

onMounted(loadWorkspace);
</script>

<template>
    <AdminPage
        description="Внутри раздела доступны вкладки записи, категории, теги и настройки."
        class="panel-stack"
    >
        <div class="panel-card section">
            <div
                class="admin-tabs admin-page-head"
                role="tablist"
                aria-label="Вкладки раздела"
            >
                <RouterLink
                    v-for="link in tabLinks"
                    :key="link.name"
                    :to="{ name: link.name, params: { sectionSlug } }"
                    class="admin-tab"
                    :class="{
                        'is-active':
                            activeTab ===
                            (link.name === 'records-posts'
                                ? 'posts'
                                : link.name.replace('records-', '')),
                    }"
                >
                    {{ link.label }}
                </RouterLink>
            </div>

            <AdminCard v-if="activeTab === 'posts'">
                <form class="admin-form-stack" @submit.prevent="submitRecord">
                    <label
                        class="admin-form-label code"
                        data-label="Заголовок:"
                    >
                        <input
                            v-model="recordForm.title"
                            class="admin-input"
                            type="text"
                            required
                        />
                    </label>

                    <div class="page-meta-grid">
                        <label class="admin-form-label slug" data-label="Slug:">
                            <input
                                v-model="recordForm.slug"
                                class="admin-input"
                                type="text"
                            />
                        </label>

                        <AdminSelect
                            v-model="recordForm.category_id"
                            class="category"
                            data-label="Категория:"
                            :options="[
                                { value: '', label: 'Без категории' },
                                ...categories.map((item) => ({
                                    value: item.id,
                                    label: item.name,
                                })),
                            ]"
                        />

                        <AdminSelect
                            v-model="recordForm.status"
                            label="Status"
                            class="status"
                            data-label="Статус:"
                            :options="[
                                { value: 'draft', label: 'draft' },
                                { value: 'published', label: 'published' },
                            ]"
                        />
                        <div class="admin-form-label tags" data-label="Теги:">
                            <AdminMultiSelect
                                v-model="recordForm.tag_ids"
                                :options="
                                    tags.map((item) => ({
                                        value: item.id,
                                        label: item.name,
                                    }))
                                "
                                placeholder="Выберите теги"
                                empty-text="Теги не найдены"
                                no-options-text="Теги пока не созданы"
                            />
                        </div>
                    </div>

                    <label
                        class="admin-form-label expert"
                        data-label="Експерт:"
                    >
                        <input
                            v-model="recordForm.excerpt"
                            class="admin-input"
                            type="text"
                        />
                    </label>

                    <label class="admin-form-label" data-label="Контент:">
                        <textarea
                            v-model="recordForm.content"
                            class="admin-textarea"
                            rows="3"
                            placeholder=""
                        ></textarea>
                    </label>

                    <div class="flex-end">
                        <AdminButton
                            type="submit"
                            variant="primary"
                            :disabled="saving"
                        >
                            <Icon name="create" width="18" height="18" />{{
                                saving ? "Сохраняем..." : "Создать запись"
                            }}</AdminButton
                        >
                    </div>
                </form>
            </AdminCard>

            <AdminCard v-if="activeTab === 'categories'">
                <form class="admin-form-stack" @submit.prevent="submitCategory">
                    <label
                        class="admin-form-label language"
                        data-label="Название:"
                    >
                        <input
                            v-model="categoryForm.name"
                            class="admin-input"
                            type="text"
                            required
                        />
                    </label>
                    <label class="admin-form-label slug" data-label="Slug:">
                        <input
                            v-model="categoryForm.slug"
                            class="admin-input"
                            type="text"
                        />
                    </label>
                    <div class="flex-end">
                        <AdminButton type="submit" variant="primary">
                            <Icon name="create" width="18" height="18" />Создать
                            категорию</AdminButton
                        >
                    </div>
                </form>
            </AdminCard>

            <AdminCard v-if="activeTab === 'tags'">
                <form class="admin-form-stack" @submit.prevent="submitTag">
                    <label
                        class="admin-form-label language"
                        data-label="Название:"
                    >
                        <input
                            v-model="tagForm.name"
                            class="admin-input"
                            type="text"
                            required
                        />
                    </label>
                    <label class="admin-form-label slug" data-label="Slug:">
                        <input
                            v-model="tagForm.slug"
                            class="admin-input"
                            type="text"
                        />
                    </label>
                    <div class="flex-end">
                        <AdminButton type="submit" variant="primary"
                            ><Icon
                                name="create"
                                width="18"
                                height="18"
                            />Создать тег</AdminButton
                        >
                    </div>
                </form>
            </AdminCard>

            <AdminCard v-if="activeTab === 'settings'">
                <form class="admin-form-stack" @submit.prevent="saveSettings">
                    <label
                        class="admin-form-label language"
                        data-label="Название:"
                    >
                        <input
                            v-model="settingsForm.title"
                            class="admin-input"
                            type="text"
                            required
                        />
                    </label>
                    <div class="page-meta-grid">
                        <label class="admin-form-label slug" data-label="Slug:">
                            <input
                                v-model="settingsForm.slug"
                                class="admin-input"
                                type="text"
                                required
                            />
                        </label>

                        <AdminSelect
                            v-model="settingsForm.status"
                            label="Статус"
                            class="status"
                            data-label="Статус:"
                            :options="[
                                { value: 'active', label: 'Активный' },
                                { value: 'disabled', label: 'Отключен' },
                            ]"
                        />
                    </div>

                    <div class="page-meta-grid">
                        <AdminCheckbox
                            :model-value="settingsForm.has_categories"
                            @update:modelValue="
                                settingsForm.has_categories = $event
                            "
                        >
                            Категории
                        </AdminCheckbox>

                        <AdminCheckbox
                            :model-value="settingsForm.has_tags"
                            @update:modelValue="settingsForm.has_tags = $event"
                        >
                            Теги
                        </AdminCheckbox>

                        <AdminCheckbox
                            :model-value="settingsForm.has_seo"
                            @update:modelValue="settingsForm.has_seo = $event"
                        >
                            SEO
                        </AdminCheckbox>

                        <AdminCheckbox
                            :model-value="settingsForm.has_featured_image"
                            @update:modelValue="
                                settingsForm.has_featured_image = $event
                            "
                        >
                            Изображение
                        </AdminCheckbox>
                    </div>

                    <div class="flex-end">
                        <AdminButton
                            type="submit"
                            variant="primary"
                            :disabled="saving"
                        >
                            <Icon name="settings" width="18" height="18" />
                            {{
                                saving ? "Сохраняем..." : "Сохранить настройки"
                            }}</AdminButton
                        >
                    </div>
                </form>
            </AdminCard>
        </div>

        <p v-if="loading" class="muted text-center">Загрузка...</p>

        <AdminCard v-if="activeTab === 'posts'">
            <table class="table create-partition">
                <thead>
                    <tr>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="tags"
                                    width="14"
                                    height="14"
                                />Название
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="category"
                                    width="14"
                                    height="14"
                                />Категория
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="status"
                                    width="14"
                                    height="14"
                                />Статус
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="avatar"
                                    width="14"
                                    height="14"
                                />Автор
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="calendar"
                                    width="14"
                                    height="14"
                                />Дата
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="setting"
                                    width="14"
                                    height="14"
                                />Действия
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody v-if="records.length">
                    <tr v-for="item in records" :key="item.id">
                        <td>
                            <strong>{{ item.title }}</strong>
                        </td>
                        <td>{{ item.category?.name || "—" }}</td>
                        <td>{{ item.status }}</td>
                        <td>
                            {{
                                item.author?.name ||
                                item.author?.username ||
                                "—"
                            }}
                        </td>
                        <td>
                            <div class="date-w">
                                {{ item.published_at || "—" }}
                            </div>
                        </td>
                        <td class="table-actions">
                            <div class="cell-actions">
                                <AdminButton
                                    type="button"
                                    @click="duplicateRecord(item.id)"
                                    title="Дублировать"
                                    class="button-link"
                                    ><Icon
                                        name="duplicate"
                                        width="20"
                                        height="20"
                                /></AdminButton>
                                <AdminButton
                                    v-if="item.status !== 'published'"
                                    type="button"
                                    @click="publishRecord(item.id)"
                                    title="Опубликовать"
                                    ><Icon name="show" width="22" height="22"
                                /></AdminButton>
                                <AdminButton
                                    v-else
                                    type="button"
                                    @click="unpublishRecord(item.id)"
                                    title="Снять с публикации"
                                    ><Icon
                                        name="eye-off"
                                        width="22"
                                        height="22"
                                /></AdminButton>
                                <AdminButton
                                    type="button"
                                    variant="danger"
                                    title="Удалить"
                                    @click="askRemoveRecord(item.id)"
                                    ><Icon name="trash" width="20" height="20"
                                /></AdminButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td colspan="6">Записей пока нет.</td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <AdminCard v-if="activeTab === 'categories'">
            <table class="table create-partition">
                <thead>
                    <tr>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="tags"
                                    width="14"
                                    height="14"
                                />Название
                            </div>
                        </th>
                        <th>
                            <div class="table-inner"><span>#</span>Slug</div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="settings"
                                    width="14"
                                    height="14"
                                />Действия
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody v-if="categories.length">
                    <tr v-for="item in categories" :key="item.id">
                        <td>
                            <strong>{{ item.name }}</strong>
                        </td>
                        <td>{{ item.slug }}</td>
                        <td>
                            <div class="flex-center">
                                <AdminButton
                                    type="button"
                                    variant="danger"
                                    @click="removeCategory(item.id)"
                                    title="Удалить"
                                    ><Icon name="trash" width="20" height="20"
                                /></AdminButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td colspan="3">Категории пока не созданы.</td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <AdminCard v-if="activeTab === 'tags'">
            <table class="table create-partition">
                <thead>
                    <tr>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="tags"
                                    width="14"
                                    height="14"
                                />Название
                            </div>
                        </th>
                        <th>
                            <div class="table-inner"><span>#</span>Slug</div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="settings"
                                    width="14"
                                    height="14"
                                />Действия
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody v-if="tags.length">
                    <tr v-for="item in tags" :key="item.id">
                        <td>
                            <strong>{{ item.name }}</strong>
                        </td>
                        <td>{{ item.slug }}</td>
                        <td>
                            <div class="flex-center">
                                <AdminButton
                                    type="button"
                                    variant="danger"
                                    @click="removeTag(item.id)"
                                    title="Удалить"
                                    ><Icon name="trash" width="20" height="20"
                                /></AdminButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td colspan="3">Теги пока не созданы.</td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>
    </AdminPage>
    <AdminModal
        :open="confirmModal.open"
        title="Подтверждение удаления"
        @close="closeModal"
    >
        <p>Вы уверены что хотите удалить запись?</p>

        <template #footer>
            <div class="admin-actions-row">
                <AdminButton variant="secondary" @click="closeModal">
                    Отмена
                </AdminButton>

                <AdminButton variant="danger" @click="confirmDelete">
                    Удалить
                </AdminButton>
            </div>
        </template>
    </AdminModal>
</template>
