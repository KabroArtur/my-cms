<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { fetchCurrentUser, logout } from '../api/auth'

const router = useRouter()
const loading = ref(true)
const user = ref(null)
const errorMessage = ref('')

const links = computed(() => {
    const permissions = new Set(user.value?.permissions ?? [])

    return [
        { to: '/admin', label: 'Dashboard', visible: true },
        { to: '/admin/pages', label: 'Pages', visible: permissions.has('pages.access') },
        { to: '/admin/users', label: 'Users', visible: permissions.has('users.access') },
        { to: '/admin/roles', label: 'Roles', visible: permissions.has('roles.access') },
        { to: '/admin/media', label: 'Media', visible: permissions.has('media.access') },
        { to: '/admin/settings', label: 'Settings', visible: permissions.has('settings.access') },
    ].filter((link) => link.visible)
})

async function loadCurrentUser() {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchCurrentUser()
        user.value = payload.data
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить пользователя.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function handleLogout() {
    try {
        await logout()
        await router.push('/login')
        window.location.href = '/login'
    } catch (error) {
        errorMessage.value = 'Не удалось завершить сессию.'
        console.error(error)
    }
}

onMounted(loadCurrentUser)
</script>

<template>
    <div class="admin-shell">
        <header class="admin-header">
            <div>
                <p class="eyebrow">CMS</p>
                <h1 class="admin-title">Admin Panel</h1>
            </div>

            <div class="admin-header__meta">
                <p v-if="loading" class="muted">Загрузка пользователя...</p>
                <p v-else-if="user" class="muted">{{ user.username }} | {{ user.roles.join(', ') }}</p>

                <p v-if="errorMessage" class="error-text"><strong>{{ errorMessage }}</strong></p>

                <button type="button" class="button-link" @click="handleLogout">
                    Logout
                </button>
            </div>
        </header>

        <nav class="admin-nav">
            <RouterLink
                v-for="link in links"
                :key="link.to"
                :to="link.to"
                class="nav-link"
            >
                {{ link.label }}
            </RouterLink>
        </nav>

        <main class="admin-content">
            <router-view />
        </main>
    </div>
</template>