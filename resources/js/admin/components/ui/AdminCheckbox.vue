<script setup>
const props = defineProps({
    modelValue: [Boolean, Array],
    disabled: Boolean,
    value: {
        type: [String, Number, Boolean],
        default: null,
    },
});

const emit = defineEmits(["update:modelValue"]);

function toggle() {
    if (props.disabled) return;

    if (Array.isArray(props.modelValue)) {
        const values = [...props.modelValue];
        const index = values.indexOf(props.value);

        if (index === -1) {
            values.push(props.value);
        } else {
            values.splice(index, 1);
        }

        emit("update:modelValue", values);

        return;
    }

    emit("update:modelValue", !props.modelValue);
}
</script>

<template>
    <label class="admin-checkbox" :class="{ disabled }">
        <input
            type="checkbox"
            :checked="
                Array.isArray(modelValue)
                    ? modelValue.includes(value)
                    : modelValue
            "
            @change="toggle"
        />

        <span>
            <slot />
        </span>
    </label>
</template>
