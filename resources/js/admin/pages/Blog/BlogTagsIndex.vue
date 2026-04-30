<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { fetchCurrentUser } from '../../api/auth'
import {
    createBlogTag,
    deleteBlogTag,
    fetchBlogTags,
} from '../../api/blog'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'

const loading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const errorMessage = ref('')
const tags = ref([])
const permissions = ref(new Set())

const form = reactive({
    name: '',
    slug: '',
})

const canManage = computed(() => permissions.value.has('blog.tags.manage'))
const canAccess = computed(() => permissions.value.has('blog.access'))

async function loadAll() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [mePayload, tagsPayload] = await Promise.all([
            fetchCurrentUser(),
            fetchBlogTags(),
        ])

        permissions.value = new Set(mePayload.data?.permissions ?? [])
        tags.value = Array.isArray(tagsPayload.items) ? tagsPayload.items : []
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить теги.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitTag() {
    if (!canManage.value) {
        return
    }

    saving.value = true
    errorMessage.value = ''

    try {
        await createBlogTag({
            name: form.name,
            slug: form.slug || null,
        })
        form.name = ''
        form.slug = ''
        await loadAll()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось создать тег.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removeTag(id) {
    if (!canManage.value) {
        return
    }

    deletingId.value = id
    errorMessage.value = ''

    try {
        await deleteBlogTag(id)
        await loadAll()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось удалить тег.'
        console.error(error)
    } finally {
        deletingId.value = null
    }
}

onMounted(loadAll)
</script>

<template>
    <AdminPage eyebrow="Blog" title="Теги" description="Теги для постов блога.">
        <AdminCard>
            <p v-if="errorMessage" class="error-text"><strong>{{ errorMessage }}</strong></p>
            <p v-if="loading" class="muted">Загрузка...</p>
            <p v-else-if="!canAccess" class="error-text">Нет доступа к разделу Blog.</p>

            <form v-else class="admin-form-stack" @submit.prevent="submitTag">
                <label class="admin-form-label">
                    <span>Название</span>
                    <input v-model="form.name" class="admin-input" type="text" required>
                </label>

                <label class="admin-form-label">
                    <span>Slug</span>
                    <input v-model="form.slug" class="admin-input" type="text" placeholder="auto from name">
                </label>

                <AdminButton type="submit" variant="primary" :disabled="saving || !canManage">
                    {{ saving ? 'Сохраняем...' : 'Создать тег' }}
                </AdminButton>
            </form>
        </AdminCard>

        <AdminCard>
            <h2>Список тегов</h2>

            <table v-if="!loading" class="table">
                <thead>
                    <tr>
                        <th>Тег</th>
                        <th>Slug</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tag in tags" :key="tag.id">
                        <td>{{ tag.name }}</td>
                        <td>{{ tag.slug }}</td>
                        <td>
                            <AdminButton
                                type="button"
                                variant="danger"
                                :disabled="deletingId === tag.id || !canManage"
                                @click="removeTag(tag.id)"
                            >
                                {{ deletingId === tag.id ? 'Удаляем...' : 'Удалить' }}
                            </AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>
    </AdminPage>
</template>
