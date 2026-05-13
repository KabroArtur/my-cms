<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { fetchCurrentUser } from "../../api/auth";
import {
    createBlogCategory,
    deleteBlogCategory,
    fetchBlogCategories,
} from "../../api/blog";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";

const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const errorMessage = ref("");
const categories = ref([]);
const permissions = ref(new Set());
const { notifyError, notifySuccess } = useAdminNotifications();

const form = reactive({
    name: "",
    slug: "",
    description: "",
});

const canManage = computed(() =>
    permissions.value.has("blog.categories.manage"),
);
const canAccess = computed(() => permissions.value.has("blog.access"));

async function loadAll() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const [mePayload, categoriesPayload] = await Promise.all([
            fetchCurrentUser(),
            fetchBlogCategories(),
        ]);

        permissions.value = new Set(mePayload.data?.permissions ?? []);
        categories.value = Array.isArray(categoriesPayload.items)
            ? categoriesPayload.items
            : [];
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось загрузить категории.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function submitCategory() {
    if (!canManage.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = "";

    try {
        await createBlogCategory({
            name: form.name,
            slug: form.slug || null,
            description: form.description || null,
        });
        form.name = "";
        form.slug = "";
        form.description = "";
        await loadAll();
        notifySuccess("Категория создана.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось создать категорию.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function removeCategory(id) {
    if (!canManage.value) {
        return;
    }

    deletingId.value = id;
    errorMessage.value = "";

    try {
        await deleteBlogCategory(id);
        await loadAll();
        notifySuccess("Категория удалена.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить категорию.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        deletingId.value = null;
    }
}

onMounted(loadAll);
</script>

<template>
    <AdminPage
        eyebrow="Blog"
        title="Категории"
        description="Категории для постов блога."
    >
        <AdminCard>
            <p v-if="errorMessage" class="error-text">
                <strong>{{ errorMessage }}</strong>
            </p>
            <p v-if="loading" class="muted">Загрузка...</p>
            <p v-else-if="!canAccess" class="error-text">
                Нет доступа к разделу Blog.
            </p>

            <form
                v-else
                class="admin-form-stack"
                @submit.prevent="submitCategory"
            >
                <label class="admin-form-label">
                    <span>Название</span>
                    <input
                        v-model="form.name"
                        class="admin-input"
                        type="text"
                        required
                    />
                </label>

                <label class="admin-form-label">
                    <span>Slug</span>
                    <input
                        v-model="form.slug"
                        class="admin-input"
                        type="text"
                        placeholder="auto from name"
                    />
                </label>

                <label class="admin-form-label">
                    <span>Описание</span>
                    <textarea
                        v-model="form.description"
                        class="admin-textarea"
                        rows="3"
                    ></textarea>
                </label>

                <AdminButton
                    type="submit"
                    variant="primary"
                    :disabled="saving || !canManage"
                >
                    {{ saving ? "Сохраняем..." : "Создать категорию" }}
                </AdminButton>
            </form>
        </AdminCard>

        <AdminCard>
            <h2>Список категорий</h2>

            <table v-if="!loading" class="table">
                <thead>
                    <tr>
                        <th>Категория</th>
                        <th>Slug</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="category in categories" :key="category.id">
                        <td>{{ category.name }}</td>
                        <td>{{ category.slug }}</td>
                        <td>
                            <AdminButton
                                type="button"
                                variant="danger"
                                :disabled="
                                    deletingId === category.id || !canManage
                                "
                                @click="removeCategory(category.id)"
                            >
                                {{
                                    deletingId === category.id
                                        ? "Удаляем..."
                                        : "Удалить"
                                }}
                            </AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>
    </AdminPage>
</template>
