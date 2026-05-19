<script setup>
const props = defineProps({
    modelValue: Boolean,
    disabled: Boolean,
});

const emit = defineEmits(["update:modelValue"]);

function toggle() {
    if (props.disabled) return;
    emit("update:modelValue", !props.modelValue);
}
</script>

<template>
    <label class="admin-checkbox" :class="{ disabled }">
        <input type="checkbox" :checked="modelValue" @change="toggle" />

        <span>
            <slot />
        </span>
    </label>
</template>
