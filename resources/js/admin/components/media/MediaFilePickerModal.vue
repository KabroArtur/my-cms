<script setup>
import { computed } from 'vue'
import MediaLibraryModal from './MediaLibraryModal.vue'
import { createMediaSelection, toNumericId } from './mediaHelpers'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    modelValue: {
        type: [Number, String, null],
        default: '',
    },
    title: {
        type: String,
        default: 'Выбрать изображение',
    },
})

const emit = defineEmits(['update:modelValue', 'select', 'close'])

function closeModal() {
    emit('close')
}

function selectFile(file) {
    const selection = createMediaSelection(file)

    emit('update:modelValue', selection.value ?? '')
    emit('select', selection)
    closeModal()
}

const selectedIds = computed(() => {
    const id = toNumericId(props.modelValue)

    return id === null ? [] : [id]
})
</script>

<template>
    <MediaLibraryModal
        :open="open"
        :title="title"
        :selected-ids="selectedIds"
        @close="closeModal"
        @select="selectFile"
    />
</template>