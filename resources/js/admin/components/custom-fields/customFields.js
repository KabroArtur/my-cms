export const FIELD_TYPE_OPTIONS = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'editor', label: 'Editor' },
    { value: 'image', label: 'Image' },
    { value: 'file', label: 'File' },
    { value: 'number', label: 'Number' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'switch', label: 'Switch' },
    { value: 'toggle', label: 'Toggle (legacy)' },
    { value: 'select', label: 'Select' },
    { value: 'radio', label: 'Radio' },
    { value: 'color', label: 'Color' },
    { value: 'date', label: 'Date' },
    { value: 'url', label: 'URL' },
    { value: 'email', label: 'Email' },
    { value: 'repeater', label: 'Repeater' },
    { value: 'group', label: 'Group' },
    { value: 'gallery', label: 'Gallery' },
]

export function normalizeFieldType(type = 'text') {
    const normalized = String(type || 'text').trim().toLowerCase()

    if (normalized === 'wysiwyg') {
        return 'editor'
    }

    return normalized || 'text'
}

export function cloneValue(value) {
    if (value === null || value === undefined) {
        return value
    }

    if (typeof structuredClone === 'function') {
        return structuredClone(value)
    }

    return JSON.parse(JSON.stringify(value))
}

export function sanitizeFieldKey(value = '') {
    return transliterateToLatin(String(value || ''))
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/-+/g, '-')
}

export function transliterateToLatin(value = '') {
    const map = {
        а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y',
        к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f',
        х: 'h', ц: 'cz', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
    }

    return Array.from(String(value || ''))
        .map((character) => {
            const lower = character.toLowerCase()

            return Object.prototype.hasOwnProperty.call(map, lower)
                ? map[lower]
                : character
        })
        .join('')
}

export function normalizeFieldOption(option = {}) {
    if (typeof option === 'string') {
        return {
            label: option,
            value: option,
        }
    }

    return {
        label: String(option?.label ?? option?.value ?? ''),
        value: String(option?.value ?? option?.label ?? ''),
    }
}

export function emptyFieldOption() {
    return {
        label: '',
        value: '',
    }
}

export function emptyFieldDefinition(type = 'text') {
    const normalizedType = normalizeFieldType(type)

    return normalizeFieldDefinition({
        label: '',
        key: '',
        type: normalizedType,
        default_value: defaultConfigValueForFieldType(normalizedType),
        is_required: false,
        sort_order: 0,
        settings: {},
    })
}

export function normalizeFieldDefinition(field = {}) {
    const type = normalizeFieldType(field?.type)
    const settings = normalizeFieldSettings(type, field?.settings || {})

    return {
        id: field?.id ?? null,
        label: String(field?.label ?? ''),
        key: String(field?.key ?? ''),
        type,
        default_value: normalizeDefaultConfigValue(type, field?.default_value),
        is_required: Boolean(field?.is_required),
        sort_order: Number(field?.sort_order ?? 0),
        settings,
    }
}

export function normalizeFieldSettings(type, settings = {}) {
    const normalizedType = normalizeFieldType(type)
    const next = {
        placeholder: String(settings?.placeholder ?? ''),
        help_text: String(settings?.help_text ?? ''),
    }

    if (settings?.rows !== undefined && settings?.rows !== null && settings?.rows !== '') {
        next.rows = Math.max(2, Number(settings.rows) || 4)
    }

    if (supportsOptions(normalizedType)) {
        next.options = Array.isArray(settings?.options)
            ? settings.options.map((option) => normalizeFieldOption(option)).filter((option) => option.label || option.value)
            : []
    }

    if (supportsNestedFields(normalizedType)) {
        next.fields = Array.isArray(settings?.fields)
            ? settings.fields.map((field) => normalizeFieldDefinition(field))
            : []
    }

    return {
        ...settings,
        ...next,
    }
}

export function normalizeDefaultConfigValue(type, value) {
    const normalizedType = normalizeFieldType(type)

    if (value === undefined) {
        return defaultConfigValueForFieldType(normalizedType)
    }

    if (normalizedType === 'checkbox' || normalizedType === 'switch' || normalizedType === 'toggle') {
        return Boolean(value)
    }

    if (normalizedType === 'gallery' || normalizedType === 'repeater') {
        return Array.isArray(value) ? cloneValue(value) : []
    }

    if (normalizedType === 'group') {
        return value && typeof value === 'object' && !Array.isArray(value) ? cloneValue(value) : {}
    }

    return value ?? ''
}

export function defaultConfigValueForFieldType(type) {
    const normalizedType = normalizeFieldType(type)

    if (normalizedType === 'checkbox' || normalizedType === 'switch' || normalizedType === 'toggle') {
        return false
    }

    if (normalizedType === 'group') {
        return {}
    }

    if (normalizedType === 'gallery' || normalizedType === 'repeater') {
        return []
    }

    return ''
}

export function supportsOptions(type) {
    return ['select', 'radio'].includes(normalizeFieldType(type))
}

export function supportsNestedFields(type) {
    return ['group', 'repeater'].includes(normalizeFieldType(type))
}

export function supportsPlaceholder(type) {
    return ['text', 'textarea', 'editor', 'url', 'email', 'number'].includes(normalizeFieldType(type))
}

export function supportsRows(type) {
    return ['textarea', 'editor'].includes(normalizeFieldType(type))
}

export function supportsDefaultValue(type) {
    return !['group', 'repeater', 'gallery'].includes(normalizeFieldType(type))
}

export function fieldOptions(field) {
    const normalized = normalizeFieldDefinition(field)

    return Array.isArray(normalized.settings?.options)
        ? normalized.settings.options
        : []
}

export function nestedFieldDefinitions(field) {
    const normalized = normalizeFieldDefinition(field)

    return Array.isArray(normalized.settings?.fields)
        ? normalized.settings.fields
        : []
}

export function defaultValueForField(field) {
    const normalized = normalizeFieldDefinition(field)
    const type = normalized.type

    if (supportsDefaultValue(type) && normalized.default_value !== null && normalized.default_value !== undefined && normalized.default_value !== '') {
        return cloneValue(normalized.default_value)
    }

    if (type === 'group') {
        const value = {}

        nestedFieldDefinitions(normalized).forEach((nested) => {
            value[nested.key] = defaultValueForField(nested)
        })

        return value
    }

    if (type === 'repeater' || type === 'gallery') {
        return []
    }

    if (type === 'checkbox' || type === 'switch' || type === 'toggle') {
        return false
    }

    return normalized.default_value ?? ''
}

export function getErrorMessages(errors = {}, path = '') {
    const direct = errors?.[path]

    if (Array.isArray(direct)) {
        return direct
    }

    if (typeof direct === 'string') {
        return [direct]
    }

    return []
}

export function getFirstError(errors = {}, path = '') {
    return getErrorMessages(errors, path)[0] || ''
}

export function ensureFieldValue(field, value) {
    if (value !== undefined) {
        return cloneValue(value)
    }

    return defaultValueForField(field)
}

export function moveArrayItem(items, fromIndex, toIndex) {
    const next = Array.isArray(items) ? items.map((item) => cloneValue(item)) : []

    if (fromIndex < 0 || toIndex < 0 || fromIndex >= next.length || toIndex >= next.length) {
        return next
    }

    const [item] = next.splice(fromIndex, 1)
    next.splice(toIndex, 0, item)

    return next
}

export function isBooleanFieldType(type) {
    return ['checkbox', 'switch', 'toggle'].includes(normalizeFieldType(type))
}

export function isMediaFieldType(type) {
    return ['image', 'file', 'gallery'].includes(normalizeFieldType(type))
}