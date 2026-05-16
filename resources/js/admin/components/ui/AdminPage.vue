<template>
    <section class="panel-stack">
        <div class="panel-header">
            <div>
                <h1>{{ title }}</h1>
                <p v-if="description" class="muted description">
                    {{ description }}
                </p>
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
