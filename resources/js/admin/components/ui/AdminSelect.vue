<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import Icon from "../../components/ui/Icon.vue";

const props = defineProps({
    modelValue: [String, Number],
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: "Выберите значение",
    },
});

const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const root = ref(null);

const selectedOption = computed(() =>
    props.options.find((o) => String(o.value) === String(props.modelValue)),
);

function select(option) {
    emit("update:modelValue", option.value);
    open.value = false;
}

function toggle() {
    open.value = !open.value;
}

function clickOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => window.addEventListener("click", clickOutside));
onBeforeUnmount(() => window.removeEventListener("click", clickOutside));
</script>

<template>
    <div ref="root" class="admin-select-custom">
        <button
            type="button"
            class="admin-select-trigger"
            :class="{ 'is-open': open }"
            @click="toggle"
        >
            <span>
                {{ selectedOption?.label || placeholder }}
            </span>

            <span class="arrow" :class="{ open }">
                <Icon name="arrow-down" width="20" height="20" />
            </span>
        </button>

        <transition name="select">
            <div v-show="open" class="admin-select-dropdown">
                <div
                    v-for="option in options"
                    :key="option.value"
                    class="admin-select-option"
                    :class="{
                        active: String(option.value) === String(modelValue),
                    }"
                    @click="select(option)"
                >
                    {{ option.label }}
                </div>
            </div>
        </transition>
    </div>
</template>
