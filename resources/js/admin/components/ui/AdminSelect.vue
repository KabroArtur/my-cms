<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import Icon from "../../components/ui/Icon.vue";

const props = defineProps({
    modelValue: [String, Number],
    options: { type: Array, required: true },
    placeholder: { type: String, default: "Выберите значение" },
    showIcons: { type: Boolean, default: true },
});

const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const root = ref(null);

const selectedOption = computed(() =>
    props.options.find((o) => String(o.value) === String(props.modelValue)),
);

function toggle() {
    open.value = !open.value;
}

function select(option) {
    emit("update:modelValue", option.value);
    open.value = false;
}

function onOutside(e) {
    if (!root.value) return;
    if (!root.value.contains(e.target)) {
        open.value = false;
    }
}

function onKeydown(e) {
    if (e.key === "Escape") {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener("pointerdown", onOutside, true);
    document.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", onOutside, true);
    document.removeEventListener("keydown", onKeydown);
});
</script>

<template>
    <div ref="root" class="admin-select-custom">
        <button
            type="button"
            class="admin-select-trigger"
            :class="{ 'is-open': open }"
            @mousedown.prevent="toggle"
        >
            <span class="selected">
                <Icon
                    v-if="showIcons && selectedOption?.icon"
                    :name="selectedOption.icon"
                    width="18"
                    height="18"
                />

                <span v-else>
                    {{ selectedOption?.label || placeholder }}
                </span>
            </span>

            <span class="arrow" :class="{ open }">
                <Icon name="arrow-down" width="20" height="20" />
            </span>
        </button>

        <transition name="select">
            <div v-show="open" class="admin-select-dropdown" @mousedown.stop>
                <div
                    v-for="option in options"
                    :key="option.value"
                    class="admin-select-option"
                    :class="{
                        active: String(option.value) === String(modelValue),
                    }"
                    @mousedown.prevent="select(option)"
                >
                    <Icon
                        v-if="showIcons && option.icon"
                        :name="option.icon"
                        width="18"
                        height="18"
                    />

                    <span v-else>
                        {{ option.label }}
                    </span>
                </div>
            </div>
        </transition>
    </div>
</template>
