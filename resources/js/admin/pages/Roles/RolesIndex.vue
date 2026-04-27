<script setup>
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminBadge from '../../components/ui/AdminBadge.vue'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import { createRole, fetchRoles, updateRole } from '../../api/roles'

const loading = ref(true)
const errorMessage = ref('')
const roles = ref([])
const saving = ref(false)
const validationErrors = ref({})
const editingId = ref(null)

const form = reactive({
    name: '',
    slug: '',
    permission_slugs: [],
})

const permissionOptions = [
    { slug: 'pages.access', label: 'Pages' },
    { slug: 'users.access', label: 'Users' },
    { slug: 'roles.access', label: 'Roles' },
    { slug: 'settings.access', label: 'Settings' },
    { slug: 'media.access', label: 'Media' },
]

function resetForm() {
    editingId.value = null
    validationErrors.value = {}
    form.name = ''
    form.slug = ''
    form.permission_slugs = []
}

function startEdit(role) {
    editingId.value = role.id
    validationErrors.value = {}
    errorMessage.value = ''
    form.name = role.name
    form.slug = role.slug
    form.permission_slugs = [...(role.permissions ?? [])]
}

function togglePermission(slug) {
    if (form.permission_slugs.includes(slug)) {
        form.permission_slugs = form.permission_slugs.filter((value) => value !== slug)

        return
    }

    form.permission_slugs = [...form.permission_slugs, slug]
}

async function submitForm() {
    saving.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        if (editingId.value) {
            const payload = await updateRole(editingId.value, form)
            const index = roles.value.findIndex((role) => role.id === editingId.value)

            if (index !== -1) {
                roles.value[index] = payload.data
            }
        } else {
            const payload = await createRole(form)
            roles.value.push(payload.data)
        }

        resetForm()
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = 'Не удалось сохранить роль.'
        }

        console.error(error)
    } finally {
        saving.value = false
    }
}

async function loadRoles() {
    loading.value = true
    errorMessage.value = ''

    try {
        const payload = await fetchRoles()
        roles.value = payload.data ?? []
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить роли.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

onMounted(loadRoles)
</script>

<template>
    <AdminPage
        eyebrow="Access"
        title="Доступы"
        description="Экран ролей и прав открыт как внутренний раздел из страницы пользователей, а не как отдельный пункт общего меню."
    >
        <template #actions>
            <RouterLink to="/admin/users" class="button-link">
                К пользователям
            </RouterLink>
        </template>

        <div class="roles-grid">
            <AdminCard>
                <h2>{{ editingId ? 'Редактирование роли' : 'Новая роль' }}</h2>

                <form class="admin-form-stack" @submit.prevent="submitForm">
                    <label class="admin-form-label">
                        <span>Name</span>
                        <input v-model="form.name" class="admin-input" type="text">
                        <small v-if="validationErrors.name" class="error-text">{{ validationErrors.name[0] }}</small>
                    </label>

                    <label class="admin-form-label">
                        <span>Slug</span>
                        <input v-model="form.slug" class="admin-input" type="text">
                        <small v-if="validationErrors.slug" class="error-text">{{ validationErrors.slug[0] }}</small>
                    </label>

                    <fieldset class="admin-fieldset">
                        <legend>Access</legend>

                        <label
                            v-for="permission in permissionOptions"
                            :key="permission.slug"
                            class="admin-choice"
                        >
                            <input
                                :checked="form.permission_slugs.includes(permission.slug)"
                                type="checkbox"
                                @change="togglePermission(permission.slug)"
                            >
                            <span>{{ permission.label }}</span>
                        </label>
                    </fieldset>

                    <p v-if="errorMessage" class="error-text">{{ errorMessage }}</p>

                    <div class="admin-actions-row">
                        <AdminButton type="submit" variant="primary" :disabled="saving">
                            {{ saving ? 'Сохранение...' : (editingId ? 'Сохранить роль' : 'Создать роль') }}
                        </AdminButton>

                        <AdminButton v-if="editingId" type="button" @click="resetForm">
                            Отмена
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>

            <AdminCard>
                <p v-if="loading" class="muted">Загрузка ролей...</p>
                <p v-else-if="errorMessage && roles.length === 0" class="error-text">{{ errorMessage }}</p>
                <p v-else-if="roles.length === 0" class="muted">Роли пока не найдены.</p>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Permissions</th>
                            <th>Users</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="role in roles" :key="role.id">
                            <td>{{ role.id }}</td>
                            <td>{{ role.name }}</td>
                            <td>{{ role.slug }}</td>
                            <td>
                                <AdminBadge
                                    v-for="permission in role.permissions"
                                    :key="permission"
                                    soft
                                >
                                    {{ permission }}
                                </AdminBadge>
                            </td>
                            <td>{{ role.users_count }}</td>
                            <td>
                                <div class="admin-actions-row">
                                    <AdminButton type="button" @click="startEdit(role)">
                                        Edit
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