<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { fetchCurrentUser } from '../../api/auth'
import { loadCmsSettings } from '../../composables/useCmsSettings'
import { fetchPageTree } from '../../api/pages'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import {
    createFieldGroup,
    deleteFieldGroup,
    fetchFieldGroups,
    updateFieldGroup,
} from '../../api/additionalFields'

const router = useRouter()

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const errorMessage = ref('')
const groups = ref([])
const activeGroupId = ref(null)
const accessChecked = ref(false)
const pageOptions = ref([])
const pageSearch = reactive({})
const templateOptions = ref([{ value: 'default', label: 'По умолчанию', description: 'Основной шаблон темы' }])

const ruleFieldOptions = [
    { value: 'template', label: 'Шаблон страницы', placeholder: '', hint: 'Набор появится только у страниц с выбранным шаблоном.' },
    { value: 'page_id', label: 'Страницы', placeholder: '', hint: 'Выберите конкретные страницы из уже созданных.' },
]

const emptyRule = () => ({ field: 'template', operator: '=', value: 'default' })
const emptyField = () => ({
    label: '',
    key: '',
    type: 'text',
    settings_json: '{}',
    default_value: '',
    is_required: false,
    sort_order: 0,
})

const form = reactive({
    id: null,
    name: '',
    key: '',
    description: '',
    is_active: true,
    sort_order: 0,
    location_rules_mode: 'all',
    location_rules: [],
    fields: [emptyField()],
})

const isEditing = computed(() => Number.isInteger(form.id) && form.id > 0)

function resetForm() {
    form.id = null
    form.name = ''
    form.key = ''
    form.description = ''
    form.is_active = true
    form.sort_order = 0
    form.location_rules_mode = 'all'
    form.location_rules = []
    form.fields = [emptyField()]
    activeGroupId.value = null
}

function hydrateForm(group) {
    form.id = group.id
    form.name = group.name || ''
    form.key = group.key || ''
    form.description = group.description || ''
    form.is_active = Boolean(group.is_active)
    form.sort_order = Number(group.sort_order || 0)
    form.location_rules_mode = group.location_rules?.mode === 'any' ? 'any' : 'all'

    const rules = Array.isArray(group.location_rules?.rules)
        ? group.location_rules.rules.filter((rule) => rule?.field !== 'entity_type')
        : []

    form.location_rules = rules.map((rule) => ({
        field: rule.field || 'template',
        operator: rule.operator || '=',
        value: String(rule.value || ''),
    }))

    const sourceFields = Array.isArray(group.fields) && group.fields.length > 0 ? group.fields : [emptyField()]

    form.fields = sourceFields.map((field, index) => ({
        label: field.label || '',
        key: field.key || '',
        type: field.type || 'text',
        settings_json: JSON.stringify(field.settings || {}, null, 2),
        default_value: field.default_value ?? '',
        is_required: Boolean(field.is_required),
        sort_order: Number(field.sort_order ?? index),
    }))

    activeGroupId.value = group.id
}

function addRule() {
    form.location_rules.push(emptyRule())
}

function ruleMeta(field) {
    return ruleFieldOptions.find((option) => option.value === field) || ruleFieldOptions[0]
}

function operatorOptions(field) {
    if (field === 'page_id') {
        return [
            { value: 'in', label: 'в списке' },
            { value: 'not_in', label: 'не в списке' },
        ]
    }

    return [
        { value: '=', label: '=' },
        { value: '!=', label: '!=' },
    ]
}

function flattenPages(nodes, bucket = []) {
    for (const node of nodes || []) {
        bucket.push({
            id: node.id,
            slug: node.slug || '',
            path: node.path || '',
            title: node.title || `#${node.id}`,
            template: node.template || '',
            is_home: Boolean(node.is_home),
        })
    }

    return bucket
}

function formatPageLabel(page) {
    const path = page.path ? `/${page.path}` : '/'
    const template = page.template ? ` | ${page.template}` : ''

    return `${page.title} (${path}${template})`
}

function applyPreset(preset) {
    if (preset === 'all-pages') {
        form.location_rules_mode = 'all'
        form.location_rules = []

        return
    }

    if (preset === 'template-only') {
        form.location_rules_mode = 'all'
        form.location_rules = [
            { field: 'template', operator: '=', value: 'default' },
        ]

        return
    }

    if (preset === 'selected-pages') {
        form.location_rules_mode = 'all'
        form.location_rules = [
            { field: 'page_id', operator: 'in', value: '' },
        ]
    }
}

function selectedRuleValues(rule) {
    return String(rule.value || '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean)
}

function syncRuleOperator(rule) {
    const supported = operatorOptions(rule.field).map((option) => option.value)

    if (!supported.includes(rule.operator)) {
        rule.operator = supported[0]
    }

    if (rule.field === 'template' && rule.value === '') {
        rule.value = 'default'
    }
}

function updatePageRule(rule, event) {
    rule.value = Array.from(event.target.selectedOptions)
        .map((option) => option.value)
        .filter(Boolean)
        .join(',')
}

function filteredPageOptions(index) {
    const query = String(pageSearch[index] || '').trim().toLowerCase()

    if (query === '') {
        return pageOptions.value
    }

    return pageOptions.value.filter((page) => {
        const haystack = [page.title, page.slug, page.path, page.template]
            .map((value) => String(value || '').toLowerCase())
            .join(' ')

        return haystack.includes(query)
    })
}

function removeRule(index) {
    if (form.location_rules.length === 1) {
        form.location_rules[0] = emptyRule()

        return
    }

    form.location_rules.splice(index, 1)
}

function addField() {
    form.fields.push({
        ...emptyField(),
        sort_order: form.fields.length,
    })
}

function removeField(index) {
    if (form.fields.length === 1) {
        form.fields[0] = emptyField()

        return
    }

    form.fields.splice(index, 1)
}

function normalizePayload() {
    return {
        name: form.name.trim(),
        key: form.key.trim().toLowerCase(),
        description: form.description.trim() || null,
        is_active: Boolean(form.is_active),
        sort_order: Number(form.sort_order || 0),
        location_rules: {
            mode: form.location_rules_mode === 'any' ? 'any' : 'all',
            rules: [{ field: 'entity_type', operator: '=', value: 'page' }]
                .concat(form.location_rules
                .map((rule) => ({
                    field: String(rule.field || 'template'),
                    operator: String(rule.operator || '='),
                    value: String(rule.value || '').trim(),
                }))
                .filter((rule) => rule.value !== '')),
        },
        fields: form.fields.map((field, index) => ({
            label: String(field.label || '').trim(),
            key: String(field.key || '').trim().toLowerCase(),
            type: String(field.type || 'text'),
            settings: parseSettings(field.settings_json),
            default_value: field.default_value === '' ? null : field.default_value,
            is_required: Boolean(field.is_required),
            sort_order: Number(field.sort_order ?? index),
        })),
    }
}

function parseSettings(settingsJson) {
    try {
        const parsed = JSON.parse(settingsJson || '{}')

        return typeof parsed === 'object' && parsed !== null ? parsed : {}
    } catch {
        return {}
    }
}

async function loadGroups() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [payload, pagesPayload] = await Promise.all([
            fetchFieldGroups(),
            fetchPageTree().catch(() => ({ data: [] })),
        ])
        const settingsPayload = await loadCmsSettings()

        groups.value = payload.data || []
        pageOptions.value = flattenPages(pagesPayload.data || [])
        templateOptions.value = settingsPayload.options?.page_templates ?? templateOptions.value
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить наборы дополнительных полей.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function ensureAccess() {
    try {
        const payload = await fetchCurrentUser()
        const permissions = new Set(payload.data?.permissions ?? [])

        if (!permissions.has('pages.additional_fields.manage')) {
            await router.replace({ name: 'settings' })

            return false
        }

        accessChecked.value = true

        return true
    } catch (error) {
        errorMessage.value = 'Не удалось проверить права доступа.'
        console.error(error)

        return false
    }
}

async function saveGroup() {
    saving.value = true
    errorMessage.value = ''

    try {
        const payload = normalizePayload()

        if (isEditing.value) {
            await updateFieldGroup(form.id, payload)
        } else {
            await createFieldGroup(payload)
        }

        await loadGroups()

        if (isEditing.value) {
            const next = groups.value.find((group) => group.id === form.id)

            if (next) {
                hydrateForm(next)
            }
        } else {
            resetForm()
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Не удалось сохранить набор.'
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removeGroup() {
    if (!isEditing.value) {
        return
    }

    deleting.value = true
    errorMessage.value = ''

    try {
        await deleteFieldGroup(form.id)
        await loadGroups()
        resetForm()
    } catch (error) {
        errorMessage.value = 'Не удалось удалить набор.'
        console.error(error)
    } finally {
        deleting.value = false
    }
}

onMounted(async () => {
    if (await ensureAccess()) {
        await loadGroups()
    }
})
</script>

<template>
    <AdminPage
        eyebrow="Settings"
        title="Структура контента"
        description="Наборы дополнительных полей для шаблонов и страниц."
    >
        <p v-if="!accessChecked && !errorMessage" class="muted">Проверка прав доступа...</p>

        <div class="admin-grid">
            <AdminCard>
                <h2>Наборы дополнительных полей</h2>
                <p class="muted">Для массового использования создавайте наборы полей и назначайте их по правилам.</p>

                <p v-if="loading" class="muted">Загрузка...</p>

                <div v-else class="admin-stack">
                    <button
                        v-for="group in groups"
                        :key="group.id"
                        type="button"
                        class="page-media-picker__folder"
                        @click="hydrateForm(group)"
                    >
                        <strong>{{ group.name }}</strong>
                        <span class="muted">{{ group.key }} | {{ group.is_active ? 'Активен' : 'Выключен' }}</span>
                    </button>

                    <p v-if="groups.length === 0" class="muted">Наборы пока не созданы.</p>
                </div>
            </AdminCard>

            <AdminCard>
                <form class="admin-form-stack" @submit.prevent="saveGroup">
                    <label class="admin-form-label">
                        <span>Название набора</span>
                        <input v-model="form.name" class="admin-input" type="text" placeholder="Hero">
                    </label>

                    <label class="admin-form-label">
                        <span>Ключ набора</span>
                        <input v-model="form.key" class="admin-input" type="text" placeholder="hero_fields">
                    </label>

                    <label class="admin-form-label">
                        <span>Описание</span>
                        <textarea v-model="form.description" class="admin-textarea" rows="2" placeholder="Короткое описание набора"></textarea>
                    </label>

                    <div class="page-meta-grid">
                        <label class="admin-form-label">
                            <span>Активен</span>
                            <input v-model="form.is_active" type="checkbox">
                        </label>

                        <label class="admin-form-label">
                            <span>Порядок</span>
                            <input v-model.number="form.sort_order" class="admin-input" type="number" min="0">
                        </label>
                    </div>

                    <section class="admin-stack">
                        <div class="admin-actions-row">
                            <h3>Правила назначения</h3>
                            <AdminButton type="button" @click="addRule">+ Добавить правило</AdminButton>
                        </div>

                        <div class="admin-actions-row">
                            <AdminButton type="button" @click="applyPreset('all-pages')">Все страницы</AdminButton>
                            <AdminButton type="button" @click="applyPreset('template-only')">Только шаблон</AdminButton>
                            <AdminButton type="button" @click="applyPreset('selected-pages')">Выбранные страницы</AdminButton>
                        </div>

                        <label class="admin-form-label">
                            <span>Как применять правила</span>
                            <select v-model="form.location_rules_mode" class="admin-select">
                                <option value="all">Все условия одновременно (AND)</option>
                                <option value="any">Хотя бы одно условие (OR)</option>
                            </select>
                            <small class="muted">AND для строгого назначения, OR если набор должен появляться на одной из нескольких групп страниц.</small>
                        </label>

                        <div v-for="(rule, index) in form.location_rules" :key="`rule-${index}`" class="page-meta-grid">
                            <label class="admin-form-label">
                                <span>Поле</span>
                                <select v-model="rule.field" class="admin-select" @change="syncRuleOperator(rule)">
                                    <option v-for="option in ruleFieldOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="admin-form-label">
                                <span>Оператор</span>
                                <select v-model="rule.operator" class="admin-select">
                                    <option v-for="option in operatorOptions(rule.field)" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>

                            <label v-if="rule.field === 'template'" class="admin-form-label">
                                <span>Значение</span>
                                <select v-model="rule.value" class="admin-select">
                                    <option v-for="option in templateOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <small class="muted">{{ ruleMeta(rule.field).hint }}</small>
                            </label>

                            <label v-else class="admin-form-label">
                                <span>Страницы</span>
                                <input
                                    v-model="pageSearch[index]"
                                    class="admin-input"
                                    type="search"
                                    placeholder="Поиск по названию, slug, пути или шаблону"
                                >
                                <select class="admin-select" multiple size="6" @change="updatePageRule(rule, $event)">
                                    <option
                                        v-for="page in filteredPageOptions(index)"
                                        :key="`rule-page-${index}-${page.id}`"
                                        :value="page.id"
                                        :selected="selectedRuleValues(rule).includes(String(page.id))"
                                    >
                                        {{ formatPageLabel(page) }}
                                    </option>
                                </select>
                                <small class="muted">Можно выбрать несколько страниц. Значения ID скрыты, в правиле сохраняется привязка к выбранным страницам.</small>
                            </label>

                            <div class="admin-form-label">
                                <span>&nbsp;</span>
                                <AdminButton type="button" @click="removeRule(index)">Удалить правило</AdminButton>
                            </div>
                        </div>

                        <p class="muted">Если правил нет, набор работает на всех страницах. Обычно здесь достаточно одного правила: конкретные страницы или конкретный шаблон страницы.</p>
                    </section>

                    <section class="admin-stack">
                        <div class="admin-actions-row">
                            <h3>Поля набора</h3>
                            <AdminButton type="button" @click="addField">+ Добавить поле</AdminButton>
                        </div>

                        <article
                            v-for="(field, index) in form.fields"
                            :key="`field-${index}`"
                            class="page-featured-media"
                        >
                            <div class="page-meta-grid">
                                <label class="admin-form-label">
                                    <span>Label</span>
                                    <input v-model="field.label" class="admin-input" type="text" placeholder="Hero title">
                                </label>

                                <label class="admin-form-label">
                                    <span>Key</span>
                                    <input v-model="field.key" class="admin-input" type="text" placeholder="hero_title">
                                </label>

                                <label class="admin-form-label">
                                    <span>Type</span>
                                    <select v-model="field.type" class="admin-select">
                                        <option value="text">text</option>
                                        <option value="textarea">textarea</option>
                                        <option value="editor">editor</option>
                                        <option value="image">image</option>
                                        <option value="url">url</option>
                                        <option value="number">number</option>
                                        <option value="toggle">toggle</option>
                                        <option value="select">select</option>
                                        <option value="group">group</option>
                                        <option value="repeater">repeater</option>
                                    </select>
                                </label>

                                <label class="admin-form-label">
                                    <span>Порядок</span>
                                    <input v-model.number="field.sort_order" class="admin-input" type="number" min="0">
                                </label>

                                <label class="admin-form-label">
                                    <span>Обязательное</span>
                                    <input v-model="field.is_required" type="checkbox">
                                </label>
                            </div>

                            <label class="admin-form-label">
                                <span>Default value</span>
                                <input v-model="field.default_value" class="admin-input" type="text" placeholder="Значение по умолчанию">
                            </label>

                            <label class="admin-form-label">
                                <span>Settings (JSON)</span>
                                <textarea
                                    v-model="field.settings_json"
                                    class="admin-textarea"
                                    rows="5"
                                    placeholder='Например: {"options":[{"label":"A","value":"a"}]}'
                                ></textarea>
                                <small class="muted">Для select передайте settings.options, для group/repeater - settings.fields.</small>
                            </label>

                            <AdminButton type="button" @click="removeField(index)">Удалить поле</AdminButton>
                        </article>
                    </section>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving">
                            {{ saving ? 'Сохранение...' : (isEditing ? 'Обновить набор' : 'Создать набор') }}
                        </AdminButton>

                        <AdminButton type="button" :disabled="deleting || !isEditing" @click="removeGroup">
                            {{ deleting ? 'Удаление...' : 'Удалить набор' }}
                        </AdminButton>

                        <button type="button" class="button-link" @click="resetForm">
                            Новый набор
                        </button>
                    </div>
                </form>
            </AdminCard>
        </div>
    </AdminPage>
</template>
