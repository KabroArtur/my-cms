import { ref } from "vue";

const notifications = ref([]);
const notificationTimers = new Map();

function dismissNotification(id) {
    const timer = notificationTimers.get(id);

    if (timer) {
        window.clearTimeout(timer);
        notificationTimers.delete(id);
    }

    notifications.value = notifications.value.filter((item) => item.id !== id);
}

function pushNotification(message, tone = "success", options = {}) {
    const content =
        typeof message === "object"
            ? String(message.message ?? "").trim()
            : String(message ?? "").trim();

    if (content === "") {
        return null;
    }

    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const duration = Number(options.duration ?? 4200);
    const createdAt = Date.now();

    const finalTone =
        typeof message === "object" ? (message.tone ?? tone) : tone;

    notifications.value = [
        ...notifications.value,
        {
            id,
            message: content,
            tone: finalTone,
            duration,
            createdAt,
            expiresAt: duration > 0 ? createdAt + duration : null,
        },
    ];

    if (duration > 0 && typeof window !== "undefined") {
        const timer = window.setTimeout(() => {
            dismissNotification(id);
        }, duration);

        notificationTimers.set(id, timer);
    }

    return id;
}

export function useAdminNotifications() {
    return {
        notifications,
        dismissNotification,
        notify: pushNotification,
        notifySuccess: (message, options) =>
            pushNotification(message, "success", options),
        notifyError: (message, options) =>
            pushNotification(message, "error", options),
        notifyWarning: (message, options) =>
            pushNotification(message, "warning", options),
        notifyInfo: (message, options) =>
            pushNotification(message, "info", options),
    };
}
