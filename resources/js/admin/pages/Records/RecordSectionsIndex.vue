<script setup>
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
    createRecordSection,
    deleteRecordSection,
    fetchRecordSections,
} from '../../api/records'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'

const loading = ref(false)
const saving = ref(false)
const deletingSlug = ref('')
const errorMessage = ref('')
const sections = ref([])

const form = reactive({
    title: '',
    slug: '',
    has_categories: true,
    has_tags: true,
    has_seo: true,
    has_featured_image: true,
    status: 'active',
})

async function loadSections() {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchRecordSections()
        sections.value = Array.isArray(payload.items) ? payload.items : []
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить разделы записей.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitSection() {
    saving.value = true
    errorMessage.value = ''

    try {
        await createRecordSection({
            title: form.title,
            slug: form.slug || null,
            has_categories: form.has_categories,
            has_tags: form.has_tags,
            has_seo: form.has_seo,
            has_featured_image: form.has_featured_image,
            status: form.status,
        })

        form.title = ''
        form.slug = ''
        form.has_categories = true
        form.has_tags = true
        form.has_seo = true
        form.has_featured_image = true
        form.status = 'active'

        await loadSections()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось создать раздел.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removeSection(slug) {
    deletingSlug.value = slug
    errorMessage.value = ''

    try {
        await deleteRecordSection(slug)
        await loadSections()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось удалить раздел.'
        console.error(error)
    } finally {
        deletingSlug.value = ''
    }
}

onMounted(loadSections)
</script>

<template>
    <AdminPage eyebrow="Записи" title="Создать раздел" description="Раздел — контейнер для записей, категорий, тегов и настроек URL.">
        <AdminCard>
            <p v-if="errorMessage" class="error-text"><strong>{{ errorMessage }}</strong></p>

            <form class="admin-form-stack" @submit.prevent="submitSection">
                <label class="admin-form-label">
                    <span>Название раздела</span>
                    <input v-model="form.title" class="admin-input" type="text" required placeholder="Blog">
                </label>

                <label class="admin-form-label">
                    <span>Slug</span>
                    <input v-model="form.slug" class="admin-input" type="text" placeholder="blog">
                </label>

                <div class="page-meta-grid">
                    <label class="admin-form-label">
                        <span><input v-model="form.has_categories" type="checkbox"> Включить категории</span>
                    </label>
                    <label class="admin-form-label">
                        <span><input v-model="form.has_tags" type="checkbox"> Включить теги</span>
                    </label>
                    <label class="admin-form-label">
                        <span><input v-model="form.has_seo" type="checkbox"> Включить SEO</span>
                    </label>
                    <label class="admin-form-label">
                        <span><input v-model="form.has_featured_image" type="checkbox"> Включить изображение</span>
                    </label>
                </div>

                <label class="admin-form-label">
                    <span>Статус</span>
                    <select v-model="form.status" class="admin-select">
                        <option value="active">active</option>
                        <option value="disabled">disabled</option>
                    </select>
                </label>

                <AdminButton type="submit" variant="primary" :disabled="saving">
                    {{ saving ? 'Сохраняем...' : 'Создать раздел' }}
                </AdminButton>
            </form>
        </AdminCard>

        <AdminCard>
            <h2>Разделы</h2>
            <p v-if="loading" class="muted">Загрузка...</p>

            <table v-else class="table">
                <thead>
                    <tr>
                        <th>Раздел</th>
                        <th>Slug</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="section in sections" :key="section.id">
                        <td>{{ section.name }}</td>
                        <td>/{{ section.slug }}</td>
                        <td>{{ section.status }}</td>
                        <td class="table-actions">
                            <RouterLink :to="{ name: 'records-posts', params: { sectionSlug: section.slug } }" class="button-link">Открыть</RouterLink>
                            <AdminButton type="button" variant="danger" :disabled="deletingSlug === section.slug" @click="removeSection(section.slug)">
                                {{ deletingSlug === section.slug ? 'Удаляем...' : 'Удалить' }}
                            </AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>
    </AdminPage>
</template>
