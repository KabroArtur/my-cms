<script setup>
import { onMounted, reactive, ref } from 'vue'
import { createPage, deletePage, fetchPages, updatePage } from '../../api/pages'

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const pages = ref([])
const editingId = ref(null)
const slugLocked = ref(false)

const form = reactive({
    title: '',
    slug: '',
    status: 'draft',
    template: '',
    excerpt: '',
})

function normalizeSlug(value) {
    return String(value)
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\p{L}\p{N}-]+/gu, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
}

function syncSlugFromTitle() {
    if (slugLocked.value) {
        return
    }

    form.slug = normalizeSlug(form.title)
}

function handleSlugInput() {
    slugLocked.value = form.slug.trim() !== ''
}

function resetForm() {
    editingId.value = null
    slugLocked.value = false
    form.title = ''
    form.slug = ''
    form.status = 'draft'
    form.template = ''
    form.excerpt = ''
    validationErrors.value = {}
}

function startEdit(page) {
    editingId.value = page.id
    slugLocked.value = true
    form.title = page.title ?? ''
    form.slug = page.slug ?? ''
    form.status = page.status ?? 'draft'
    form.template = page.template ?? ''
    form.excerpt = page.excerpt ?? ''
    errorMessage.value = ''
    validationErrors.value = {}
}

async function loadPages() {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchPages()
        pages.value = payload.data ?? []
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить страницы.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitForm() {
    saving.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        if (editingId.value) {
            const payload = await updatePage(editingId.value, form)
            const index = pages.value.findIndex((page) => page.id === editingId.value)

            if (index !== -1) {
                pages.value[index] = payload.data
            }
        } else {
            const payload = await createPage(form)
            pages.value.unshift(payload.data)
        }

        resetForm()
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = editingId.value
                ? 'Не удалось обновить страницу.'
                : 'Не удалось создать страницу.'
        }

        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removePage(page) {
    const confirmed = window.confirm(`Удалить страницу "${page.title}"?`)

    if (!confirmed) {
        return
    }

    errorMessage.value = ''

    try {
        await deletePage(page.id)
        pages.value = pages.value.filter((item) => item.id !== page.id)

        if (editingId.value === page.id) {
            resetForm()
        }
    } catch (error) {
        errorMessage.value = 'Не удалось удалить страницу.'
        console.error(error)
    }
}

onMounted(loadPages)
</script>

<template>
    <section>
        <h1>Pages</h1>

        <div>
            <section>
                <fieldset>
                    <legend>{{ editingId ? 'Edit page' : 'Create page' }}</legend>

                    <form @submit.prevent="submitForm">
                        <table border="1" cellpadding="8" cellspacing="0">
                            <tbody>
                                <tr>
                                    <td>
                                        <label for="page-title">Title</label>
                                    </td>
                                    <td>
                                        <input id="page-title" v-model="form.title" type="text" placeholder="Например, О компании" @input="syncSlugFromTitle">
                                    </td>
                                    <td>
                                        <small v-if="validationErrors.title">{{ validationErrors.title[0] }}</small>
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <label for="page-slug">Slug</label>
                                    </td>
                                    <td>
                                        <input id="page-slug" v-model="form.slug" type="text" placeholder="about-company" @input="handleSlugInput">
                                    </td>
                                    <td>
                                        <small v-if="validationErrors.slug">{{ validationErrors.slug[0] }}</small>
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <label for="page-status">Status</label>
                                    </td>
                                    <td>
                                        <select id="page-status" v-model="form.status">
                                            <option value="draft">Черновик</option>
                                            <option value="published">Опубликована</option>
                                            <option value="archived">Архив</option>
                                        </select>
                                    </td>
                                    <td>
                                        <small v-if="validationErrors.status">{{ validationErrors.status[0] }}</small>
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <label for="page-template">Template</label>
                                    </td>
                                    <td>
                                        <input id="page-template" v-model="form.template" type="text" placeholder="default">
                                    </td>
                                    <td>
                                        <small v-if="validationErrors.template">{{ validationErrors.template[0] }}</small>
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <label for="page-excerpt">Excerpt</label>
                                    </td>
                                    <td>
                                        <textarea id="page-excerpt" v-model="form.excerpt" rows="4" cols="40" placeholder="Короткое описание страницы"></textarea>
                                    </td>
                                    <td>
                                        <small v-if="validationErrors.excerpt">{{ validationErrors.excerpt[0] }}</small>
                                    </td>
                                </tr>

                                <tr v-if="errorMessage">
                                    <td colspan="3">
                                        <strong>{{ errorMessage }}</strong>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="3">
                                        <button type="submit" :disabled="saving">
                                            [ {{ saving ? 'Сохранение...' : (editingId ? 'Сохранить страницу' : 'Создать страницу') }} ]
                                        </button>

                                        <button v-if="editingId" type="button" @click="resetForm">
                                            [ Отмена ]
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                </fieldset>
            </section>

            <section>
                <p v-if="loading">Загрузка страниц...</p>
                <p v-else-if="pages.length === 0">Страницы пока не созданы.</p>

                <table v-else border="1" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="page in pages" :key="page.id">
                            <td>{{ page.id }}</td>
                            <td>{{ page.title }}</td>
                            <td>{{ page.slug }}</td>
                            <td>{{ page.status }}</td>
                            <td>
                                <button type="button" @click="startEdit(page)">
                                    [ Edit ]
                                </button>

                                <button type="button" @click="removePage(page)">
                                    [ Delete ]
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </section>
</template>