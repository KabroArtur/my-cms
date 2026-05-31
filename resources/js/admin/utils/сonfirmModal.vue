import { reactive } from "vue";

const confirmModal = reactive({
    open: false,
    title: "",
    message: "",
    action: null,
});

function open({ title, message, action }) {
    confirmModal.open = true;
    confirmModal.title = title;
    confirmModal.message = message;
    confirmModal.action = action;
}

function close() {
    confirmModal.open = false;
    confirmModal.title = "";
    confirmModal.message = "";
    confirmModal.action = null;
}

async function confirmAction() {
    if (!confirmModal.action) return;

    try {
        await confirmModal.action();
    } finally {
        close();
    }
}

export default {
    state: confirmModal,
    open,
    close,
    confirmAction,
};
