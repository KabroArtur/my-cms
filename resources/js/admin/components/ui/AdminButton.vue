<template>
    <button :class="buttonClass" :type="type" :disabled="disabled">
        <slot />
    </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'secondary',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const buttonClass = computed(() => ({
    'button-base': true,
    'button-primary': props.variant === 'primary',
    'button-secondary': props.variant === 'secondary',
    'button-danger': props.variant === 'danger',
}))
</script>