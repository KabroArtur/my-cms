<script setup>
import { computed } from "vue";

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
});

const emit = defineEmits(["select"]);

const selectedMap = computed(
    () => new Set(props.selectedIds.map((id) => String(id))),
);
const accentMap = computed(
    () => new Set(props.accentIds.map((id) => String(id))),
);
</script>

<template>
    <div class="media-grid">
        <button
            v-for="file in files"
            :key="file.id"
            type="button"
            class="media-grid__item"
            :class="{
                'is-selected': selectedMap.has(String(file.id)),
                'is-recent': accentMap.has(String(file.id)),
            }"
            @click="emit('select', file)"
        >
            <div class="media-grid__preview">
                <img
                    :src="file.preview_url || file.url"
                    :alt="file.alt_text || file.original_name"
                />
            </div>

            <div class="media-grid__meta">
                <strong>{{ file.original_name }}</strong>
                <span
                    >{{ file.size_human
                    }}<template v-if="file.width && file.height">
                        | {{ file.width }} x {{ file.height }}</template
                    ></span
                >
            </div>
        </button>
    </div>
</template>
