<script setup>
import { onMounted, reactive, ref } from "vue";
import { RouterLink } from "vue-router";
import {
    createRecordSection,
    deleteRecordSection,
    fetchRecordSections,
} from "../../api/records";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import AdminSelect from "../../components/ui/AdminSelect.vue";
import AdminCheckbox from "../../components/ui/AdminCheckbox.vue";
import Icon from "../../components/ui/Icon.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";

const loading = ref(false);
const saving = ref(false);
const deletingSlug = ref("");
const errorMessage = ref("");
const sections = ref([]);
const { notifyError, notifySuccess } = useAdminNotifications();

const form = reactive({
    title: "",
    slug: "",
    has_categories: true,
    has_tags: true,
    has_seo: true,
    has_featured_image: true,
    status: "active",
});

async function loadSections() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const payload = await fetchRecordSections();
        sections.value = Array.isArray(payload.items) ? payload.items : [];
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Не удалось загрузить разделы записей.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function submitSection() {
    saving.value = true;
    errorMessage.value = "";

    try {
        await createRecordSection({
            title: form.title,
            slug: form.slug || null,
            has_categories: form.has_categories,
            has_tags: form.has_tags,
            has_seo: form.has_seo,
            has_featured_image: form.has_featured_image,
            status: form.status,
        });

        form.title = "";
        form.slug = "";
        form.has_categories = true;
        form.has_tags = true;
        form.has_seo = true;
        form.has_featured_image = true;
        form.status = "active";

        await loadSections();
        notifySuccess("Раздел создан.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось создать раздел.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function removeSection(slug) {
    deletingSlug.value = slug;
    errorMessage.value = "";

    try {
        await deleteRecordSection(slug);
        await loadSections();
        notifySuccess("Раздел удален.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить раздел.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        deletingSlug.value = "";
    }
}

onMounted(loadSections);
</script>

<template>
    <AdminPage
        description="Раздел — контейнер для записей, категорий, тегов и настроек URL."
    >
        <AdminCard>
            <p v-if="errorMessage" class="error-text">
                <strong>{{ errorMessage }}</strong>
            </p>

            <form class="admin-form-stack" @submit.prevent="submitSection">
                <label class="admin-form-label" data-label="Название раздела:">
                    <input
                        v-model="form.title"
                        class="admin-input name-section"
                        type="text"
                        required
                        placeholder="Blog"
                    />
                </label>
                <div class="page-meta-grid">
                    <label class="admin-form-label slug" data-label="Slug:">
                        <input
                            v-model="form.slug"
                            class="admin-input"
                            type="text"
                            placeholder="blog"
                        />
                    </label>
                    <AdminSelect
                        class="status"
                        data-label="Статус:"
                        :model-value="form.status"
                        :options="[
                            { value: 'active', label: 'Активен' },
                            { value: 'disabled', label: 'Отключён' },
                        ]"
                        @update:modelValue="form.status = $event"
                    />
                </div>

                <div class="page-meta-grid">
                    <AdminCheckbox
                        :model-value="form.has_categories"
                        @update:modelValue="form.has_categories = $event"
                    >
                        Включить категории
                    </AdminCheckbox>

                    <AdminCheckbox
                        :model-value="form.has_tags"
                        @update:modelValue="form.has_tags = $event"
                    >
                        Включить теги
                    </AdminCheckbox>

                    <AdminCheckbox
                        :model-value="form.has_seo"
                        @update:modelValue="form.has_seo = $event"
                    >
                        Включить SEO
                    </AdminCheckbox>

                    <AdminCheckbox
                        :model-value="form.has_featured_image"
                        @update:modelValue="form.has_featured_image = $event"
                    >
                        Включить изображение
                    </AdminCheckbox>
                </div>

                <div class="flex-end">
                    <AdminButton
                        type="submit"
                        variant="primary"
                        :disabled="saving"
                        class="flex-end"
                    >
                        <Icon name="create" width="18" height="18" />
                        {{ saving ? "Сохраняем..." : "Создать раздел" }}
                    </AdminButton>
                </div>
            </form>
        </AdminCard>

        <AdminCard>
            <p v-if="loading" class="muted text-2xl">Загрузка...</p>

            <table v-else class="table create-partition">
                <thead>
                    <tr>
                        <th>
                            <div class="table-inner">
                                <Icon name="folder" width="14" height="14" />
                                Раздел
                            </div>
                        </th>

                        <th>
                            <div class="table-inner"># Slug</div>
                        </th>

                        <th>
                            <div class="table-inner">
                                <Icon name="status" width="14" height="14" />
                                Статус
                            </div>
                        </th>

                        <th>
                            <div class="table-inner">
                                <Icon name="settings" width="14" height="14" />
                                Действия
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="section in sections" :key="section.id">
                        <td>
                            <strong>{{ section.name }}</strong>
                        </td>

                        <td>/{{ section.slug }}</td>

                        <td>{{ section.status }}</td>

                        <td class="cell-actions">
                            <RouterLink
                                :to="{
                                    name: 'records-posts',
                                    params: { sectionSlug: section.slug },
                                }"
                                class="button-link"
                            >
                                <Icon name="pencil" width="18" height="18" />
                            </RouterLink>

                            <AdminButton
                                type="button"
                                variant="danger"
                                :disabled="deletingSlug === section.slug"
                                @click="removeSection(section.slug)"
                            >
                                <Icon name="trash" width="18" height="18" />
                            </AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>
    </AdminPage>
</template>
