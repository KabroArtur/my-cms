<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { deletePage, fetchPages, fetchTrashedPages, permanentlyDeletePage, restorePage } from '../../api/pages'

const loading = ref(true)
const errorMessage = ref('')
const pages = ref([])
const trashedPages = ref([])

function resolvePublicUrl(page) {
    return page.is_home ? '/' : `/${page.slug}`
}

async function loadPages() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [payload, trashedPayload] = await Promise.all([
            fetchPages(),
            fetchTrashedPages(),
        ])

        pages.value = payload.data ?? []
        trashedPages.value = trashedPayload.data ?? []
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить страницы.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function removePage(page) {
    const confirmed = window.confirm(`Переместить страницу "${page.title}" в корзину?`)

    if (!confirmed) {
        return
    }

    errorMessage.value = ''

    try {
        await deletePage(page.id)
        pages.value = pages.value.filter((item) => item.id !== page.id)
        trashedPages.value.unshift({
            ...page,
            deleted_at: new Date().toISOString(),
        })
    } catch (error) {
        errorMessage.value = 'Не удалось переместить страницу в корзину.'
        console.error(error)
    }
}

async function restoreTrashedPage(page) {
    errorMessage.value = ''

    try {
        const payload = await restorePage(page.id)
        trashedPages.value = trashedPages.value.filter((item) => item.id !== page.id)
        pages.value.unshift(payload.data)
    } catch (error) {
        errorMessage.value = 'Не удалось восстановить страницу.'
        console.error(error)
    }
}

async function forceRemovePage(page) {
    const confirmed = window.confirm(`Удалить страницу "${page.title}" навсегда?`)

    if (!confirmed) {
        return
    }

    errorMessage.value = ''

    try {
        await permanentlyDeletePage(page.id)
        trashedPages.value = trashedPages.value.filter((item) => item.id !== page.id)
    } catch (error) {
        errorMessage.value = 'Не удалось удалить страницу навсегда.'
        console.error(error)
    }
}

onMounted(loadPages)
</script>

<template>
    <AdminPage
        eyebrow="Pages"
        title="Страницы"
        description="Список страниц с переходом в отдельную карточку редактирования."
    >
        <template #actions>
            <RouterLink to="/admin/pages/create" class="button-link">
                Новая страница
            </RouterLink>
        </template>

        <div class="admin-page-grid">
            <AdminCard>
                <p v-if="loading" class="muted">Загрузка страниц...</p>
                <p v-else-if="pages.length === 0" class="muted">Страницы пока не созданы.</p>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Description</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Visibility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="page in pages" :key="page.id">
                            <td>{{ page.id }}</td>
                            <td>
                                <RouterLink :to="`/admin/pages/${page.id}`" class="page-title-link">
                                    {{ page.title }}
                                </RouterLink>
                            </td>
                            <td>
                                <span v-if="page.is_home">/</span>
                                <span v-else>/{{ page.slug }}</span>
                            </td>
                            <td>{{ page.excerpt || '—' }}</td>
                            <td>{{ page.slug }}</td>
                            <td>{{ page.status }}</td>
                            <td>{{ page.published_at ? new Date(page.published_at).toLocaleString('ru-RU') : '—' }}</td>
                            <td>{{ page.visibility }}</td>
                            <td>
                                <div class="admin-actions-row">
                                    <a :href="resolvePublicUrl(page)" class="button-link" target="_blank" rel="noopener">
                                        Перейти
                                    </a>

                                    <RouterLink :to="`/admin/pages/${page.id}`" class="button-link">
                                        Открыть
                                    </RouterLink>

                                    <AdminButton type="button" variant="danger" @click="removePage(page)">
                                        В корзину
                                    </AdminButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AdminCard>

            <AdminCard>
                <h2>Корзина</h2>
                <p v-if="trashedPages.length === 0" class="muted">Удалённых страниц пока нет.</p>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Удалена</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="page in trashedPages" :key="page.id">
                            <td>{{ page.id }}</td>
                            <td>{{ page.title }}</td>
                            <td>{{ page.slug }}</td>
                            <td>{{ page.deleted_at ? new Date(page.deleted_at).toLocaleString('ru-RU') : '—' }}</td>
                            <td>
                                <div class="admin-actions-row">
                                    <AdminButton type="button" @click="restoreTrashedPage(page)">
                                        Восстановить
                                    </AdminButton>

                                    <AdminButton type="button" variant="danger" @click="forceRemovePage(page)">
                                        Удалить навсегда
                                    </AdminButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AdminCard>
        </div>
    </AdminPage>
</template>