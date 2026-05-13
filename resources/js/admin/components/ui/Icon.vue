<template>
    <svg
        :width="width"
        :height="height"
        class="icon"
        aria-hidden="true"
        v-bind="$attrs"
    >
        <use :href="`#${name}`" />
    </svg>
</template>

<script setup>
defineProps({
    name: { type: String, required: true },
    width: { type: [Number, String], required: true },
    height: { type: [Number, String], required: true },
});
</script>
