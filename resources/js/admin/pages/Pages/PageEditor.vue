<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { loadCmsSettings } from '../../composables/useCmsSettings'
import { fetchMediaLibrary } from '../../api/media'
import { createPage, fetchPage, fetchPageTree, updatePage } from '../../api/pages'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const slugLocked = ref(false)
const allPages = ref([])
const mediaLoading = ref(false)
const mediaErrorMessage = ref('')
const mediaCurrentFolder = ref(null)
const mediaBreadcrumbs = ref([])
const mediaFolders = ref([])
const mediaFiles = ref([])
const featuredMedia = ref(null)
const contentTextarea = ref(null)
const mediaInsertSize = ref('original')

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
    featured_media_id: '',
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
    form.featured_media_id = page.featured_media_id ?? ''
    form.content = page.content ?? ''
    featuredMedia.value = page.featured_media ?? null
    slugLocked.value = form.slug.trim() !== ''
}

async function loadMedia(folderId = null) {
    mediaLoading.value = true
    mediaErrorMessage.value = ''

    try {
        const settingsPayload = await loadCmsSettings()
        mediaInsertSize.value = settingsPayload.settings?.media_default_insert_variant || 'original'
        const payload = await fetchMediaLibrary(folderId)
        mediaCurrentFolder.value = payload.data?.current_folder ?? null
        mediaBreadcrumbs.value = payload.data?.breadcrumbs ?? []
        mediaFolders.value = payload.data?.folders ?? []
        mediaFiles.value = payload.data?.files ?? []
    } catch (error) {
        mediaErrorMessage.value = 'Не удалось загрузить медиафайлы.'
        console.error(error)
    } finally {
        mediaLoading.value = false
    }
}

async function openMediaFolder(folder) {
    await loadMedia(folder.id)
}

async function openMediaRoot() {
    await loadMedia(null)
}

function buildImageTag(file) {
    const source = resolveMediaUrl(file)
    const alt = file.alt_text || file.original_name

    return `<img src="${source}" alt="${alt}">`
}

function resolveMediaUrl(file) {
    if (mediaInsertSize.value === 'original') {
        return file.url
    }

    return file.variants?.[mediaInsertSize.value]?.url || file.url
}

async function insertIntoContent(value) {
    const textarea = contentTextarea.value
    const insertValue = String(value)

    if (!textarea) {
        form.content = `${form.content}${form.content ? '\n' : ''}${insertValue}`
        return
    }

    const start = textarea.selectionStart ?? form.content.length
    const end = textarea.selectionEnd ?? form.content.length
    const before = form.content.slice(0, start)
    const after = form.content.slice(end)

    form.content = `${before}${insertValue}${after}`

    await nextTick()

    textarea.focus()
    const position = start + insertValue.length
    textarea.setSelectionRange(position, position)
}

async function insertMediaUrl(file) {
    await insertIntoContent(resolveMediaUrl(file))
}

async function insertMediaImage(file) {
    await insertIntoContent(buildImageTag(file))
}

function setFeaturedMedia(file) {
    form.featured_media_id = file.id
    featuredMedia.value = file
}

function clearFeaturedMedia() {
    form.featured_media_id = ''
    featuredMedia.value = null
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

onMounted(() => {
    loadPage()
    loadMedia()
})
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

                        <div class="admin-form-label page-home-toggle">
                            <span>Главная страница</span>
                            <small class="muted">Выбирается в разделе настроек сайта, чтобы не держать этот переключатель на каждой странице.</small>
                        </div>
                    </div>

                    <label class="admin-form-label">
                        <span>Meta title</span>
                        <input v-model="form.meta_title" class="admin-input" type="text" placeholder="SEO заголовок страницы">
                    </label>

                    <label class="admin-form-label">
                        <span>Meta description</span>
                        <textarea v-model="form.meta_description" class="admin-textarea" rows="3" placeholder="SEO описание страницы"></textarea>
                    </label>

                    <div class="page-featured-media">
                        <div class="page-featured-media__header">
                            <span>Обложка страницы</span>
                            <input v-model="form.featured_media_id" type="hidden">
                            <AdminButton v-if="featuredMedia" type="button" @click="clearFeaturedMedia">
                                Очистить
                            </AdminButton>
                        </div>

                        <div v-if="featuredMedia" class="page-featured-media__card">
                            <div class="page-featured-media__preview">
                                <img :src="featuredMedia.preview_url || featuredMedia.url" :alt="featuredMedia.alt_text || featuredMedia.original_name">
                            </div>

                            <div class="page-featured-media__body">
                                <strong>{{ featuredMedia.original_name }}</strong>
                                <p class="muted">{{ featuredMedia.size_human }}<span v-if="featuredMedia.width && featuredMedia.height"> | {{ featuredMedia.width }} x {{ featuredMedia.height }}</span></p>
                            </div>
                        </div>

                        <p v-else class="muted">Обложка пока не выбрана.</p>
                    </div>

                    <label class="admin-form-label">
                        <span>Контент</span>
                        <textarea ref="contentTextarea" v-model="form.content" class="admin-textarea admin-editor" rows="16" placeholder="Временный editor через textarea. Кастомный редактор можно будет подключить позже."></textarea>
                        <small class="muted">Сейчас используется простой editor-слой через textarea. Ниже можно вставлять URL или готовый img-тег прямо из медиатеки.</small>
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

                    <div class="page-media-picker">
                        <div class="page-media-picker__header">
                            <h2>Медиа для вставки</h2>
                            <div class="media-breadcrumbs">
                                <button type="button" class="button-link" @click="openMediaRoot">
                                    Корень
                                </button>

                                <template v-for="folder in mediaBreadcrumbs" :key="folder.id">
                                    <span>/</span>
                                    <button type="button" class="button-link" @click="openMediaFolder(folder)">
                                        {{ folder.name }}
                                    </button>
                                </template>
                            </div>
                        </div>

                        <p v-if="mediaLoading" class="muted">Загрузка медиатеки...</p>
                        <p v-else-if="mediaErrorMessage" class="error-text">{{ mediaErrorMessage }}</p>

                        <div v-if="mediaFolders.length > 0" class="page-media-picker__folders">
                            <button
                                v-for="folder in mediaFolders"
                                :key="folder.id"
                                type="button"
                                class="page-media-picker__folder"
                                @click="openMediaFolder(folder)"
                            >
                                {{ folder.name }}
                            </button>
                        </div>

                        <div v-if="mediaFiles.length > 0" class="page-media-picker__files">
                            <label class="admin-form-label">
                                <span>Размер для вставки</span>
                                <select v-model="mediaInsertSize" class="admin-select">
                                    <option value="original">Оригинал</option>
                                    <option value="large">Large</option>
                                    <option value="medium">Medium</option>
                                    <option value="thumb">Mini / Thumb</option>
                                </select>
                                <small class="muted">Превью в админке всегда компактное, а здесь выбирается размер URL и img-тега для контента.</small>
                            </label>

                            <article v-for="file in mediaFiles" :key="file.id" class="page-media-picker__file-card">
                                <div class="page-media-picker__preview">
                                    <img :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
                                </div>

                                <div class="page-media-picker__file-body">
                                    <h3>{{ file.original_name }}</h3>
                                    <p class="muted">{{ file.size_human }}<span v-if="file.width && file.height"> | {{ file.width }} x {{ file.height }}</span></p>

                                    <div class="admin-actions-row">
                                        <AdminButton type="button" @click="insertMediaUrl(file)">
                                            Вставить URL
                                        </AdminButton>

                                        <AdminButton type="button" variant="primary" @click="insertMediaImage(file)">
                                            Вставить img
                                        </AdminButton>

                                        <AdminButton type="button" @click="setFeaturedMedia(file)">
                                            Сделать обложкой
                                        </AdminButton>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <p v-else-if="!mediaLoading" class="muted">В текущей папке пока нет изображений.</p>
                    </div>
                </div>
            </AdminCard>
        </div>
    </AdminPage>
</template>