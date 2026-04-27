<script setup>
import { onMounted, reactive, ref } from 'vue'
import AdminBadge from '../../components/ui/AdminBadge.vue'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { fetchRoles } from '../../api/roles'
import { createUser, deleteUser, fetchUsers, updateUser } from '../../api/users'

const loading = ref(true)
const errorMessage = ref('')
const users = ref([])
const roles = ref([])
const saving = ref(false)
const validationErrors = ref({})
const editingId = ref(null)

const form = reactive({
    name: '',
    username: '',
    email: '',
    password: '',
    role_slugs: [],
})

function resetForm() {
    editingId.value = null
    validationErrors.value = {}
    form.name = ''
    form.username = ''
    form.email = ''
    form.password = ''
    form.role_slugs = []
}

function startEdit(user) {
    editingId.value = user.id
    validationErrors.value = {}
    errorMessage.value = ''
    form.name = user.name ?? ''
    form.username = user.username ?? ''
    form.email = user.email ?? ''
    form.password = ''
    form.role_slugs = [...(user.roles ?? [])]
}

function toggleRole(slug) {
    if (form.role_slugs.includes(slug)) {
        form.role_slugs = form.role_slugs.filter((value) => value !== slug)

        return
    }

    form.role_slugs = [...form.role_slugs, slug]
}

async function loadUsers() {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchUsers()
        users.value = payload.data ?? []
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить пользователей.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function loadRoles() {
    try {
        const payload = await fetchRoles()
        roles.value = payload.data ?? []
    } catch (error) {
        console.error(error)
    }
}

async function submitForm() {
    saving.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        if (editingId.value) {
            const payload = await updateUser(editingId.value, form)
            const index = users.value.findIndex((user) => user.id === editingId.value)

            if (index !== -1) {
                users.value[index] = payload.data
            }
        } else {
            const payload = await createUser(form)
            users.value.push(payload.data)
        }

        resetForm()
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = editingId.value
                ? 'Не удалось обновить пользователя.'
                : 'Не удалось создать пользователя.'
        }

        console.error(error)
    } finally {
        saving.value = false
    }
}

async function removeUser(user) {
    const confirmed = window.confirm(`Удалить пользователя "${user.username}"?`)

    if (!confirmed) {
        return
    }

    errorMessage.value = ''

    try {
        await deleteUser(user.id)
        users.value = users.value.filter((item) => item.id !== user.id)

        if (editingId.value === user.id) {
            resetForm()
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось удалить пользователя.'
        console.error(error)
    }
}

onMounted(async () => {
    await Promise.all([loadUsers(), loadRoles()])
})
</script>

<template>
    <AdminPage
        eyebrow="Users"
        title="Пользователи"
        description="Простой список пользователей, ролей и разрешений административной системы."
    >

        <div class="users-grid">
            <AdminCard>
                <h2>{{ editingId ? 'Редактирование пользователя' : 'Новый пользователь' }}</h2>

                <form class="admin-form-stack" @submit.prevent="submitForm">
                    <label class="admin-form-label">
                        <span>Name</span>
                        <input v-model="form.name" class="admin-input" type="text">
                        <small v-if="validationErrors.name" class="error-text">{{ validationErrors.name[0] }}</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Login</span>
                        <input v-model="form.username" class="admin-input" type="text">
                        <small v-if="validationErrors.username" class="error-text">{{ validationErrors.username[0] }}</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Email</span>
                        <input v-model="form.email" class="admin-input" type="email">
                        <small v-if="validationErrors.email" class="error-text">{{ validationErrors.email[0] }}</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Password</span>
                        <input v-model="form.password" class="admin-input" type="password" :placeholder="editingId ? 'Оставить пустым, чтобы не менять' : ''">
                        <small v-if="validationErrors.password" class="error-text">{{ validationErrors.password[0] }}</small>
                    </label>

                    <fieldset class="admin-fieldset">
                        <legend>Roles</legend>

                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="admin-choice"
                        >
                            <input
                                :checked="form.role_slugs.includes(role.slug)"
                                type="checkbox"
                                @change="toggleRole(role.slug)"
                            >
                            <span>{{ role.name }} ({{ role.slug }})</span>
                        </label>
                    </fieldset>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving">
                            {{ saving ? 'Сохранение...' : (editingId ? 'Сохранить пользователя' : 'Создать пользователя') }}
                        </AdminButton>

                        <AdminButton v-if="editingId" type="button" @click="resetForm">
                            Отмена
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>

            <AdminCard>
                <p v-if="loading" class="muted">Загрузка пользователей...</p>
                <p v-else-if="errorMessage && users.length === 0" class="error-text">{{ errorMessage }}</p>
                <p v-else-if="users.length === 0" class="muted">Пользователи пока не найдены.</p>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Login</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Permissions</th>
                            <th>2FA</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>{{ user.id }}</td>
                            <td>{{ user.name }}</td>
                            <td>{{ user.username }}</td>
                            <td>{{ user.email }}</td>
                            <td>
                                <AdminBadge
                                    v-for="role in user.roles"
                                    :key="role"
                                >
                                    {{ role }}
                                </AdminBadge>
                            </td>
                            <td>
                                <AdminBadge
                                    v-for="permission in user.permissions"
                                    :key="permission"
                                    soft
                                >
                                    {{ permission }}
                                </AdminBadge>
                            </td>
                            <td>{{ user.two_factor_enabled ? 'On' : 'Off' }}</td>
                            <td>
                                <div class="admin-actions-row">
                                    <AdminButton type="button" @click="startEdit(user)">
                                        Edit
                                    </AdminButton>

                                    <AdminButton type="button" variant="danger" @click="removeUser(user)">
                                        Delete
                                    </AdminButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AdminCard>
        </div>
    </AdminPage>
</template>