<script setup>
import Icon from "../ui/Icon.vue";

defineProps({
    open: {
        type: Boolean,
        default: false,
    },

    title: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["close"]);

function closeModal() {
    emit("close");
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="admin-modal" @click.self="closeModal">
                <div class="admin-modal__dialog">
                    <div class="admin-modal__header">
                        <div class="admin-modal__title">
                            <h2>
                                <slot name="title">
                                    {{ title }}
                                </slot>
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="button-base button-secondary button-close"
                            @click="closeModal"
                        >
                            <Icon name="close" width="22" height="22" />
                        </button>
                    </div>

                    <div class="admin-modal__body text-center">
                        <slot />

                        <div v-if="$slots.footer" class="admin-modal__footer">
                            <slot name="footer" />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
