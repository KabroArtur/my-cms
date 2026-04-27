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
        { to: '/admin/pages', label: 'Pages', visible: permissions.has('pages.view') },
        { to: '/admin/media', label: 'Media', visible: permissions.has('media.view') },
        { to: '/admin/settings', label: 'Settings', visible: permissions.has('settings.view') },
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
    <div>
        <header>
            <p>CMS</p>

            <p v-if="loading">Загрузка пользователя...</p>
            <p v-else-if="user">{{ user.username }} | {{ user.roles.join(', ') }}</p>

            <p v-if="errorMessage"><strong>{{ errorMessage }}</strong></p>

            <button type="button" @click="handleLogout">
                [ Logout ]
            </button>
        </header>

        <nav>
            <RouterLink
                v-for="link in links"
                :key="link.to"
                :to="link.to"
            >
                {{ link.label }}
            </RouterLink>
        </nav>

        <main>
            <router-view />
        </main>
    </div>
</template>