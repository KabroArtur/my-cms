<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import Icon from "./Icon.vue";

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },

    options: {
        type: Array,
        default: () => [],
    },

    placeholder: {
        type: String,
        default: "Выберите значения",
    },

    emptyText: {
        type: String,
        default: "Ничего не найдено",
    },

    noOptionsText: {
        type: String,
        default: "Нет доступных вариантов",
    },
});

const emit = defineEmits(["update:modelValue"]);

const root = ref(null);
const open = ref(false);
const search = ref("");

const hasOptions = computed(() => props.options.length > 0);

const normalizedModelValue = computed(() => {
    if (!Array.isArray(props.modelValue)) return [];
    return props.modelValue.map(String);
});

const filteredOptions = computed(() => {
    if (!hasOptions.value) return [];

    const q = search.value.trim().toLowerCase();

    return props.options.filter((option) => {
        const selected = normalizedModelValue.value.includes(
            String(option.value),
        );

        if (selected) return false;

        if (!q) return true;

        return option.label.toLowerCase().includes(q);
    });
});

const selectedOptions = computed(() => {
    return props.options.filter((option) =>
        normalizedModelValue.value.includes(String(option.value)),
    );
});

const allSelected = computed(() => {
    return (
        hasOptions.value &&
        selectedOptions.value.length === props.options.length
    );
});

function toggle() {
    open.value = !open.value;
}

function selectOption(option) {
    const nextValues = [
        ...new Set([...normalizedModelValue.value, String(option.value)]),
    ];

    emit("update:modelValue", nextValues);
    search.value = "";
}

function removeOption(value) {
    emit(
        "update:modelValue",
        normalizedModelValue.value.filter((item) => item !== String(value)),
    );
}

function handleClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => {
    window.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
    window.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div ref="root" class="admin-multiselect">
        <div
            class="admin-multiselect__control"
            :class="{ 'is-open': open }"
            @click="toggle"
        >
            <div class="admin-multiselect__value">
                <div
                    v-if="selectedOptions.length"
                    class="admin-multiselect__tags"
                >
                    <div
                        v-for="option in selectedOptions"
                        :key="option.value"
                        class="admin-multiselect__tag"
                    >
                        <span>{{ option.label }}</span>

                        <button
                            type="button"
                            class="admin-multiselect__remove"
                            @click.stop="removeOption(option.value)"
                        >
                            <Icon name="close" width="10" height="10" />
                        </button>
                    </div>
                </div>

                <span v-else class="admin-multiselect__placeholder">
                    {{ placeholder }}
                </span>
            </div>

            <Icon
                name="arrow-down"
                width="20"
                height="20"
                class="admin-multiselect__arrow"
            />
        </div>

        <transition name="select">
            <div v-if="open" class="admin-multiselect__dropdown">
                <div v-if="!hasOptions" class="admin-multiselect__empty">
                    {{ noOptionsText }}
                </div>

                <template v-else>
                    <input
                        v-if="!allSelected"
                        v-model="search"
                        type="search"
                        class="admin-input admin-multiselect__search"
                        placeholder="Поиск..."
                        @click.stop
                    />

                    <div v-if="allSelected" class="admin-multiselect__empty">
                        Все доступные варианты выбраны
                    </div>

                    <div
                        v-else-if="filteredOptions.length === 0"
                        class="admin-multiselect__empty"
                    >
                        {{ emptyText }}
                    </div>

                    <button
                        v-for="option in filteredOptions"
                        :key="option.value"
                        type="button"
                        class="admin-multiselect__option"
                        @click.stop="selectOption(option)"
                    >
                        {{ option.label }}
                    </button>
                </template>
            </div>
        </transition>
    </div>
</template>
