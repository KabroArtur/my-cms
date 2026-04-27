<template>
    <article class="admin-stat-card">
        <p class="admin-stat-card__label">{{ label }}</p>
        <strong class="admin-stat-card__value">{{ value }}</strong>
        <p v-if="description" class="muted">{{ description }}</p>
    </article>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
})
</script>