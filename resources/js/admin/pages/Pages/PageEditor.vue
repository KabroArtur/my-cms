<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { createPage, fetchPage, fetchPageTree, updatePage } from '../../api/pages'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const slugLocked = ref(false)
const allPages = ref([])

const form = reactive({
    title: '',
    slug: '',
    parent_id: '',
    excerpt: '',
    status: 'draft',
    visibility: 'public',
    is_home: false,
    published_at: '',
    template: '',
    meta_title: '',
    meta_description: '',
    content: '',
})

const isCreateMode = computed(() => route.name === 'page-create')
const pageId = computed(() => route.params.id)
const pageTitle = computed(() => isCreateMode.value ? 'Новая страница' : `Страница #${pageId.value}`)
const availableParents = computed(() => allPages.value.filter((page) => String(page.id) !== String(pageId.value)))
const resolvedParent = computed(() => availableParents.value.find((page) => String(page.id) === String(form.parent_id)) ?? null)
const publicUrl = computed(() => {
    if (form.is_home) {
        return '/'
    }

    const segments = []

    if (resolvedParent.value?.path) {
        segments.push(resolvedParent.value.path)
    }

    if (form.slug) {
        segments.push(form.slug)
    }

    return segments.length > 0 ? `/${segments.join('/')}` : '—'
})
const canOpenPublicPage = computed(() => form.is_home || form.slug.trim() !== '')

const transliterationMap = {
    А: 'A', а: 'a', Б: 'B', б: 'b', В: 'V', в: 'v', Г: 'G', г: 'g', Д: 'D', д: 'd',
    Е: 'E', е: 'e', Ё: 'E', ё: 'e', Ж: 'Zh', ж: 'zh', З: 'Z', з: 'z', И: 'I', и: 'i',
    Й: 'Y', й: 'y', К: 'K', к: 'k', Л: 'L', л: 'l', М: 'M', м: 'm', Н: 'N', н: 'n',
    О: 'O', о: 'o', П: 'P', п: 'p', Р: 'R', р: 'r', С: 'S', с: 's', Т: 'T', т: 't',
    У: 'U', у: 'u', Ф: 'F', ф: 'f', Х: 'Kh', х: 'kh', Ц: 'Ts', ц: 'ts', Ч: 'Ch', ч: 'ch',
    Ш: 'Sh', ш: 'sh', Щ: 'Shch', щ: 'shch', Ъ: '', ъ: '', Ы: 'Y', ы: 'y', Ь: '', ь: '',
    Э: 'E', э: 'e', Ю: 'Yu', ю: 'yu', Я: 'Ya', я: 'ya', І: 'I', і: 'i', Ї: 'Yi', ї: 'yi',
    Є: 'Ye', є: 'ye', Ґ: 'G', ґ: 'g',
}

function formatDateTimeLocalValue(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    const timezoneOffset = date.getTimezoneOffset() * 60_000

    return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16)
}

function normalizeSlug(value) {
    const source = String(value)
        .split('')
        .map((character) => transliterationMap[character] ?? character)
        .join('')

    return source
        .toLowerCase()
        .trim()
        .replace(/['’]+/g, '')
        .replace(/\//g, '-')
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]+/g, '-')
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
    form.slug = normalizeSlug(form.slug)
    slugLocked.value = form.slug.trim() !== ''
}

function fillForm(page) {
    form.title = page.title ?? ''
    form.slug = page.slug ?? ''
    form.parent_id = page.parent_id ?? ''
    form.excerpt = page.excerpt ?? ''
    form.status = page.status ?? 'draft'
    form.visibility = page.visibility ?? 'public'
    form.is_home = page.is_home ?? false
    form.published_at = formatDateTimeLocalValue(page.published_at)
    form.template = page.template ?? ''
    form.meta_title = page.meta_title ?? ''
    form.meta_description = page.meta_description ?? ''
    form.content = page.content ?? ''
    slugLocked.value = form.slug.trim() !== ''
}

function resetForm() {
    fillForm({})
    form.status = 'draft'
    form.visibility = 'public'
    form.is_home = false
    slugLocked.value = false
    validationErrors.value = {}
    errorMessage.value = ''
}

async function loadPage() {
    loading.value = true
    errorMessage.value = ''

    try {
        const pagesPayloadPromise = fetchPageTree()

        if (isCreateMode.value) {
            const pagesPayload = await pagesPayloadPromise
            allPages.value = pagesPayload.data ?? []
            resetForm()

            return
        }

        const [payload, pagesPayload] = await Promise.all([
            fetchPage(pageId.value),
            pagesPayloadPromise,
        ])

        allPages.value = pagesPayload.data ?? []
        fillForm(payload.data)
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить страницу.'
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
        const payload = isCreateMode.value
            ? await createPage(form)
            : await updatePage(pageId.value, form)

        if (isCreateMode.value) {
            await router.replace(`/admin/pages/${payload.data.id}`)
        } else {
            fillForm(payload.data)
        }
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = 'Не удалось сохранить страницу.'
        }

        console.error(error)
    } finally {
        saving.value = false
    }
}

onMounted(loadPage)
</script>

<template>
    <AdminPage
        eyebrow="Pages"
        :title="pageTitle"
        description="Карточка страницы для полного редактирования полей и контента."
    >
        <template #actions>
            <div class="admin-actions-row">
                <a v-if="canOpenPublicPage" :href="publicUrl" class="button-link" target="_blank" rel="noopener">
                    Перейти на страницу
                </a>

                <RouterLink to="/admin/pages" class="button-link">
                    К списку
                </RouterLink>
            </div>
        </template>

        <div class="page-editor-grid">
            <AdminCard>
                <p v-if="loading" class="muted">Загрузка страницы...</p>

                <form v-else class="admin-form-stack" @submit.prevent="submitForm">
                    <label class="admin-form-label">
                        <span>Заголовок</span>
                        <input v-model="form.title" class="admin-input" type="text" placeholder="Например, О компании" @input="syncSlugFromTitle">
                        <small v-if="validationErrors.title" class="error-text">{{ validationErrors.title[0] }}</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Slug</span>
                        <input v-model="form.slug" class="admin-input" type="text" placeholder="review" @input="handleSlugInput">
                        <small v-if="validationErrors.slug" class="error-text">{{ validationErrors.slug[0] }}</small>
                        <small class="muted">Slug хранится как отдельный сегмент URL. Полный путь строится из родительской страницы.</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Описание</span>
                        <textarea v-model="form.excerpt" class="admin-textarea" rows="4" placeholder="Краткое описание страницы"></textarea>
                        <small v-if="validationErrors.excerpt" class="error-text">{{ validationErrors.excerpt[0] }}</small>
                    </label>

                    <div class="page-meta-grid">
                        <label class="admin-form-label">
                            <span>Родительская страница</span>
                            <select v-model="form.parent_id" class="admin-select">
                                <option value="">Без родителя</option>
                                <option v-for="page in availableParents" :key="page.id" :value="page.id">
                                    {{ page.title }} ({{ page.is_home ? '/' : `/${page.path || page.slug}` }})
                                </option>
                            </select>
                            <small v-if="validationErrors.parent_id" class="error-text">{{ validationErrors.parent_id[0] }}</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Статус публикации</span>
                            <select v-model="form.status" class="admin-select">
                                <option value="draft">Черновик</option>
                                <option value="pending_review">На проверке</option>
                                <option value="scheduled">Запланирована</option>
                                <option value="published">Опубликована</option>
                                <option value="archived">Архив</option>
                            </select>
                            <small v-if="validationErrors.status" class="error-text">{{ validationErrors.status[0] }}</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Видимость</span>
                            <select v-model="form.visibility" class="admin-select">
                                <option value="public">Публичная</option>
                                <option value="private">Скрытая</option>
                            </select>
                            <small v-if="validationErrors.visibility" class="error-text">{{ validationErrors.visibility[0] }}</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Дата публикации</span>
                            <input v-model="form.published_at" class="admin-input" type="datetime-local">
                            <small v-if="validationErrors.published_at" class="error-text">{{ validationErrors.published_at[0] }}</small>
                        </label>

                        <label class="admin-form-label">
                            <span>Шаблон</span>
                            <input v-model="form.template" class="admin-input" type="text" placeholder="default">
                            <small v-if="validationErrors.template" class="error-text">{{ validationErrors.template[0] }}</small>
                        </label>

                        <label class="admin-form-label page-home-toggle">
                            <span>Главная страница</span>
                            <label class="admin-choice">
                                <input v-model="form.is_home" type="checkbox">
                                <span>Использовать эту страницу как главную для сайта</span>
                            </label>
                            <small class="muted">Главная страница всегда одна. Если отметить этот флаг, она будет открываться по адресу /.</small>
                        </label>
                    </div>

                    <label class="admin-form-label">
                        <span>Meta title</span>
                        <input v-model="form.meta_title" class="admin-input" type="text" placeholder="SEO заголовок страницы">
                    </label>

                    <label class="admin-form-label">
                        <span>Meta description</span>
                        <textarea v-model="form.meta_description" class="admin-textarea" rows="3" placeholder="SEO описание страницы"></textarea>
                    </label>

                    <label class="admin-form-label">
                        <span>Контент</span>
                        <textarea v-model="form.content" class="admin-textarea admin-editor" rows="16" placeholder="Временный editor через textarea. Кастомный редактор можно будет подключить позже."></textarea>
                        <small class="muted">Сейчас используется простой editor-слой через textarea. Позже его можно заменить на кастомный редактор.</small>
                    </label>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving">
                            {{ saving ? 'Сохранение...' : 'Сохранить страницу' }}
                        </AdminButton>

                        <RouterLink to="/admin/pages" class="button-link">
                            Закрыть
                        </RouterLink>
                    </div>
                </form>
            </AdminCard>

            <AdminCard>
                <div class="admin-stack">
                    <h2>Минимальный набор полей</h2>
                    <ul class="admin-list">
                        <li>Заголовок и slug страницы.</li>
                        <li>Описание и контент.</li>
                        <li>Статус публикации и дата публикации.</li>
                        <li>Видимость страницы.</li>
                        <li>Шаблон и SEO-поля.</li>
                    </ul>

                    <div class="page-preview-box">
                        <p class="eyebrow">Публичная ссылка</p>
                        <p class="page-preview-box__value">{{ publicUrl }}</p>
                        <p class="muted">Чтобы перенести страницу из /review в /login/review, достаточно поменять родительскую страницу на Login. Главная страница сайта всегда открывается по адресу /.</p>
                    </div>
                </div>
            </AdminCard>
        </div>
    </AdminPage>
</template>