<script setup>
import { computed } from "vue";
import MediaPickerField from "../media/MediaPickerField.vue";
import AdminButton from "../ui/AdminButton.vue";
import RichTextField from "./RichTextField.vue";
import {
    cloneValue,
    defaultValueForField,
    ensureFieldValue,
    fieldOptions,
    getErrorMessages,
    isBooleanFieldType,
    moveArrayItem,
    nestedFieldDefinitions,
    normalizeFieldDefinition,
} from "./customFields";

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },
    modelValue: {
        type: [Array, Object, Boolean, Number, String, null],
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    path: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["update:modelValue"]);

const normalizedField = computed(() => normalizeFieldDefinition(props.field));
const type = computed(() => normalizedField.value.type);
const options = computed(() => fieldOptions(normalizedField.value));
const nestedFields = computed(() =>
    nestedFieldDefinitions(normalizedField.value),
);
const errorMessages = computed(() =>
    getErrorMessages(props.errors, props.path),
);
const placeholder = computed(
    () => normalizedField.value.settings?.placeholder || "",
);
const helpText = computed(
    () => normalizedField.value.settings?.help_text || "",
);
const rows = computed(() =>
    Number(
        normalizedField.value.settings?.rows ||
            (type.value === "editor" ? 6 : 4),
    ),
);

function updateValue(value) {
    emit("update:modelValue", value);
}

function currentValue() {
    return ensureFieldValue(normalizedField.value, props.modelValue);
}

function updateText(event) {
    updateValue(event.target.value);
}

function updateNumber(event) {
    const next = event.target.value;
    updateValue(next === "" ? "" : Number(next));
}

function updateBoolean(event) {
    updateValue(Boolean(event.target.checked));
}

function updateColorText(event) {
    updateValue(event.target.value);
}

function currentGroupValue() {
    const current =
        props.modelValue &&
        typeof props.modelValue === "object" &&
        !Array.isArray(props.modelValue)
            ? cloneValue(props.modelValue)
            : {};

    nestedFields.value.forEach((nested) => {
        if (!(nested.key in current)) {
            current[nested.key] = defaultValueForField(nested);
        }
    });

    return current;
}

function updateGroupField(key, value) {
    const next = currentGroupValue();
    next[key] = value;
    updateValue(next);
}

function currentRepeaterValue() {
    return Array.isArray(props.modelValue) ? cloneValue(props.modelValue) : [];
}

function addRepeaterItem() {
    const next = currentRepeaterValue();
    const row = {};

    nestedFields.value.forEach((nested) => {
        row[nested.key] = defaultValueForField(nested);
    });

    next.push(row);
    updateValue(next);
}

function updateRepeaterItem(index, key, value) {
    const next = currentRepeaterValue();
    next[index] = {
        ...(next[index] || {}),
        [key]: value,
    };
    updateValue(next);
}

function removeRepeaterItem(index) {
    const next = currentRepeaterValue();
    next.splice(index, 1);
    updateValue(next);
}

function moveRepeaterItem(index, offset) {
    updateValue(moveArrayItem(currentRepeaterValue(), index, index + offset));
}

const mediaAccept = computed(() =>
    type.value === "image" || type.value === "gallery" ? "image/*" : "",
);
const mediaTitle = computed(() => {
    if (type.value === "gallery") {
        return `Выбрать элементы галереи: ${normalizedField.value.label || normalizedField.value.key}`;
    }

    if (type.value === "file") {
        return `Выбрать файл: ${normalizedField.value.label || normalizedField.value.key}`;
    }

    return `Выбрать изображение: ${normalizedField.value.label || normalizedField.value.key}`;
});
</script>

<template>
    <div class="custom-field-renderer">
        <div class="custom-field-renderer__label-row">
            <span>
                {{ normalizedField.label || normalizedField.key }}
                <small v-if="normalizedField.key" class="muted"
                    >({{ normalizedField.key }})</small
                >
            </span>
            <small
                v-if="normalizedField.is_required"
                class="custom-field-renderer__required"
                >Required</small
            >
        </div>

        <input
            v-if="['text', 'url', 'email', 'date'].includes(type)"
            :value="currentValue()"
            class="admin-input"
            :type="type"
            :placeholder="placeholder"
            @input="updateText"
        />

        <textarea
            v-else-if="type === 'textarea'"
            :value="currentValue()"
            class="admin-textarea"
            :rows="rows"
            :placeholder="placeholder"
            @input="updateText"
        ></textarea>

        <RichTextField
            v-else-if="type === 'editor'"
            :model-value="String(currentValue() || '')"
            :placeholder="placeholder || 'Введите контент...'"
            @update:model-value="updateValue"
        />

        <input
            v-else-if="type === 'number'"
            :value="currentValue()"
            class="admin-input"
            type="number"
            :placeholder="placeholder"
            @input="updateNumber"
        />

        <label
            v-else-if="isBooleanFieldType(type)"
            class="custom-field-renderer__boolean"
            :class="{ 'is-switch': ['switch', 'toggle'].includes(type) }"
        >
            <input
                :checked="Boolean(currentValue())"
                type="checkbox"
                @change="updateBoolean"
            />
            <span>{{
                ["switch", "toggle"].includes(type) ? "Вкл / Выкл" : "Да / Нет"
            }}</span>
        </label>

        <select
            v-else-if="type === 'select'"
            :value="currentValue()"
            class="admin-select"
            @change="updateValue($event.target.value)"
        >
            <option value="">Выберите значение</option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>

        <div
            v-else-if="type === 'radio'"
            class="custom-field-renderer__radio-list"
        >
            <label
                v-for="option in options"
                :key="option.value"
                class="custom-field-renderer__radio-item"
            >
                <input
                    :checked="currentValue() === option.value"
                    type="radio"
                    :name="`radio-${path || normalizedField.key}`"
                    :value="option.value"
                    @change="updateValue(option.value)"
                />
                <span>{{ option.label }}</span>
            </label>
        </div>

        <div
            v-else-if="type === 'color'"
            class="custom-field-renderer__color-row"
        >
            <input
                :value="currentValue() || '#000000'"
                type="color"
                @input="updateColorText"
            />
            <input
                :value="currentValue()"
                class="admin-input"
                type="text"
                placeholder="#ffffff"
                @input="updateColorText"
            />
        </div>

        <MediaPickerField
            v-else-if="['image', 'file'].includes(type)"
            :model-value="currentValue()"
            :title="mediaTitle"
            return-type="object"
            :allow-upload="true"
            :accept="mediaAccept"
            @update:model-value="updateValue"
        />

        <MediaPickerField
            v-else-if="type === 'gallery'"
            :model-value="Array.isArray(modelValue) ? modelValue : []"
            :title="mediaTitle"
            return-type="object"
            :allow-upload="true"
            accept="image/*"
            multiple
            @update:model-value="updateValue"
        />

        <div v-else-if="type === 'group'" class="custom-field-renderer__nested">
            <div v-if="nestedFields.length === 0" class="muted">
                В этой группе пока нет вложенных полей.
            </div>
            <CustomFieldRenderer
                v-for="nested in nestedFields"
                :key="`${normalizedField.key}-${nested.key}`"
                :field="nested"
                :model-value="
                    modelValue &&
                    typeof modelValue === 'object' &&
                    !Array.isArray(modelValue)
                        ? modelValue[nested.key]
                        : undefined
                "
                :errors="errors"
                :path="path ? `${path}.${nested.key}` : nested.key"
                @update:model-value="
                    (value) => updateGroupField(nested.key, value)
                "
            />
        </div>

        <div
            v-else-if="type === 'repeater'"
            class="custom-field-renderer__nested"
        >
            <div class="admin-actions-row">
                <small class="muted">Повторяемые элементы</small>
                <AdminButton type="button" @click="addRepeaterItem"
                    >Добавить элемент</AdminButton
                >
            </div>

            <div
                v-if="!Array.isArray(modelValue) || modelValue.length === 0"
                class="muted"
            >
                Элементы пока не добавлены.
            </div>

            <article
                v-for="(row, index) in Array.isArray(modelValue)
                    ? modelValue
                    : []"
                :key="`${normalizedField.key}-row-${index}`"
                class="custom-field-renderer__repeater-row"
            >
                <div
                    class="admin-actions-row custom-field-renderer__repeater-actions"
                >
                    <strong>Элемент {{ index + 1 }}</strong>
                    <div class="admin-actions-row">
                        <AdminButton
                            type="button"
                            :disabled="index === 0"
                            @click="moveRepeaterItem(index, -1)"
                            >Выше</AdminButton
                        >
                        <AdminButton
                            type="button"
                            :disabled="index === modelValue.length - 1"
                            @click="moveRepeaterItem(index, 1)"
                            >Ниже</AdminButton
                        >
                        <AdminButton
                            type="button"
                            @click="removeRepeaterItem(index)"
                            >Удалить</AdminButton
                        >
                    </div>
                </div>

                <CustomFieldRenderer
                    v-for="nested in nestedFields"
                    :key="`${normalizedField.key}-${nested.key}-${index}`"
                    :field="nested"
                    :model-value="row?.[nested.key]"
                    :errors="errors"
                    :path="
                        path
                            ? `${path}.${index}.${nested.key}`
                            : `${normalizedField.key}.${index}.${nested.key}`
                    "
                    @update:model-value="
                        (value) => updateRepeaterItem(index, nested.key, value)
                    "
                />
            </article>
        </div>

        <textarea
            v-else
            :value="currentValue()"
            class="admin-textarea"
            :rows="rows"
            :placeholder="placeholder"
            @input="updateText"
        ></textarea>

        <small v-if="helpText" class="muted">{{ helpText }}</small>
        <small
            v-for="message in errorMessages"
            :key="message"
            class="error-text"
            >{{ message }}</small
        >
    </div>
</template>
