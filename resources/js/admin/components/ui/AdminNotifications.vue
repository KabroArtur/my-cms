<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAdminNotifications } from '../../composables/useAdminNotifications'

const { notifications, dismissNotification } = useAdminNotifications()
const now = ref(Date.now())
let tickTimer = null

const renderedNotifications = computed(() => notifications.value.map((notification) => {
    if (!notification.duration || !notification.expiresAt) {
        return {
            ...notification,
            progress: 100,
        }
    }

    const remaining = Math.max(0, notification.expiresAt - now.value)

    return {
        ...notification,
        progress: Math.max(0, Math.min(100, (remaining / notification.duration) * 100)),
    }
}))

onMounted(() => {
    tickTimer = window.setInterval(() => {
        now.value = Date.now()
    }, 100)
})

onBeforeUnmount(() => {
    if (tickTimer) {
        window.clearInterval(tickTimer)
    }
})
</script>

<template>
    <div class="admin-notifications" aria-live="polite" aria-atomic="true">
        <transition-group name="admin-notification">
            <article
                v-for="notification in renderedNotifications"
                :key="notification.id"
                class="admin-notification"
                :class="`is-${notification.tone}`"
            >
                <div class="admin-notification__body">
                    <p>{{ notification.message }}</p>
                    <span v-if="notification.duration > 0" class="admin-notification__progress">
                        <span :style="{ width: `${notification.progress}%` }"></span>
                    </span>
                </div>

                <button type="button" class="admin-notification__close" @click="dismissNotification(notification.id)">
                    Закрыть
                </button>
            </article>
        </transition-group>
    </div>
</template>