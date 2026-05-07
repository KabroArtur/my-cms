<script setup>
import { computed } from 'vue'

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
    accentIds: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['select'])

const selectedMap = computed(() => new Set(props.selectedIds.map((id) => String(id))))
const accentMap = computed(() => new Set(props.accentIds.map((id) => String(id))))
</script>

<template>
    <div class="media-grid">
        <button
            v-for="file in files"
            :key="file.id"
            type="button"
            class="media-grid__item"
            :class="{ 'is-selected': selectedMap.has(String(file.id)), 'is-recent': accentMap.has(String(file.id)) }"
            @click="emit('select', file)"
        >
            <div class="media-grid__preview">
                <img :src="file.preview_url || file.url" :alt="file.alt_text || file.original_name">
            </div>

            <div class="media-grid__meta">
                <strong>{{ file.original_name }}</strong>
                <span>{{ file.size_human }}<template v-if="file.width && file.height"> | {{ file.width }} x {{ file.height }}</template></span>
            </div>
        </button>
    </div>
</template>

<style scoped>
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 0.9rem;
}

.media-grid__item {
    display: grid;
    gap: 0.65rem;
    padding: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.88);
    text-align: left;
    transition: border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}

.media-grid__item:hover,
.media-grid__item.is-selected {
    border-color: rgba(14, 116, 144, 0.55);
    transform: translateY(-1px);
    box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
}

.media-grid__item.is-recent {
    border-color: rgba(34, 197, 94, 0.7);
    box-shadow: 0 18px 42px rgba(34, 197, 94, 0.14);
}

.media-grid__preview {
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(226, 232, 240, 0.88), rgba(248, 250, 252, 0.98));
}

.media-grid__preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-grid__meta {
    display: grid;
    gap: 0.2rem;
}

.media-grid__meta strong,
.media-grid__meta span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.media-grid__meta span {
    color: rgba(71, 85, 105, 0.92);
    font-size: 0.86rem;
}
</style>