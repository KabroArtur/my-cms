<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { fetchCurrentUser } from "../../api/auth";
import {
    createBlogPost,
    deleteBlogPost,
    fetchBlogCategories,
    fetchBlogPosts,
    fetchBlogTags,
} from "../../api/blog";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";

const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const errorMessage = ref("");
const posts = ref([]);
const categories = ref([]);
const tags = ref([]);
const permissions = ref(new Set());
const { notifyError, notifySuccess } = useAdminNotifications();

const form = reactive({
    title: "",
    slug: "",
    excerpt: "",
    content: "",
    category_id: "",
    tag_ids: [],
    is_published: false,
});

const canManagePosts = computed(() =>
    permissions.value.has("blog.posts.manage"),
);
const canAccess = computed(() => permissions.value.has("blog.access"));

async function loadAll() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const [mePayload, postsPayload, categoriesPayload, tagsPayload] =
            await Promise.all([
                fetchCurrentUser(),
                fetchBlogPosts(),
                fetchBlogCategories(),
                fetchBlogTags(),
            ]);

        permissions.value = new Set(mePayload.data?.permissions ?? []);
        posts.value = Array.isArray(postsPayload.items)
            ? postsPayload.items
            : [];
        categories.value = Array.isArray(categoriesPayload.items)
            ? categoriesPayload.items
            : [];
        tags.value = Array.isArray(tagsPayload.items) ? tagsPayload.items : [];
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось загрузить Blog.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    form.title = "";
    form.slug = "";
    form.excerpt = "";
    form.content = "";
    form.category_id = "";
    form.tag_ids = [];
    form.is_published = false;
}

async function submitPost() {
    if (!canManagePosts.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = "";

    try {
        await createBlogPost({
            title: form.title,
            slug: form.slug || null,
            excerpt: form.excerpt || null,
            content: form.content || null,
            category_id: form.category_id || null,
            tag_ids: form.tag_ids,
            is_published: form.is_published,
        });
        resetForm();
        await loadAll();
        notifySuccess("Пост создан.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось сохранить пост.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function removePost(id) {
    if (!canManagePosts.value) {
        return;
    }

    deletingId.value = id;
    errorMessage.value = "";

    try {
        await deleteBlogPost(id);
        await loadAll();
        notifySuccess("Пост удален.");
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Не удалось удалить пост.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        deletingId.value = null;
    }
}

onMounted(loadAll);
</script>

<template>
    <AdminPage description="Управление постами блога в SPA-режиме.">
        <AdminCard>
            <p v-if="errorMessage" class="error-text">
                <strong>{{ errorMessage }}</strong>
            </p>
            <p v-if="loading" class="muted">Загрузка...</p>
            <p v-else-if="!canAccess" class="error-text">
                Нет доступа к разделу Blog.
            </p>

            <form v-else class="admin-form-stack" @submit.prevent="submitPost">
                <label class="admin-form-label">
                    <span>Заголовок</span>
                    <input
                        v-model="form.title"
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
                        placeholder="auto from title"
                    />
                </label>

                <label class="admin-form-label">
                    <span>Краткое описание</span>
                    <input
                        v-model="form.excerpt"
                        class="admin-input"
                        type="text"
                    />
                </label>

                <label class="admin-form-label">
                    <span>Контент</span>
                    <textarea
                        v-model="form.content"
                        class="admin-textarea"
                        rows="6"
                    ></textarea>
                </label>

                <div class="page-meta-grid">
                    <label class="admin-form-label">
                        <span>Категория</span>
                        <select v-model="form.category_id" class="admin-select">
                            <option value="">Без категории</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </label>

                    <label class="admin-form-label">
                        <span>Теги</span>
                        <select
                            v-model="form.tag_ids"
                            class="admin-select"
                            multiple
                        >
                            <option
                                v-for="tag in tags"
                                :key="tag.id"
                                :value="tag.id"
                            >
                                {{ tag.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <label class="admin-form-label">
                    <span>
                        <input v-model="form.is_published" type="checkbox" />
                        Опубликовать
                    </span>
                </label>

                <AdminButton
                    type="submit"
                    variant="primary"
                    :disabled="saving || !canManagePosts"
                >
                    {{ saving ? "Сохраняем..." : "Создать пост" }}
                </AdminButton>
            </form>
        </AdminCard>

        <AdminCard>
            <h2>Список постов</h2>

            <p v-if="loading" class="muted">Загрузка...</p>

            <table v-else class="table">
                <thead>
                    <tr>
                        <th>Пост</th>
                        <th>Категория</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="post in posts" :key="post.id">
                        <td>
                            <strong>{{ post.title }}</strong>
                            <p class="muted">/{{ post.slug }}</p>
                        </td>
                        <td>{{ post.category?.name || "—" }}</td>
                        <td>
                            {{ post.is_published ? "Опубликован" : "Черновик" }}
                        </td>
                        <td>
                            <AdminButton
                                type="button"
                                variant="danger"
                                :disabled="
                                    deletingId === post.id || !canManagePosts
                                "
                                @click="removePost(post.id)"
                            >
                                {{
                                    deletingId === post.id
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
