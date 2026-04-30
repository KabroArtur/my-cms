<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Highlight from '@tiptap/extension-highlight'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import HorizontalRule from '@tiptap/extension-horizontal-rule'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import Placeholder from '@tiptap/extension-placeholder'
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { fetchCurrentUser } from '../../api/auth'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { fetchApplicableAdditionalFields } from '../../api/additionalFields'
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
const creatorLabel = ref('Не указан')
const canUpdatePage = ref(true)
const mediaInsertSize = ref('original')
const additionalFieldGroups = ref([])
const additionalFieldValues = ref({})
const canManageAdditionalFields = ref(false)
const templateOptions = ref([{ value: 'default', label: 'По умолчанию', description: 'Основной шаблон темы' }])
const headingLevels = [1, 2, 3, 4, 5, 6]

const form = reactive({
    title: '',
    slug: '',
    parent_id: '',
    excerpt: '',
    status: 'draft',
    visibility: 'public',
    is_home: false,
    published_at: '',
    template: 'default',
    meta_title: '',
    meta_description: '',
    featured_media_id: '',
    content: '',
})

const contentEditor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3, 4, 5, 6],
            },
        }),
        Underline,
        Highlight,
        Subscript,
        Superscript,
        HorizontalRule,
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
            alignments: ['left', 'center', 'right', 'justify'],
            defaultAlignment: 'left',
        }),
        Placeholder.configure({
            placeholder: 'Начните писать контент страницы...',
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            protocols: ['http', 'https', 'mailto', 'tel'],
        }),
        Image,
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    content: '',
    onUpdate({ editor }) {
        form.content = editor.getHTML()
    },
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
    form.template = page.template || 'default'
    form.meta_title = page.meta_title ?? ''
    form.meta_description = page.meta_description ?? ''
    form.featured_media_id = page.featured_media_id ?? ''
    form.content = page.content ?? ''
    featuredMedia.value = page.featured_media ?? null
    creatorLabel.value = page.creator?.name || page.creator?.username || 'Не указан'
    canUpdatePage.value = page.can?.update ?? true
    slugLocked.value = form.slug.trim() !== ''

    if (contentEditor.value) {
        contentEditor.value.commands.setContent(form.content || '', false)
    }

    additionalFieldGroups.value = page.additional_fields?.groups ?? []
    additionalFieldValues.value = page.additional_fields?.values ?? {}
}

function fieldOptions(field) {
    const options = Array.isArray(field?.settings?.options) ? field.settings.options : []

    return options
        .map((option) => {
            if (typeof option === 'string') {
                return { label: option, value: option }
            }

            if (option && typeof option === 'object') {
                const value = String(option.value ?? option.label ?? '')

                return {
                    label: String(option.label ?? value),
                    value,
                }
            }

            return { label: '', value: '' }
        })
        .filter((option) => option.value !== '')
}

function nestedFieldDefinitions(field) {
    return Array.isArray(field?.settings?.fields)
        ? field.settings.fields.filter((item) => item && typeof item === 'object' && item.key)
        : []
}

function defaultValueForField(field) {
    const type = String(field?.type ?? 'text').toLowerCase()

    if (field?.default_value !== null && field?.default_value !== undefined && field?.default_value !== '') {
        return field.default_value
    }

    if (type === 'toggle') {
        return false
    }

    if (type === 'group') {
        const value = {}

        nestedFieldDefinitions(field).forEach((nested) => {
            value[nested.key] = defaultValueForField(nested)
        })

        return value
    }

    if (type === 'repeater') {
        return []
    }

    return ''
}

function ensureFieldValue(field) {
    const key = String(field?.key ?? '')

    if (key === '') {
        return
    }

    if (!(key in additionalFieldValues.value)) {
        additionalFieldValues.value[key] = defaultValueForField(field)
    }
}

function ensureGroupValue(field) {
    ensureFieldValue(field)

    if (!additionalFieldValues.value[field.key] || typeof additionalFieldValues.value[field.key] !== 'object' || Array.isArray(additionalFieldValues.value[field.key])) {
        additionalFieldValues.value[field.key] = {}
    }

    nestedFieldDefinitions(field).forEach((nested) => {
        if (!(nested.key in additionalFieldValues.value[field.key])) {
            additionalFieldValues.value[field.key][nested.key] = defaultValueForField(nested)
        }
    })
}

function ensureRepeaterValue(field) {
    ensureFieldValue(field)

    if (!Array.isArray(additionalFieldValues.value[field.key])) {
        additionalFieldValues.value[field.key] = []
    }
}

function addRepeaterRow(field) {
    ensureRepeaterValue(field)

    const row = {}

    nestedFieldDefinitions(field).forEach((nested) => {
        row[nested.key] = defaultValueForField(nested)
    })

    additionalFieldValues.value[field.key].push(row)
}

function removeRepeaterRow(fieldKey, index) {
    if (!Array.isArray(additionalFieldValues.value[fieldKey])) {
        return
    }

    additionalFieldValues.value[fieldKey].splice(index, 1)
}

function hydrateAdditionalFields(groups, values = {}) {
    additionalFieldGroups.value = Array.isArray(groups) ? groups : []
    additionalFieldValues.value = { ...(values || {}) }

    additionalFieldGroups.value.forEach((group) => {
        const fields = Array.isArray(group.fields) ? group.fields : []

        fields.forEach((field) => ensureFieldValue(field))
    })
}

async function loadApplicableAdditionalFields() {
    try {
        const payload = await fetchApplicableAdditionalFields({
            page_id: isCreateMode.value ? undefined : pageId.value,
            template: form.template || 'default',
        })

        const groups = payload.data?.groups ?? []
        const values = payload.data?.values ?? {}
        hydrateAdditionalFields(groups, {
            ...values,
            ...additionalFieldValues.value,
        })
    } catch (error) {
        console.error(error)
    }
}

async function loadMedia(folderId = null) {
    mediaLoading.value = true
    mediaErrorMessage.value = ''

    try {
        const settingsPayload = await loadCmsSettings()
        mediaInsertSize.value = settingsPayload.settings?.media_default_insert_variant || 'original'
        templateOptions.value = settingsPayload.options?.page_templates ?? templateOptions.value
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

function resolveMediaUrl(file) {
    if (mediaInsertSize.value === 'original') {
        return file.url
    }

    return file.variants?.[mediaInsertSize.value]?.url || file.url
}

async function insertIntoContent(value) {
    const insertValue = String(value)

    if (!contentEditor.value) {
        form.content = `${form.content}${form.content ? '' : ''}${insertValue}`
        return
    }

    contentEditor.value.chain().focus().insertContent(insertValue).run()
    form.content = contentEditor.value.getHTML()
}

async function insertMediaUrl(file) {
    await insertIntoContent(resolveMediaUrl(file))
}

async function insertMediaImage(file) {
    if (!contentEditor.value) {
        return
    }

    contentEditor.value.chain().focus().setImage({
        src: resolveMediaUrl(file),
        alt: file.alt_text || file.original_name || '',
    }).run()

    form.content = contentEditor.value.getHTML()
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
    creatorLabel.value = 'Вы'
    canUpdatePage.value = true
    slugLocked.value = false
    additionalFieldGroups.value = []
    additionalFieldValues.value = {}
    validationErrors.value = {}
    errorMessage.value = ''
}

function toggleLink() {
    if (!contentEditor.value) {
        return
    }

    const currentHref = contentEditor.value.getAttributes('link').href
    const nextHref = window.prompt('Введите URL ссылки', currentHref || 'https://')

    if (nextHref === null) {
        return
    }

    const normalized = nextHref.trim()

    if (normalized === '') {
        contentEditor.value.chain().focus().unsetLink().run()

        return
    }

    contentEditor.value.chain().focus().setLink({ href: normalized }).run()
}

function setParagraph() {
    if (!contentEditor.value) {
        return
    }

    contentEditor.value.chain().focus().setParagraph().run()
}

function setHeading(level) {
    if (!contentEditor.value || !Number.isInteger(level) || level < 1 || level > 6) {
        return
    }

    contentEditor.value.chain().focus().setHeading({ level }).run()
}

function toggleTextAlign(align) {
    if (!contentEditor.value) {
        return
    }

    contentEditor.value.chain().focus().setTextAlign(align).run()
}

function insertTable() {
    if (!contentEditor.value) {
        return
    }

    contentEditor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}

function toolbarButtonClass(isActive) {
    return {
        'tiptap-toolbar-btn': true,
        'is-active': isActive,
    }
}

async function loadPage() {
    loading.value = true
    errorMessage.value = ''

    try {
        const currentUserPromise = fetchCurrentUser()
        const pagesPayloadPromise = fetchPageTree()

        if (isCreateMode.value) {
            const pagesPayload = await pagesPayloadPromise
            const currentUserPayload = await currentUserPromise
            allPages.value = pagesPayload.data ?? []
            canManageAdditionalFields.value = (currentUserPayload.data?.permissions ?? []).includes('pages.additional_fields.manage')
            resetForm()
            await loadApplicableAdditionalFields()

            return
        }

        const [payload, pagesPayload] = await Promise.all([
            fetchPage(pageId.value),
            pagesPayloadPromise,
        ])
        const currentUserPayload = await currentUserPromise

        allPages.value = pagesPayload.data ?? []
        canManageAdditionalFields.value = (currentUserPayload.data?.permissions ?? []).includes('pages.additional_fields.manage')
        fillForm(payload.data)
        await loadApplicableAdditionalFields()
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить страницу.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function submitForm() {
    if (!isCreateMode.value && !canUpdatePage.value) {
        errorMessage.value = 'У вас нет прав на редактирование этой страницы.'

        return
    }

    saving.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        const submitPayload = {
            ...form,
            template: form.template === 'default' ? '' : form.template,
            additional_fields: additionalFieldValues.value,
        }

        const payload = isCreateMode.value
            ? await createPage(submitPayload)
            : await updatePage(pageId.value, submitPayload)

        if (isCreateMode.value) {
            await router.replace({ name: 'page-edit', params: { id: payload.data.id } })
            await loadPage()
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

watch(() => form.template, () => {
    loadApplicableAdditionalFields()
})

onBeforeUnmount(() => {
    contentEditor.value?.destroy()
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

                <RouterLink :to="{ name: 'pages' }" class="button-link">
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
                        <div class="admin-editor-toolbar">
                            <div class="tiptap-toolbar-group">
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('paragraph'))" type="button" @click="setParagraph">Параграф</AdminButton>
                                <AdminButton
                                    v-for="level in headingLevels"
                                    :key="`h-${level}`"
                                    :class="toolbarButtonClass(contentEditor?.isActive('heading', { level }))"
                                    type="button"
                                    @click="setHeading(level)"
                                >
                                    H{{ level }}
                                </AdminButton>
                            </div>

                            <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

                            <div class="tiptap-toolbar-group">
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('bold'))" type="button" @click="contentEditor?.chain().focus().toggleBold().run()">Жирный</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('italic'))" type="button" @click="contentEditor?.chain().focus().toggleItalic().run()">Курсив</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('underline'))" type="button" @click="contentEditor?.chain().focus().toggleUnderline().run()">Подчеркн.</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('strike'))" type="button" @click="contentEditor?.chain().focus().toggleStrike().run()">Зачеркн.</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('code'))" type="button" @click="contentEditor?.chain().focus().toggleCode().run()">Код</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('codeBlock'))" type="button" @click="contentEditor?.chain().focus().toggleCodeBlock().run()">Блок кода</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('highlight'))" type="button" @click="contentEditor?.chain().focus().toggleHighlight().run()">Подсветка</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('link'))" type="button" @click="toggleLink">Ссылка</AdminButton>
                            </div>

                            <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

                            <div class="tiptap-toolbar-group">
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('bulletList'))" type="button" @click="contentEditor?.chain().focus().toggleBulletList().run()">Список •</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('orderedList'))" type="button" @click="contentEditor?.chain().focus().toggleOrderedList().run()">Список 1.</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('taskList'))" type="button" @click="contentEditor?.chain().focus().toggleTaskList().run()">Чек-лист</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('blockquote'))" type="button" @click="contentEditor?.chain().focus().toggleBlockquote().run()">Цитата</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().setHorizontalRule().run()">Линия</AdminButton>
                            </div>

                            <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

                            <div class="tiptap-toolbar-group">
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive({ textAlign: 'left' }))" type="button" @click="toggleTextAlign('left')">Слева</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive({ textAlign: 'center' }))" type="button" @click="toggleTextAlign('center')">Центр</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive({ textAlign: 'right' }))" type="button" @click="toggleTextAlign('right')">Справа</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive({ textAlign: 'justify' }))" type="button" @click="toggleTextAlign('justify')">По ширине</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('subscript'))" type="button" @click="contentEditor?.chain().focus().toggleSubscript().run()">x₂</AdminButton>
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('superscript'))" type="button" @click="contentEditor?.chain().focus().toggleSuperscript().run()">x²</AdminButton>
                            </div>

                            <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

                            <div class="tiptap-toolbar-group">
                                <AdminButton :class="toolbarButtonClass(contentEditor?.isActive('table'))" type="button" @click="insertTable">Таблица</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().addColumnBefore().run()">+Колонка</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().addRowAfter().run()">+Строка</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().deleteColumn().run()">-Колонка</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().deleteRow().run()">-Строка</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().deleteTable().run()">Удалить таблицу</AdminButton>
                            </div>

                            <span class="tiptap-toolbar-separator" aria-hidden="true"></span>

                            <div class="tiptap-toolbar-group">
                                <AdminButton type="button" @click="contentEditor?.chain().focus().undo().run()">Назад</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().redo().run()">Вперед</AdminButton>
                                <AdminButton type="button" @click="contentEditor?.chain().focus().unsetAllMarks().clearNodes().run()">Сброс</AdminButton>
                            </div>
                        </div>
                        <EditorContent :editor="contentEditor" class="admin-editor tiptap-editor" />
                    </label>

                    <section class="additional-fields-block">
                        <div class="additional-fields-block__header">
                            <div>
                                <h2>Дополнительные поля</h2>
                                <p class="muted">Локальные хаотичные поля лучше не использовать. Для массового сценария создавайте наборы в структуре контента.</p>
                            </div>

                            <RouterLink v-if="canManageAdditionalFields" :to="{ name: 'content-structure' }" class="button-link">
                                Настроить структуру
                            </RouterLink>
                        </div>

                        <p v-if="additionalFieldGroups.length === 0" class="muted">Для текущего шаблона нет подключенных наборов полей.</p>

                        <div v-for="group in additionalFieldGroups" :key="group.id" class="additional-fields-group">
                            <h3>{{ group.name }}</h3>
                            <p v-if="group.description" class="muted">{{ group.description }}</p>

                            <div class="admin-stack">
                                <div v-for="field in group.fields" :key="field.key" class="admin-form-label">
                                    <span>{{ field.label }} <small class="muted">({{ field.key }})</small></span>

                                    <input
                                        v-if="['text', 'url'].includes(field.type)"
                                        v-model="additionalFieldValues[field.key]"
                                        class="admin-input"
                                        :type="field.type === 'url' ? 'url' : 'text'"
                                        @focus="ensureFieldValue(field)"
                                    >

                                    <input
                                        v-else-if="field.type === 'number'"
                                        v-model.number="additionalFieldValues[field.key]"
                                        class="admin-input"
                                        type="number"
                                        @focus="ensureFieldValue(field)"
                                    >

                                    <label v-else-if="field.type === 'toggle'" class="muted">
                                        <input
                                            v-model="additionalFieldValues[field.key]"
                                            type="checkbox"
                                            @focus="ensureFieldValue(field)"
                                        >
                                        Да / Нет
                                    </label>

                                    <select
                                        v-else-if="field.type === 'select'"
                                        v-model="additionalFieldValues[field.key]"
                                        class="admin-select"
                                        @focus="ensureFieldValue(field)"
                                    >
                                        <option value="">Выберите значение</option>
                                        <option v-for="option in fieldOptions(field)" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>

                                    <input
                                        v-else-if="field.type === 'image'"
                                        v-model.number="additionalFieldValues[field.key]"
                                        class="admin-input"
                                        type="number"
                                        placeholder="ID файла из медиатеки"
                                        @focus="ensureFieldValue(field)"
                                    >

                                    <div v-else-if="field.type === 'group'" class="additional-fields-nested">
                                        <div class="admin-actions-row">
                                            <small class="muted">Группа вложенных полей</small>
                                            <AdminButton type="button" @click="ensureGroupValue(field)">Инициализировать</AdminButton>
                                        </div>

                                        <div v-for="nested in nestedFieldDefinitions(field)" :key="`${field.key}-${nested.key}`" class="admin-form-label">
                                            <span>{{ nested.label || nested.key }}</span>
                                            <input
                                                v-if="['text', 'url'].includes(nested.type || 'text')"
                                                v-model="additionalFieldValues[field.key][nested.key]"
                                                class="admin-input"
                                                :type="(nested.type || 'text') === 'url' ? 'url' : 'text'"
                                                @focus="ensureGroupValue(field)"
                                            >
                                            <input
                                                v-else-if="(nested.type || 'text') === 'number'"
                                                v-model.number="additionalFieldValues[field.key][nested.key]"
                                                class="admin-input"
                                                type="number"
                                                @focus="ensureGroupValue(field)"
                                            >
                                            <label v-else-if="(nested.type || 'text') === 'toggle'" class="muted">
                                                <input v-model="additionalFieldValues[field.key][nested.key]" type="checkbox" @focus="ensureGroupValue(field)">
                                                Да / Нет
                                            </label>
                                            <textarea
                                                v-else
                                                v-model="additionalFieldValues[field.key][nested.key]"
                                                class="admin-textarea"
                                                rows="3"
                                                @focus="ensureGroupValue(field)"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div v-else-if="field.type === 'repeater'" class="additional-fields-nested">
                                        <div class="admin-actions-row">
                                            <small class="muted">Повторяемые элементы</small>
                                            <AdminButton type="button" @click="addRepeaterRow(field)">+ Добавить элемент</AdminButton>
                                        </div>

                                        <div
                                            v-for="(row, rowIndex) in (additionalFieldValues[field.key] || [])"
                                            :key="`${field.key}-row-${rowIndex}`"
                                            class="additional-fields-repeater-row"
                                        >
                                            <div v-for="nested in nestedFieldDefinitions(field)" :key="`${field.key}-${nested.key}-${rowIndex}`" class="admin-form-label">
                                                <span>{{ nested.label || nested.key }}</span>
                                                <input
                                                    v-if="['text', 'url'].includes(nested.type || 'text')"
                                                    v-model="row[nested.key]"
                                                    class="admin-input"
                                                    :type="(nested.type || 'text') === 'url' ? 'url' : 'text'"
                                                >
                                                <input
                                                    v-else-if="(nested.type || 'text') === 'number'"
                                                    v-model.number="row[nested.key]"
                                                    class="admin-input"
                                                    type="number"
                                                >
                                                <label v-else-if="(nested.type || 'text') === 'toggle'" class="muted">
                                                    <input v-model="row[nested.key]" type="checkbox">
                                                    Да / Нет
                                                </label>
                                                <textarea v-else v-model="row[nested.key]" class="admin-textarea" rows="3"></textarea>
                                            </div>

                                            <AdminButton type="button" @click="removeRepeaterRow(field.key, rowIndex)">Удалить элемент</AdminButton>
                                        </div>
                                    </div>

                                    <textarea
                                        v-else
                                        v-model="additionalFieldValues[field.key]"
                                        class="admin-textarea"
                                        :rows="field.type === 'editor' ? 6 : 4"
                                        @focus="ensureFieldValue(field)"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>
                    <p v-if="!isCreateMode && !canUpdatePage" class="error-text">Только автор страницы или администратор может ее изменять.</p>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving || (!isCreateMode && !canUpdatePage)">
                            {{ saving ? 'Сохранение...' : 'Сохранить страницу' }}
                        </AdminButton>

                        <RouterLink :to="{ name: 'pages' }" class="button-link">
                            Закрыть
                        </RouterLink>
                    </div>
                </form>
            </AdminCard>

            <AdminCard>
                <div class="admin-stack">
                    <div class="page-preview-box page-editor-aside-box">
                        <p class="eyebrow">Публикация</p>
                        <p class="page-preview-box__value">{{ publicUrl }}</p>
                        <p class="muted">Создал: {{ creatorLabel }}</p>

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
                            <select v-model="form.template" class="admin-select">
                                <option v-for="option in templateOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <small v-if="validationErrors.template" class="error-text">{{ validationErrors.template[0] }}</small>
                        </label>
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