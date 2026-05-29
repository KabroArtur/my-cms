<script setup>
import { reactive, watch } from "vue";
import AdminButton from "../ui/AdminButton.vue";
import MediaFactsPanel from "./MediaFactsPanel.vue";
import { stripExtension } from "./mediaHelpers";
import Icon from "../ui/Icon.vue";
import AdminSelect from "../../components/ui/AdminSelect.vue";

const props = defineProps({
    file: {
        type: Object,
        default: null,
    },
    moveFolderOptions: {
        type: Array,
        default: () => [],
    },
    saving: {
        type: Boolean,
        default: false,
    },
    selecting: {
        type: Boolean,
        default: false,
    },
    editable: {
        type: Boolean,
        default: false,
    },
    showFacts: {
        type: Boolean,
        default: true,
    },
    croppedDimensions: {
        type: Object,
        default: null,
    },
    outputDimensions: {
        type: Object,
        default: null,
    },
    showTransformFacts: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    "close",
    "save",
    "select",
    "copy-url",
    "delete",
    "move-file",
    "edit",
]);

const form = reactive({
    original_name: "",
    alt_text: "",
    folder_id: "",
});

watch(
    () => props.file,
    (file) => {
        form.original_name = stripExtension(
            file?.original_name || file?.filename || "",
        );
        form.alt_text = file?.alt_text ?? "";
        form.folder_id = file?.folder_id ?? "";
    },
    { immediate: true },
);

function submit() {
    if (!props.file) {
        return;
    }

    emit("save", {
        original_name: form.original_name,
        alt_text: form.alt_text,
    });
}

function handleMoveFile(value) {
    if (!props.file) {
        return;
    }

    emit("move-file", {
        file: props.file,
        folder_id: value === "" ? null : Number(value),
    });
}
</script>

<template>
    <aside v-if="file" class="media-sidebar">
        <div class="media-sidebar__header">
            <p>
                <strong>{{ file.original_name }}</strong>
            </p>

            <button
                type="button"
                class="button-base button-close"
                @click="emit('close')"
            >
                <Icon name="close" width="22" height="22" />
            </button>
        </div>

        <div class="media-sidebar__preview">
            <img
                :src="file.preview_url || file.url"
                :alt="file.alt_text || file.original_name"
            />
        </div>

        <MediaFactsPanel
            v-if="showFacts"
            :file="file"
            :cropped-dimensions="croppedDimensions"
            :output-dimensions="outputDimensions"
            :show-transform-facts="showTransformFacts"
        />

        <form class="admin-form-stack" @submit.prevent="submit">
            <label class="admin-form-label">
                <span>Название:</span>
                <input
                    v-model="form.original_name"
                    class="admin-input name"
                    type="text"
                    placeholder="Название"
                />
            </label>

            <label class="admin-form-label">
                <span>Alt текст:</span>
                <input
                    v-model="form.alt_text"
                    class="admin-input alt"
                    type="text"
                />
            </label>

            <div
                class="admin-select-custom folder"
                data-label="Выберите папку:"
            >
                <AdminSelect
                    v-model="form.folder_id"
                    :options="
                        moveFolderOptions.map((folder) => ({
                            value: folder.id ?? '',
                            label: folder.path || folder.name,
                        }))
                    "
                    @update:modelValue="handleMoveFile"
                />
            </div>

            <div class="admin-actions-row media-sidebar__actions">
                <button
                    type="button"
                    class="button-base button-danger"
                    @click="emit('delete', file)"
                    title="Удалить"
                >
                    <Icon name="trash" width="20" height="20" />
                </button>

                <button
                    type="button"
                    class="button-base button-copy"
                    @click="emit('copy-url', file)"
                    title="Скопировать URL"
                >
                    <Icon name="copy" width="22" height="22" />
                </button>

                <AdminButton
                    type="submit"
                    variant="primary"
                    :disabled="saving"
                    title="Сохранить изменения"
                >
                    <Icon name="save" width="18" height="18" />
                </AdminButton>

                <button
                    v-if="editable"
                    type="button"
                    class="button-link"
                    @click="emit('edit', file)"
                    title="Редактировать"
                >
                    <Icon name="pencil" width="20" height="20" />
                </button>

                <button
                    type="button"
                    class="button-base button-secondary"
                    :disabled="selecting"
                    @click="emit('select', file)"
                    title="Выбрать обложкой"
                >
                    <Icon name="check" width="24" height="24" />
                </button>
            </div>
        </form>
    </aside>
</template>
