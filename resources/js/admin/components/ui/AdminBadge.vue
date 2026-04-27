<template>
    <span :class="['badge', soft ? 'badge-soft' : '']">
        <slot />
    </span>
</template>

<script setup>
defineProps({
    soft: {
        type: Boolean,
        default: false,
    },
})
</script>