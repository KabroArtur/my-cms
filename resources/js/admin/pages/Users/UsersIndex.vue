<script setup>
import { onMounted, reactive, ref } from "vue";
import { RouterLink } from "vue-router";
import AdminBadge from "../../components/ui/AdminBadge.vue";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import AdminCheckbox from "../../components/ui/AdminCheckbox.vue";
import AdminModal from "../../components/ui/AdminModal.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import { fetchRoles } from "../../api/roles";
import Icon from "../../components/ui/Icon.vue";
import {
    createUser,
    deleteUser,
    fetchUsers,
    updateUser,
} from "../../api/users";

const loading = ref(true);
const errorMessage = ref("");
const users = ref([]);
const roles = ref([]);
const saving = ref(false);
const validationErrors = ref({});
const editingId = ref(null);
const showPassword = ref(false);
const deleteModalOpen = ref(false);
const userToDelete = ref(null);
const { notifyError, notifySuccess } = useAdminNotifications();

function confirmDelete(user) {
    userToDelete.value = user;
    deleteModalOpen.value = true;
}

const form = reactive({
    name: "",
    username: "",
    email: "",
    password: "",
    role_slugs: [],
});

function generateStrongPassword(length = 12) {
    const safeLength = Math.max(10, Number(length) || 10);
    const lowers = "abcdefghijkmnopqrstuvwxyz";
    const uppers = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    const digits = "23456789";
    const symbols = "!@#$%^&*()-_=+[]{};:,.?";
    const all = `${lowers}${uppers}${digits}${symbols}`;

    const pick = (charset) => charset[randomInt(charset.length)];
    const passwordChars = [
        pick(lowers),
        pick(uppers),
        pick(digits),
        pick(symbols),
    ];

    while (passwordChars.length < safeLength) {
        passwordChars.push(pick(all));
    }

    for (let index = passwordChars.length - 1; index > 0; index -= 1) {
        const swapIndex = randomInt(index + 1);
        const current = passwordChars[index];
        passwordChars[index] = passwordChars[swapIndex];
        passwordChars[swapIndex] = current;
    }

    form.password = passwordChars.join("");
}

function randomInt(max) {
    const bytes = new Uint32Array(1);
    window.crypto.getRandomValues(bytes);

    return bytes[0] % max;
}

function togglePasswordVisibility() {
    showPassword.value = !showPassword.value;
}

function resetForm() {
    editingId.value = null;
    showPassword.value = false;
    validationErrors.value = {};
    form.name = "";
    form.username = "";
    form.email = "";
    form.password = "";
    form.role_slugs = [];
}

function startEdit(user) {
    editingId.value = user.id;
    showPassword.value = false;
    validationErrors.value = {};
    errorMessage.value = "";
    form.name = user.name ?? "";
    form.username = user.username ?? "";
    form.email = user.email ?? "";
    form.password = "";
    form.role_slugs = [...(user.roles ?? [])];
}

function toggleRole(slug) {
    if (form.role_slugs.includes(slug)) {
        form.role_slugs = form.role_slugs.filter((value) => value !== slug);

        return;
    }

    form.role_slugs = [...form.role_slugs, slug];
}

async function loadUsers() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const payload = await fetchUsers();
        users.value = payload.data ?? [];
    } catch (error) {
        errorMessage.value = "Не удалось загрузить пользователей.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function loadRoles() {
    try {
        const payload = await fetchRoles();
        roles.value = payload.data ?? [];
    } catch (error) {
        console.error(error);
    }
}

async function submitForm() {
    saving.value = true;
    errorMessage.value = "";
    validationErrors.value = {};

    try {
        if (editingId.value) {
            const payload = await updateUser(editingId.value, form);
            const index = users.value.findIndex(
                (user) => user.id === editingId.value,
            );

            if (index !== -1) {
                users.value[index] = payload.data;
            }

            notifySuccess("Пользователь обновлен.");
        } else {
            const payload = await createUser(form);
            users.value.push(payload.data);
            notifySuccess("Пользователь создан.");
        }

        resetForm();
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {};
        } else {
            errorMessage.value = editingId.value
                ? "Не удалось обновить пользователя."
                : "Не удалось создать пользователя.";
            notifyError(errorMessage.value);
        }

        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function removeUser() {
    if (!userToDelete.value) return;

    try {
        await deleteUser(userToDelete.value.id);

        users.value = users.value.filter(
            (item) => item.id !== userToDelete.value.id,
        );

        notifySuccess("Пользователь удален.");

        if (editingId.value === userToDelete.value.id) {
            resetForm();
        }

        deleteModalOpen.value = false;
        userToDelete.value = null;
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? "Не удалось удалить пользователя.";

        notifyError(errorMessage.value);
        console.error(error);
    }
}

onMounted(async () => {
    await Promise.all([loadUsers(), loadRoles()]);
});
</script>

<template>
    <AdminPage
        description="Простой список пользователей, ролей и разрешений административной системы."
    >
        <template #actions>
            <div class="users-toolbar__actions">
                <RouterLink :to="{ name: 'roles' }" class="button-base">
                    <Icon name="lock" width="18" height="18" />Доступы и роли
                </RouterLink>
            </div>
        </template>

        <div class="section-wrapper">
            <AdminCard>
                <div class="section-header">
                    <h2>
                        {{
                            editingId
                                ? "Редактирование пользователя"
                                : "Новый пользователь"
                        }}
                    </h2>
                </div>

                <form class="admin-stack mt-16" @submit.prevent="submitForm">
                    <div class="admin-form-stack">
                        <label class="admin-form-label slug" data-label="Имя:">
                            <input
                                v-model="form.name"
                                class="admin-input"
                                type="text"
                            />
                            <small
                                v-if="validationErrors.name"
                                class="error-text"
                                >{{ validationErrors.name[0] }}</small
                            >
                        </label>

                        <label
                            class="admin-form-label locale"
                            data-label="Логин:"
                        >
                            <input
                                v-model="form.username"
                                class="admin-input"
                                type="text"
                            />
                            <small
                                v-if="validationErrors.username"
                                class="error-text"
                                >{{ validationErrors.username[0] }}</small
                            >
                        </label>

                        <label
                            class="admin-form-label rows"
                            data-label="Email:"
                        >
                            <input
                                v-model="form.email"
                                class="admin-input"
                                type="email"
                            />
                            <small
                                v-if="validationErrors.email"
                                class="error-text"
                                >{{ validationErrors.email[0] }}</small
                            >
                        </label>

                        <div class="admin-password">
                            <label
                                class="admin-form-label expert"
                                data-label="Пароль:"
                            >
                                <input
                                    v-model="form.password"
                                    class="admin-input"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="
                                        editingId
                                            ? 'Оставить пустым, чтобы не менять'
                                            : ''
                                    "
                                />
                                <small
                                    v-if="validationErrors.password"
                                    class="error-text"
                                    >{{ validationErrors.password[0] }}</small
                                >

                                <AdminButton
                                    type="button"
                                    @click="togglePasswordVisibility"
                                    class="button-eye"
                                >
                                    <Icon
                                        :name="
                                            showPassword ? 'eye-off' : 'show'
                                        "
                                        width="24"
                                        height="24"
                                    />
                                </AdminButton>
                            </label>
                            <p class="title-tooltip-down">
                                <Icon
                                    name="info"
                                    width="16"
                                    height="16"
                                /><strong
                                    >Минимум 6 символов. Кнопка генерирует
                                    сложный пароль от 10 символов.</strong
                                >
                            </p>
                            <AdminButton
                                type="button"
                                @click="generateStrongPassword(12)"
                            >
                                <Icon name="password" width="18" height="18" />
                                Сгенерировать пароль
                            </AdminButton>
                        </div>
                    </div>
                    <h3 class="admin-form-title">Роли</h3>
                    <div class="page-meta-grid-3 field-definition-editor__card">
                        <AdminCheckbox
                            v-for="role in roles"
                            :key="role.id"
                            :model-value="form.role_slugs.includes(role.slug)"
                            @update:model-value="toggleRole(role.slug)"
                            class="user-role"
                        >
                            <strong> {{ role.name }} ({{ role.slug }})</strong>
                        </AdminCheckbox>
                    </div>
                    <div class="flex-end">
                        <AdminButton
                            type="submit"
                            variant="primary"
                            :disabled="saving"
                        >
                            <Icon name="user-add" width="20" height="20" />
                            {{
                                saving
                                    ? "Сохранение..."
                                    : editingId
                                      ? "Сохранить пользователя"
                                      : "Создать пользователя"
                            }}
                        </AdminButton>

                        <AdminButton
                            v-if="editingId"
                            type="button"
                            @click="resetForm"
                            class="button-danger"
                        >
                            <Icon name="cancel" width="18" height="18" />
                            Отмена
                        </AdminButton>
                    </div>

                    <p v-if="errorMessage" class="error-text">
                        {{ errorMessage }}
                    </p>
                </form>
            </AdminCard>

            <AdminCard>
                <div class="section-header users-toolbar">
                    <h2 class="title-tooltip-down">
                        Список пользователей
                        <Icon name="info" width="16" height="16" /><strong
                            >Управление доступами вынесено в отдельный экран, но
                            вход в него находится прямо здесь.</strong
                        >
                    </h2>
                </div>

                <p v-if="loading" class="muted text-center">
                    Загрузка пользователей...
                </p>
                <p
                    v-else-if="errorMessage && users.length === 0"
                    class="error-text"
                >
                    {{ errorMessage }}
                </p>
                <p v-else-if="users.length === 0" class="muted">
                    Пользователи пока не найдены.
                </p>

                <table v-else class="table user">
                    <thead>
                        <tr>
                            <th>
                                <div class="table-inner"><span>#</span>ID</div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="avatar"
                                        width="14"
                                        height="14"
                                    />Имя
                                </div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="log"
                                        width="14"
                                        height="14"
                                    />Логин
                                </div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="email"
                                        width="14"
                                        height="14"
                                    />Email
                                </div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="role"
                                        width="14"
                                        height="14"
                                    />Роль
                                </div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="lock"
                                        width="14"
                                        height="14"
                                    />Права доступа
                                </div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="security"
                                        width="14"
                                        height="14"
                                    />2FA
                                </div>
                            </th>
                            <th>
                                <div class="table-inner">
                                    <Icon
                                        name="settings"
                                        width="14"
                                        height="14"
                                    />Действия
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>{{ user.id }}</td>
                            <td>
                                <strong>{{ user.name }}</strong>
                            </td>
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
                            <td>
                                <span
                                    class="plugin-status-badge"
                                    :class="
                                        user.two_factor_enabled
                                            ? 'is-enabled'
                                            : 'is-disabled'
                                    "
                                >
                                    {{
                                        user.two_factor_enabled
                                            ? "Включено"
                                            : "Выключено"
                                    }}
                                </span>
                            </td>
                            <td>
                                <div class="cell-actions">
                                    <AdminButton
                                        type="button"
                                        @click="startEdit(user)"
                                        class="button-link"
                                        title="Редактировать пользователя"
                                    >
                                        <Icon
                                            name="pencil"
                                            width="20"
                                            height="20"
                                        />
                                    </AdminButton>

                                    <AdminButton
                                        type="button"
                                        variant="danger"
                                        @click="confirmDelete(user)"
                                        title="Удалить пользователя"
                                    >
                                        <Icon
                                            name="trash"
                                            width="20"
                                            height="20"
                                        />
                                    </AdminButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AdminCard>
        </div>
    </AdminPage>
    <AdminModal
        :open="deleteModalOpen"
        title="Удаление пользователя"
        @close="deleteModalOpen = false"
    >
        <p>
            Удалить пользователя
            <strong>{{ userToDelete?.username }}</strong
            >?
        </p>

        <template #footer>
            <AdminButton @click="deleteModalOpen = false"> Отмена </AdminButton>

            <AdminButton variant="danger" @click="removeUser">
                Удалить
            </AdminButton>
        </template>
    </AdminModal>
</template>
