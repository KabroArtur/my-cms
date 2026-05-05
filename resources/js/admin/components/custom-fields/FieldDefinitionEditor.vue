<script setup>
import AdminButton from '../ui/AdminButton.vue'
import {
    emptyFieldDefinition,
    emptyFieldOption,
    FIELD_TYPE_OPTIONS,
    getFirstError,
    moveArrayItem,
    normalizeFieldDefinition,
    sanitizeFieldKey,
    supportsDefaultValue,
    supportsNestedFields,
    supportsOptions,
    supportsPlaceholder,
    supportsRows,
} from './customFields'

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    pathPrefix: {
        type: String,
        default: 'fields',
    },
    title: {
        type: String,
        default: 'Поля',
    },
    emptyText: {
        type: String,
        default: 'Поля пока не добавлены.',
    },
})

const emit = defineEmits(['update:modelValue'])

function fields() {
    return Array.isArray(props.modelValue) ? props.modelValue.map((field) => normalizeFieldDefinition(field)) : []
}

function commit(nextFields) {
    emit('update:modelValue', nextFields.map((field, index) => normalizeFieldDefinition({ ...field, sort_order: index })))
}

function addField(type = 'text') {
    commit([...fields(), emptyFieldDefinition(type)])
}

function updateField(index, changes) {
    const next = fields()
    const current = next[index] || emptyFieldDefinition()
    next[index] = normalizeFieldDefinition({
        ...current,
        ...changes,
        settings: changes?.settings ?? current.settings,
    })
    commit(next)
}

function removeField(index) {
    const next = fields()
    next.splice(index, 1)
    commit(next)
}

function moveField(index, offset) {
    commit(moveArrayItem(fields(), index, index + offset))
}

function updateSetting(index, key, value) {
    const current = fields()[index] || emptyFieldDefinition()

    updateField(index, {
        settings: {
            ...current.settings,
            [key]: value,
        },
    })
}

function updateOption(index, optionIndex, key, value) {
    const current = fields()[index] || emptyFieldDefinition()
    const options = Array.isArray(current.settings?.options) ? current.settings.options.map((option) => ({ ...option })) : []
    options[optionIndex] = {
        ...(options[optionIndex] || emptyFieldOption()),
        [key]: value,
    }
    updateSetting(index, 'options', options)
}

function addOption(index) {
    const current = fields()[index] || emptyFieldDefinition()
    const options = Array.isArray(current.settings?.options) ? current.settings.options.map((option) => ({ ...option })) : []
    options.push(emptyFieldOption())
    updateSetting(index, 'options', options)
}

function removeOption(index, optionIndex) {
    const current = fields()[index] || emptyFieldDefinition()
    const options = Array.isArray(current.settings?.options) ? current.settings.options.map((option) => ({ ...option })) : []
    options.splice(optionIndex, 1)
    updateSetting(index, 'options', options)
}

function moveOption(index, optionIndex, offset) {
    const current = fields()[index] || emptyFieldDefinition()
    const options = Array.isArray(current.settings?.options) ? current.settings.options.map((option) => ({ ...option })) : []
    updateSetting(index, 'options', moveArrayItem(options, optionIndex, optionIndex + offset))
}

function updateNestedFields(index, nestedFields) {
    updateSetting(index, 'fields', nestedFields)
}

function fieldPath(index, suffix = '') {
    return suffix ? `${props.pathPrefix}.${index}.${suffix}` : `${props.pathPrefix}.${index}`
}

function updateDefaultValue(index, value) {
    updateField(index, { default_value: value })
}

function updateLabel(index, value) {
    const current = fields()[index] || emptyFieldDefinition()
    const currentAutoKey = sanitizeFieldKey(current.label)
    const nextAutoKey = sanitizeFieldKey(value)
    const shouldSyncKey = current.key === '' || current.key === currentAutoKey

    updateField(index, {
        label: value,
        ...(shouldSyncKey ? { key: nextAutoKey } : {}),
    })
}

function updateKey(index, value) {
    updateField(index, { key: sanitizeFieldKey(value) })
}
</script>

<template>
    <section class="field-definition-editor">
        <div class="admin-actions-row field-definition-editor__header">
            <h3>{{ title }}</h3>
            <AdminButton type="button" @click="addField()">Добавить поле</AdminButton>
        </div>

        <p v-if="fields().length === 0" class="muted">{{ emptyText }}</p>

        <article v-for="(field, index) in fields()" :key="field.id ?? `${pathPrefix}-${index}`" class="field-definition-editor__card">
            <div class="admin-actions-row field-definition-editor__card-header">
                <strong>{{ field.label || `Поле ${index + 1}` }}</strong>
                <div class="admin-actions-row">
                    <AdminButton type="button" :disabled="index === 0" @click="moveField(index, -1)">Выше</AdminButton>
                    <AdminButton type="button" :disabled="index === fields().length - 1" @click="moveField(index, 1)">Ниже</AdminButton>
                    <AdminButton type="button" @click="removeField(index)">Удалить</AdminButton>
                </div>
            </div>

            <div class="page-meta-grid">
                <label class="admin-form-label">
                    <span>Label</span>
                    <input :value="field.label" class="admin-input" type="text" placeholder="Hero title" @input="updateLabel(index, $event.target.value)">
                    <small v-if="getFirstError(errors, fieldPath(index, 'label'))" class="error-text">{{ getFirstError(errors, fieldPath(index, 'label')) }}</small>
                </label>

                <label class="admin-form-label">
                    <span>Key</span>
                    <input :value="field.key" class="admin-input" type="text" placeholder="hero_title" @input="updateKey(index, $event.target.value)">
                    <small v-if="getFirstError(errors, fieldPath(index, 'key'))" class="error-text">{{ getFirstError(errors, fieldPath(index, 'key')) }}</small>
                </label>

                <label class="admin-form-label">
                    <span>Type</span>
                    <select :value="field.type" class="admin-select" @change="updateField(index, { type: $event.target.value })">
                        <option v-for="option in FIELD_TYPE_OPTIONS" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <small v-if="getFirstError(errors, fieldPath(index, 'type'))" class="error-text">{{ getFirstError(errors, fieldPath(index, 'type')) }}</small>
                </label>

                <label class="admin-form-label">
                    <span>Обязательное</span>
                    <input :checked="field.is_required" type="checkbox" @change="updateField(index, { is_required: $event.target.checked })">
                </label>
            </div>

            <div class="page-meta-grid">
                <label v-if="supportsPlaceholder(field.type)" class="admin-form-label">
                    <span>Placeholder</span>
                    <input :value="field.settings?.placeholder || ''" class="admin-input" type="text" placeholder="Подсказка внутри поля" @input="updateSetting(index, 'placeholder', $event.target.value)">
                </label>

                <label v-if="supportsRows(field.type)" class="admin-form-label">
                    <span>Rows</span>
                    <input :value="field.settings?.rows || 4" class="admin-input" type="number" min="2" @input="updateSetting(index, 'rows', Number($event.target.value || 4))">
                </label>

                <label class="admin-form-label">
                    <span>Help text</span>
                    <input :value="field.settings?.help_text || ''" class="admin-input" type="text" placeholder="Подсказка под полем" @input="updateSetting(index, 'help_text', $event.target.value)">
                </label>
            </div>

            <label v-if="supportsDefaultValue(field.type)" class="admin-form-label">
                <span>Default value</span>

                <input
                    v-if="['text', 'url', 'email', 'date', 'number'].includes(field.type)"
                    :value="field.default_value"
                    class="admin-input"
                    :type="field.type === 'number' ? 'number' : field.type"
                    @input="updateDefaultValue(index, $event.target.value)"
                >

                <textarea
                    v-else-if="['textarea', 'editor'].includes(field.type)"
                    :value="field.default_value"
                    class="admin-textarea"
                    rows="3"
                    @input="updateDefaultValue(index, $event.target.value)"
                ></textarea>

                <label v-else-if="['checkbox', 'switch', 'toggle'].includes(field.type)" class="field-definition-editor__checkbox-line">
                    <input :checked="Boolean(field.default_value)" type="checkbox" @change="updateDefaultValue(index, $event.target.checked)">
                    <span>Включено по умолчанию</span>
                </label>

                <select
                    v-else-if="['select', 'radio'].includes(field.type)"
                    :value="field.default_value || ''"
                    class="admin-select"
                    @change="updateDefaultValue(index, $event.target.value)"
                >
                    <option value="">Без значения</option>
                    <option v-for="option in (field.settings?.options || [])" :key="option.value" :value="option.value">
                        {{ option.label || option.value }}
                    </option>
                </select>

                <div v-else-if="field.type === 'color'" class="field-definition-editor__color-row">
                    <input :value="field.default_value || '#000000'" type="color" @input="updateDefaultValue(index, $event.target.value)">
                    <input :value="field.default_value" class="admin-input" type="text" placeholder="#ffffff" @input="updateDefaultValue(index, $event.target.value)">
                </div>

                <input v-else :value="field.default_value" class="admin-input" type="text" @input="updateDefaultValue(index, $event.target.value)">

                <small v-if="getFirstError(errors, fieldPath(index, 'default_value'))" class="error-text">{{ getFirstError(errors, fieldPath(index, 'default_value')) }}</small>
            </label>

            <section v-if="supportsOptions(field.type)" class="field-definition-editor__section">
                <div class="admin-actions-row">
                    <h4>Варианты</h4>
                    <AdminButton type="button" @click="addOption(index)">Добавить вариант</AdminButton>
                </div>

                <p v-if="(field.settings?.options || []).length === 0" class="muted">Вариантов пока нет.</p>

                <div v-for="(option, optionIndex) in (field.settings?.options || [])" :key="`${field.key}-option-${optionIndex}`" class="field-definition-editor__option-row">
                    <label class="admin-form-label">
                        <span>Label</span>
                        <input :value="option.label" class="admin-input" type="text" @input="updateOption(index, optionIndex, 'label', $event.target.value)">
                        <small v-if="getFirstError(errors, `${fieldPath(index, 'settings.options')}.${optionIndex}.label`)" class="error-text">{{ getFirstError(errors, `${fieldPath(index, 'settings.options')}.${optionIndex}.label`) }}</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Value</span>
                        <input :value="option.value" class="admin-input" type="text" @input="updateOption(index, optionIndex, 'value', $event.target.value)">
                        <small v-if="getFirstError(errors, `${fieldPath(index, 'settings.options')}.${optionIndex}.value`)" class="error-text">{{ getFirstError(errors, `${fieldPath(index, 'settings.options')}.${optionIndex}.value`) }}</small>
                    </label>

                    <div class="field-definition-editor__option-actions">
                        <AdminButton type="button" :disabled="optionIndex === 0" @click="moveOption(index, optionIndex, -1)">Выше</AdminButton>
                        <AdminButton type="button" :disabled="optionIndex === (field.settings.options.length - 1)" @click="moveOption(index, optionIndex, 1)">Ниже</AdminButton>
                        <AdminButton type="button" @click="removeOption(index, optionIndex)">Удалить</AdminButton>
                    </div>
                </div>

                <small v-if="getFirstError(errors, fieldPath(index, 'settings.options'))" class="error-text">{{ getFirstError(errors, fieldPath(index, 'settings.options')) }}</small>
            </section>

            <FieldDefinitionEditor
                v-if="supportsNestedFields(field.type)"
                :model-value="field.settings?.fields || []"
                :errors="errors"
                :path-prefix="fieldPath(index, 'settings.fields')"
                :title="field.type === 'repeater' ? 'Вложенные поля repeatable-элемента' : 'Вложенные поля группы'"
                empty-text="Вложенные поля пока не добавлены."
                @update:model-value="updateNestedFields(index, $event)"
            />
        </article>
    </section>
</template>

<style scoped>
.field-definition-editor {
    display: grid;
    gap: 1rem;
}

.field-definition-editor__header,
.field-definition-editor__card-header {
    justify-content: space-between;
}

.field-definition-editor__card {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(248, 250, 252, 0.86);
}

.field-definition-editor__section {
    display: grid;
    gap: 0.85rem;
}

.field-definition-editor__option-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: end;
}

.field-definition-editor__option-actions {
    display: inline-flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.field-definition-editor__checkbox-line {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.field-definition-editor__color-row {
    display: grid;
    grid-template-columns: 68px minmax(0, 1fr);
    gap: 0.75rem;
    align-items: center;
}

@media (max-width: 900px) {
    .field-definition-editor__option-row {
        grid-template-columns: 1fr;
    }
}
</style>