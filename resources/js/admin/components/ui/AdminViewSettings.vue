<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import AdminCheckbox from "./AdminCheckbox.vue";

const props = defineProps({
    modelValue: Object,
    labels: Object,
});

const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const root = ref(null);

function toggle() {
    open.value = !open.value;
}

function update(key, value) {
    const next = {
        ...props.modelValue,
        [key]: value,
    };

    if (key === "compact" && value) {
        Object.keys(next).forEach((k) => {
            if (k !== "compact") next[k] = false;
        });
    }

    emit("update:modelValue", next);
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
    <div ref="root" class="admin-view-settings">
        <button class="settings-trigger" @click="toggle">
            <slot name="trigger" />
        </button>

        <transition name="fade">
            <div v-show="open" class="settings-dropdown">
                <div class="settings-title">Настройки отображения</div>

                <label
                    v-for="(value, key) in modelValue"
                    :key="key"
                    class="settings-option"
                >
                    <AdminCheckbox
                        :model-value="value"
                        @update:model-value="update(key, $event)"
                    />
                    {{ labels[key] }}
                </label>
            </div>
        </transition>
    </div>
</template>
