<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { useAdminNotifications } from '../../composables/useAdminNotifications'
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
} from '../../api/records'

const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const section = ref(null)
const records = ref([])
const categories = ref([])
const tags = ref([])
const { notifyError, notifySuccess } = useAdminNotifications()

const sectionSlug = computed(() => String(route.params.sectionSlug || ''))

const activeTab = computed(() => {
    const name = String(route.name || '')

    if (name === 'records-categories') {
        return 'categories'
    }

    if (name === 'records-tags') {
        return 'tags'
    }

    if (name === 'records-settings') {
        return 'settings'
    }

    return 'posts'
})

const recordForm = reactive({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    category_id: '',
    tag_ids: [],
    status: 'draft',
})

const categoryForm = reactive({ name: '', slug: '' })
const tagForm = reactive({ name: '', slug: '' })

const settingsForm = reactive({
    title: '',
    slug: '',
    has_categories: true,
    has_tags: true,
    has_seo: true,
    has_featured_image: true,
    status: 'active',
})

const tabLinks = computed(() => [
    { name: 'records-posts', label: 'Записи' },
    { name: 'records-categories', label: 'Категории' },
    { name: 'records-tags', label: 'Теги' },
    { name: 'records-settings', label: 'Настройки' },
])

function hydrateSettings() {
    if (!section.value) {
        return
    }

    settingsForm.title = section.value.name
    settingsForm.slug = section.value.slug
    settingsForm.has_categories = Boolean(section.value.has_categories)
    settingsForm.has_tags = Boolean(section.value.has_tags)
    settingsForm.has_seo = Boolean(section.value.has_seo)
    settingsForm.has_featured_image = Boolean(section.value.has_featured_image)
    settingsForm.status = section.value.status
}

async function loadWorkspace() {
    loading.value = true
    errorMessage.value = ''

    try {
        const sectionsPayload = await fetchRecordSections()
        const allSections = Array.isArray(sectionsPayload.items) ? sectionsPayload.items : []
        section.value = allSections.find((item) => item.slug === sectionSlug.value) || null

        if (!section.value) {
            errorMessage.value = 'Раздел не найден.'
            return
        }

        hydrateSettings()

        if (activeTab.value === 'posts') {
            const [recordsPayload, categoriesPayload, tagsPayload] = await Promise.all([
                fetchSectionRecords(sectionSlug.value),
                fetchSectionCategories(sectionSlug.value),
                fetchSectionTags(sectionSlug.value),
            ])
            records.value = Array.isArray(recordsPayload.items) ? recordsPayload.items : []
            categories.value = Array.isArray(categoriesPayload.items) ? categoriesPayload.items : []
            tags.value = Array.isArray(tagsPayload.items) ? tagsPayload.items : []
        }

        if (activeTab.value === 'categories') {
            const payload = await fetchSectionCategories(sectionSlug.value)
            categories.value = Array.isArray(payload.items) ? payload.items : []
        }

        if (activeTab.value === 'tags') {
            const payload = await fetchSectionTags(sectionSlug.value)
            tags.value = Array.isArray(payload.items) ? payload.items : []
        }
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить рабочую область раздела.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitRecord() {
    saving.value = true
    errorMessage.value = ''

    try {
        await createSectionRecord(sectionSlug.value, {
            title: recordForm.title,
            slug: recordForm.slug || null,
            excerpt: recordForm.excerpt || null,
            content: recordForm.content || null,
            category_id: recordForm.category_id || null,
            tag_ids: recordForm.tag_ids,
            status: recordForm.status,
        })

        recordForm.title = ''
        recordForm.slug = ''
        recordForm.excerpt = ''
        recordForm.content = ''
        recordForm.category_id = ''
        recordForm.tag_ids = []
        recordForm.status = 'draft'

        await loadWorkspace()
        notifySuccess('Запись создана.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось создать запись.'
        notifyError(errorMessage.value)
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removeRecord(id) {
    try {
        await deleteSectionRecord(sectionSlug.value, id)
        await loadWorkspace()
        notifySuccess('Запись удалена.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось удалить запись.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function duplicateRecord(id) {
    try {
        await duplicateSectionRecord(sectionSlug.value, id)
        await loadWorkspace()
        notifySuccess('Запись продублирована.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось продублировать запись.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function publishRecord(id) {
    try {
        await publishSectionRecord(sectionSlug.value, id)
        await loadWorkspace()
        notifySuccess('Запись опубликована.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось опубликовать запись.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function unpublishRecord(id) {
    try {
        await unpublishSectionRecord(sectionSlug.value, id)
        await loadWorkspace()
        notifySuccess('Публикация записи снята.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось снять запись с публикации.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function submitCategory() {
    try {
        await createSectionCategory(sectionSlug.value, {
            name: categoryForm.name,
            slug: categoryForm.slug || null,
        })

        categoryForm.name = ''
        categoryForm.slug = ''
        await loadWorkspace()
        notifySuccess('Категория создана.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось создать категорию.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function removeCategory(id) {
    try {
        await deleteSectionCategory(sectionSlug.value, id)
        await loadWorkspace()
        notifySuccess('Категория удалена.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось удалить категорию.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function submitTag() {
    try {
        await createSectionTag(sectionSlug.value, {
            name: tagForm.name,
            slug: tagForm.slug || null,
        })

        tagForm.name = ''
        tagForm.slug = ''
        await loadWorkspace()
        notifySuccess('Тег создан.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось создать тег.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function removeTag(id) {
    try {
        await deleteSectionTag(sectionSlug.value, id)
        await loadWorkspace()
        notifySuccess('Тег удален.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось удалить тег.'
        notifyError(errorMessage.value)
        console.error(error)
    }
}

async function saveSettings() {
    saving.value = true
    errorMessage.value = ''

    try {
        await updateRecordSection(sectionSlug.value, {
            title: settingsForm.title,
            slug: settingsForm.slug || null,
            has_categories: settingsForm.has_categories,
            has_tags: settingsForm.has_tags,
            has_seo: settingsForm.has_seo,
            has_featured_image: settingsForm.has_featured_image,
            status: settingsForm.status,
        })

        await loadWorkspace()
        notifySuccess('Настройки раздела сохранены.')
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось сохранить настройки раздела.'
        notifyError(errorMessage.value)
        console.error(error)
    } finally {
        saving.value = false
    }
}

onMounted(loadWorkspace)
</script>

<template>
    <AdminPage description="Внутри раздела доступны вкладки записи, категории, теги и настройки.">
        <AdminCard>
            <p v-if="errorMessage" class="error-text"><strong>{{ errorMessage }}</strong></p>
            <p v-if="loading" class="muted">Загрузка...</p>

            <div class="admin-tabs" role="tablist" aria-label="Вкладки раздела">
                <RouterLink
                    v-for="link in tabLinks"
                    :key="link.name"
                    :to="{ name: link.name, params: { sectionSlug } }"
                    class="admin-tab"
                    :class="{ 'is-active': activeTab === (link.name === 'records-posts' ? 'posts' : link.name.replace('records-', '')) }"
                >
                    {{ link.label }}
                </RouterLink>
            </div>
        </AdminCard>

        <AdminCard v-if="activeTab === 'posts'">
            <h2>Создать запись</h2>

            <form class="admin-form-stack" @submit.prevent="submitRecord">
                <label class="admin-form-label">
                    <span>Title</span>
                    <input v-model="recordForm.title" class="admin-input" type="text" required>
                </label>

                <label class="admin-form-label">
                    <span>Slug</span>
                    <input v-model="recordForm.slug" class="admin-input" type="text">
                </label>

                <label class="admin-form-label">
                    <span>Category</span>
                    <select v-model="recordForm.category_id" class="admin-select">
                        <option value="">Без категории</option>
                        <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                </label>

                <label class="admin-form-label">
                    <span>Теги</span>
                    <select v-model="recordForm.tag_ids" class="admin-select" multiple>
                        <option v-for="item in tags" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                </label>

                <label class="admin-form-label">
                    <span>Status</span>
                    <select v-model="recordForm.status" class="admin-select">
                        <option value="draft">draft</option>
                        <option value="published">published</option>
                    </select>
                </label>

                <label class="admin-form-label">
                    <span>Excerpt</span>
                    <input v-model="recordForm.excerpt" class="admin-input" type="text">
                </label>

                <label class="admin-form-label">
                    <span>Content</span>
                    <textarea v-model="recordForm.content" class="admin-textarea" rows="4"></textarea>
                </label>

                <AdminButton type="submit" variant="primary" :disabled="saving">{{ saving ? 'Сохраняем...' : 'Создать' }}</AdminButton>
            </form>
        </AdminCard>

        <AdminCard v-if="activeTab === 'posts'">
            <h2>Список записей</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Author</th>
                        <th>Date</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in records" :key="item.id">
                        <td>{{ item.title }}</td>
                        <td>{{ item.category?.name || '—' }}</td>
                        <td>{{ item.status }}</td>
                        <td>{{ item.author?.name || item.author?.username || '—' }}</td>
                        <td>{{ item.published_at || '—' }}</td>
                        <td class="table-actions">
                            <AdminButton type="button" @click="duplicateRecord(item.id)">Дублировать</AdminButton>
                            <AdminButton v-if="item.status !== 'published'" type="button" @click="publishRecord(item.id)">Опубликовать</AdminButton>
                            <AdminButton v-else type="button" @click="unpublishRecord(item.id)">Снять с публикации</AdminButton>
                            <AdminButton type="button" variant="danger" @click="removeRecord(item.id)">Удалить</AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <AdminCard v-if="activeTab === 'categories'">
            <h2>Категории</h2>
            <form class="admin-form-stack" @submit.prevent="submitCategory">
                <label class="admin-form-label">
                    <span>Name</span>
                    <input v-model="categoryForm.name" class="admin-input" type="text" required>
                </label>
                <label class="admin-form-label">
                    <span>Slug</span>
                    <input v-model="categoryForm.slug" class="admin-input" type="text">
                </label>
                <AdminButton type="submit" variant="primary">Создать</AdminButton>
            </form>

            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Slug</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <tr v-for="item in categories" :key="item.id">
                        <td>{{ item.name }}</td>
                        <td>{{ item.slug }}</td>
                        <td><AdminButton type="button" variant="danger" @click="removeCategory(item.id)">Удалить</AdminButton></td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <AdminCard v-if="activeTab === 'tags'">
            <h2>Теги</h2>
            <form class="admin-form-stack" @submit.prevent="submitTag">
                <label class="admin-form-label">
                    <span>Name</span>
                    <input v-model="tagForm.name" class="admin-input" type="text" required>
                </label>
                <label class="admin-form-label">
                    <span>Slug</span>
                    <input v-model="tagForm.slug" class="admin-input" type="text">
                </label>
                <AdminButton type="submit" variant="primary">Создать</AdminButton>
            </form>

            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Slug</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <tr v-for="item in tags" :key="item.id">
                        <td>{{ item.name }}</td>
                        <td>{{ item.slug }}</td>
                        <td><AdminButton type="button" variant="danger" @click="removeTag(item.id)">Удалить</AdminButton></td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <AdminCard v-if="activeTab === 'settings'">
            <h2>Настройки раздела</h2>
            <form class="admin-form-stack" @submit.prevent="saveSettings">
                <label class="admin-form-label">
                    <span>Название</span>
                    <input v-model="settingsForm.title" class="admin-input" type="text" required>
                </label>

                <label class="admin-form-label">
                    <span>Slug</span>
                    <input v-model="settingsForm.slug" class="admin-input" type="text" required>
                </label>

                <div class="page-meta-grid">
                    <label class="admin-form-label"><span><input v-model="settingsForm.has_categories" type="checkbox"> Категории</span></label>
                    <label class="admin-form-label"><span><input v-model="settingsForm.has_tags" type="checkbox"> Теги</span></label>
                    <label class="admin-form-label"><span><input v-model="settingsForm.has_seo" type="checkbox"> SEO</span></label>
                    <label class="admin-form-label"><span><input v-model="settingsForm.has_featured_image" type="checkbox"> Изображение</span></label>
                </div>

                <label class="admin-form-label">
                    <span>Статус</span>
                    <select v-model="settingsForm.status" class="admin-select">
                        <option value="active">active</option>
                        <option value="disabled">disabled</option>
                    </select>
                </label>

                <AdminButton type="submit" variant="primary" :disabled="saving">{{ saving ? 'Сохраняем...' : 'Сохранить настройки' }}</AdminButton>
            </form>
        </AdminCard>
    </AdminPage>
</template>
