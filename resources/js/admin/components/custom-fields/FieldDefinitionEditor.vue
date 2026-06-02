<script setup>
import AdminButton from "../ui/AdminButton.vue";
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
} from "./customFields";
import Icon from "../../components/ui/Icon.vue";
import AdminSelect from "../ui/AdminSelect.vue";
import AdminCheckbox from "../ui/AdminCheckbox.vue";

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
        default: "fields",
    },
    title: {
        type: String,
        default: "Поля",
    },
    emptyText: {
        type: String,
        default: "Поля пока не добавлены.",
    },
});

const emit = defineEmits(["update:modelValue"]);

function fields() {
    return Array.isArray(props.modelValue)
        ? props.modelValue.map((field) => normalizeFieldDefinition(field))
        : [];
}

function commit(nextFields) {
    emit(
        "update:modelValue",
        nextFields.map((field, index) =>
            normalizeFieldDefinition({ ...field, sort_order: index }),
        ),
    );
}

function addField(type = "text") {
    commit([...fields(), emptyFieldDefinition(type)]);
}

function updateField(index, changes) {
    const next = fields();
    const current = next[index] || emptyFieldDefinition();
    next[index] = normalizeFieldDefinition({
        ...current,
        ...changes,
        settings: changes?.settings ?? current.settings,
    });
    commit(next);
}

function removeField(index) {
    const next = fields();
    next.splice(index, 1);
    commit(next);
}

function moveField(index, offset) {
    commit(moveArrayItem(fields(), index, index + offset));
}

function updateSetting(index, key, value) {
    const current = fields()[index] || emptyFieldDefinition();

    updateField(index, {
        settings: {
            ...current.settings,
            [key]: value,
        },
    });
}

function updateOption(index, optionIndex, key, value) {
    const current = fields()[index] || emptyFieldDefinition();
    const options = Array.isArray(current.settings?.options)
        ? current.settings.options.map((option) => ({ ...option }))
        : [];
    options[optionIndex] = {
        ...(options[optionIndex] || emptyFieldOption()),
        [key]: value,
    };
    updateSetting(index, "options", options);
}

function addOption(index) {
    const current = fields()[index] || emptyFieldDefinition();
    const options = Array.isArray(current.settings?.options)
        ? current.settings.options.map((option) => ({ ...option }))
        : [];
    options.push(emptyFieldOption());
    updateSetting(index, "options", options);
}

function removeOption(index, optionIndex) {
    const current = fields()[index] || emptyFieldDefinition();
    const options = Array.isArray(current.settings?.options)
        ? current.settings.options.map((option) => ({ ...option }))
        : [];
    options.splice(optionIndex, 1);
    updateSetting(index, "options", options);
}

function moveOption(index, optionIndex, offset) {
    const current = fields()[index] || emptyFieldDefinition();
    const options = Array.isArray(current.settings?.options)
        ? current.settings.options.map((option) => ({ ...option }))
        : [];
    updateSetting(
        index,
        "options",
        moveArrayItem(options, optionIndex, optionIndex + offset),
    );
}

function updateNestedFields(index, nestedFields) {
    updateSetting(index, "fields", nestedFields);
}

function fieldPath(index, suffix = "") {
    return suffix
        ? `${props.pathPrefix}.${index}.${suffix}`
        : `${props.pathPrefix}.${index}`;
}

function updateDefaultValue(index, value) {
    updateField(index, { default_value: value });
}

function updateLabel(index, value) {
    const current = fields()[index] || emptyFieldDefinition();
    const currentAutoKey = sanitizeFieldKey(current.label);
    const nextAutoKey = sanitizeFieldKey(value);
    const shouldSyncKey = current.key === "" || current.key === currentAutoKey;

    updateField(index, {
        label: value,
        ...(shouldSyncKey ? { key: nextAutoKey } : {}),
    });
}

function updateKey(index, value) {
    updateField(index, { key: sanitizeFieldKey(value) });
}

function showDefaultValueLabel(type) {
    return !["textarea", "editor", "checkbox", "switch", "toggle"].includes(
        type,
    );
}
</script>

<template>
    <section class="field-definition-editor">
        <div class="admin-stack__head field-definition-editor__header">
            <h3>{{ title }}</h3>
            <AdminButton type="button" @click="addField()">
                <Icon name="new" width="18" height="18" />Добавить
                поле</AdminButton
            >
        </div>

        <p v-if="fields().length === 0" class="muted">{{ emptyText }}</p>

        <article
            v-for="(field, index) in fields()"
            :key="field.id ?? `${pathPrefix}-${index}`"
            class="field-definition-editor__card"
        >
            <div class="admin-actions-row field-definition-editor__card-header">
                <strong>{{ field.label || `Поле ${index + 1}` }}</strong>
                <div class="admin-actions-row">
                    <AdminButton
                        type="button"
                        :disabled="index === 0"
                        @click="moveField(index, -1)"
                        class="button-editor"
                        title="Переместить поле вверх"
                        ><Icon name="arrow-top" width="16" height="16"
                    /></AdminButton>
                    <AdminButton
                        type="button"
                        :disabled="index === fields().length - 1"
                        @click="moveField(index, 1)"
                        class="button-editor"
                        title="Переместить поле вниз"
                        ><Icon
                            name="arrow-top"
                            width="16"
                            height="16"
                            class="icon-rotated"
                    /></AdminButton>
                    <AdminButton
                        type="button"
                        @click="removeField(index)"
                        class="button-danger"
                        title="Удалить поле"
                        ><Icon name="trash" width="18" height="18"
                    /></AdminButton>
                </div>
            </div>

            <div class="page-meta-grid">
                <label class="admin-form-label label" data-label="Label:">
                    <input
                        :value="field.label"
                        class="admin-input"
                        type="text"
                        placeholder="Hero title"
                        @input="updateLabel(index, $event.target.value)"
                    />
                    <small
                        v-if="getFirstError(errors, fieldPath(index, 'label'))"
                        class="error-text"
                        >{{
                            getFirstError(errors, fieldPath(index, "label"))
                        }}</small
                    >
                </label>

                <label class="admin-form-label key-settings" data-label="Key:">
                    <input
                        :value="field.key"
                        class="admin-input"
                        type="text"
                        placeholder="hero_title"
                        @input="updateKey(index, $event.target.value)"
                    />
                    <small
                        v-if="getFirstError(errors, fieldPath(index, 'key'))"
                        class="error-text"
                        >{{
                            getFirstError(errors, fieldPath(index, "key"))
                        }}</small
                    >
                </label>

                <div>
                    <AdminSelect
                        class="type"
                        data-label="Type:"
                        :model-value="field.type"
                        :options="FIELD_TYPE_OPTIONS"
                        @update:modelValue="
                            (value) => updateField(index, { type: value })
                        "
                    />

                    <small
                        v-if="getFirstError(errors, fieldPath(index, 'type'))"
                        class="error-text"
                    >
                        {{ getFirstError(errors, fieldPath(index, "type")) }}
                    </small>
                </div>

                <label class="admin-form-label">
                    <AdminCheckbox
                        :model-value="field.is_required"
                        @update:modelValue="
                            (value) =>
                                updateField(index, { is_required: value })
                        "
                    >
                        Обязательное
                    </AdminCheckbox>
                </label>
            </div>

            <div class="page-meta-grid">
                <label
                    v-if="supportsPlaceholder(field.type)"
                    class="admin-form-label placeholder"
                    data-label="Placeholder:"
                >
                    <input
                        :value="field.settings?.placeholder || ''"
                        class="admin-input"
                        type="text"
                        placeholder="Подсказка внутри поля"
                        @input="
                            updateSetting(
                                index,
                                'placeholder',
                                $event.target.value,
                            )
                        "
                    />
                </label>

                <label
                    v-if="supportsRows(field.type)"
                    class="admin-form-label rows"
                    data-label="Rows:"
                >
                    <input
                        :value="field.settings?.rows || 4"
                        class="admin-input"
                        type="number"
                        min="2"
                        @input="
                            updateSetting(
                                index,
                                'rows',
                                Number($event.target.value || 4),
                            )
                        "
                    />
                </label>

                <label class="admin-form-label help" data-label="Help text:">
                    <input
                        :value="field.settings?.help_text || ''"
                        class="admin-input"
                        type="text"
                        placeholder="Подсказка под полем"
                        @input="
                            updateSetting(
                                index,
                                'help_text',
                                $event.target.value,
                            )
                        "
                    />
                </label>

                <label
                    v-if="supportsDefaultValue(field.type)"
                    class="admin-form-label default"
                    :class="{
                        'is-full': ['textarea', 'editor'].includes(field.type),
                    }"
                    :data-label="
                        showDefaultValueLabel(field.type)
                            ? 'Default value:'
                            : null
                    "
                >
                    <input
                        v-if="
                            ['text', 'url', 'email', 'number'].includes(
                                field.type,
                            )
                        "
                        :value="field.default_value"
                        class="admin-input"
                        :type="field.type === 'number' ? 'number' : field.type"
                        @input="updateDefaultValue(index, $event.target.value)"
                    />

                    <div
                        v-else-if="field.type === 'date'"
                        class="admin-input-wrapper"
                    >
                        <input
                            :value="field.default_value"
                            class="admin-input"
                            type="date"
                            @input="
                                updateDefaultValue(index, $event.target.value)
                            "
                        />

                        <Icon name="calendar" width="18" height="18" />
                    </div>

                    <textarea
                        v-else-if="['textarea', 'editor'].includes(field.type)"
                        :value="field.default_value"
                        class="admin-textarea"
                        rows="3"
                        placeholder="Текст по умолчанию (если оставить пустым — будет пусто)"
                        @input="updateDefaultValue(index, $event.target.value)"
                    ></textarea>

                    <AdminCheckbox
                        v-else-if="
                            ['checkbox', 'switch', 'toggle'].includes(
                                field.type,
                            )
                        "
                        :model-value="Boolean(field.default_value)"
                        @update:modelValue="
                            (value) => updateDefaultValue(index, value)
                        "
                    >
                        Включено по умолчанию
                    </AdminCheckbox>

                    <AdminSelect
                        v-else-if="['select', 'radio'].includes(field.type)"
                        :model-value="field.default_value || ''"
                        :options="[
                            { label: 'Без значения', value: '' },
                            ...(field.settings?.options || []).map((o) => ({
                                label: o.label || o.value,
                                value: o.value,
                            })),
                        ]"
                        @update:modelValue="
                            updateDefaultValue(index, $event, field.type)
                        "
                    />

                    <div
                        v-else-if="field.type === 'color'"
                        class="field-definition-editor__color"
                    >
                        <input
                            :value="field.default_value || ''"
                            class="admin-input"
                            type="text"
                            placeholder="#ffffff"
                            @input="
                                updateDefaultValue(index, $event.target.value)
                            "
                        />

                        <input
                            type="color"
                            class="field-definition-editor__color-picker"
                            :value="field.default_value || '#000000'"
                            @input="
                                updateDefaultValue(index, $event.target.value)
                            "
                        />
                    </div>

                    <input
                        v-else
                        :value="field.default_value"
                        class="admin-input"
                        type="text"
                        @input="updateDefaultValue(index, $event.target.value)"
                    />

                    <small
                        v-if="
                            getFirstError(
                                errors,
                                fieldPath(index, 'default_value'),
                            )
                        "
                        class="error-text"
                        >{{
                            getFirstError(
                                errors,
                                fieldPath(index, "default_value"),
                            )
                        }}</small
                    >
                </label>
            </div>

            <section
                v-if="supportsOptions(field.type)"
                class="field-definition-editor__section"
            >
                <div class="admin-stack__head">
                    <h3>Варианты</h3>
                    <AdminButton type="button" @click="addOption(index)"
                        ><Icon name="new" width="18" height="18" />Добавить
                        вариант</AdminButton
                    >
                </div>

                <p
                    v-if="(field.settings?.options || []).length === 0"
                    class="muted"
                >
                    Вариантов пока нет.
                </p>

                <div
                    v-for="(option, optionIndex) in field.settings?.options ||
                    []"
                    :key="`${field.key}-option-${optionIndex}`"
                    class="field-definition-editor__option-row"
                >
                    <label class="admin-form-label label">
                        <input
                            :value="option.label"
                            class="admin-input"
                            type="text"
                            @input="
                                updateOption(
                                    index,
                                    optionIndex,
                                    'label',
                                    $event.target.value,
                                )
                            "
                        />
                        <small
                            v-if="
                                getFirstError(
                                    errors,
                                    `${fieldPath(index, 'settings.options')}.${optionIndex}.label`,
                                )
                            "
                            class="error-text"
                            >{{
                                getFirstError(
                                    errors,
                                    `${fieldPath(index, "settings.options")}.${optionIndex}.label`,
                                )
                            }}</small
                        >
                    </label>

                    <label class="admin-form-label value">
                        <input
                            :value="option.value"
                            class="admin-input"
                            type="text"
                            @input="
                                updateOption(
                                    index,
                                    optionIndex,
                                    'value',
                                    $event.target.value,
                                )
                            "
                        />
                        <small
                            v-if="
                                getFirstError(
                                    errors,
                                    `${fieldPath(index, 'settings.options')}.${optionIndex}.value`,
                                )
                            "
                            class="error-text"
                            >{{
                                getFirstError(
                                    errors,
                                    `${fieldPath(index, "settings.options")}.${optionIndex}.value`,
                                )
                            }}</small
                        >
                    </label>

                    <div class="field-definition-editor__option-actions">
                        <AdminButton
                            type="button"
                            :disabled="optionIndex === 0"
                            @click="moveOption(index, optionIndex, -1)"
                            class="button-editor"
                            title="Переместить вариант вверх"
                            ><Icon name="arrow-top" width="16" height="16"
                        /></AdminButton>
                        <AdminButton
                            type="button"
                            :disabled="
                                optionIndex ===
                                field.settings.options.length - 1
                            "
                            @click="moveOption(index, optionIndex, 1)"
                            class="button-editor"
                            ><Icon
                                name="arrow-top"
                                width="16"
                                height="16"
                                class="icon-rotated"
                                title="Переместить вариант вниз"
                        /></AdminButton>
                        <AdminButton
                            type="button"
                            @click="removeOption(index, optionIndex)"
                            class="button-editor"
                            ><Icon name="trash" width="18" height="18"
                        /></AdminButton>
                    </div>
                </div>

                <small
                    v-if="
                        getFirstError(
                            errors,
                            fieldPath(index, 'settings.options'),
                        )
                    "
                    class="error-text"
                    >{{
                        getFirstError(
                            errors,
                            fieldPath(index, "settings.options"),
                        )
                    }}</small
                >
            </section>

            <FieldDefinitionEditor
                v-if="supportsNestedFields(field.type)"
                :model-value="field.settings?.fields || []"
                :errors="errors"
                :path-prefix="fieldPath(index, 'settings.fields')"
                :title="
                    field.type === 'repeater'
                        ? 'Вложенные поля repeatable-элемента'
                        : 'Вложенные поля группы'
                "
                empty-text="Вложенные поля пока не добавлены."
                @update:model-value="updateNestedFields(index, $event)"
            />
        </article>
    </section>
</template>
