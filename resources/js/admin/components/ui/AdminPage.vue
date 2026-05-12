<template>
    <section class="panel-stack">
        <div class="panel-header">
            <div>
                <p class="eyebrow">{{ eyebrow }}</p>
                <h1>{{ title }}</h1>
                <p v-if="description" class="muted">{{ description }}</p>
            </div>
            <slot name="actions" />
        </div>

        <slot />
    </section>
</template>

<script setup>
defineProps({
    eyebrow: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
});
</script>
