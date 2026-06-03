<script setup>
import { onMounted, reactive, ref } from "vue";
import { RouterLink } from "vue-router";
import AdminBadge from "../../components/ui/AdminBadge.vue";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import AdminCheckbox from "../../components/ui/AdminCheckbox.vue";
import Icon from "../../components/ui/Icon.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import { createRole, fetchRoles, updateRole } from "../../api/roles";

const loading = ref(true);
const errorMessage = ref("");
const roles = ref([]);
const saving = ref(false);
const validationErrors = ref({});
const editingId = ref(null);
const { notifyError, notifySuccess } = useAdminNotifications();

const form = reactive({
    name: "",
    slug: "",
    permission_slugs: [],
});

const permissionOptions = [
    { slug: "pages.access", label: "Pages" },
    { slug: "pages.create", label: "Pages: create" },
    { slug: "pages.update", label: "Pages: update" },
    { slug: "pages.delete", label: "Pages: delete" },
    {
        slug: "pages.additional_fields.manage",
        label: "Pages: additional fields",
    },
    { slug: "users.access", label: "Users" },
    { slug: "users.create", label: "Users: create" },
    { slug: "users.update", label: "Users: update" },
    { slug: "users.delete", label: "Users: delete" },
    { slug: "users.manage_roles", label: "Users: manage roles" },
    { slug: "roles.access", label: "Roles" },
    { slug: "roles.create", label: "Roles: create" },
    { slug: "roles.update", label: "Roles: update" },
    { slug: "settings.access", label: "Settings" },
    { slug: "settings.general.manage", label: "Settings: general" },
    { slug: "settings.appearance.manage", label: "Settings: appearance" },
    { slug: "settings.cache.manage", label: "Settings: cache" },
    { slug: "settings.security.manage", label: "Settings: security" },
    { slug: "media.access", label: "Media" },
    { slug: "media.upload", label: "Media: upload" },
    { slug: "media.delete", label: "Media: delete" },
    { slug: "media.manage_folders", label: "Media: folders" },
];

const permissionChildren = {
    "pages.access": [
        "pages.create",
        "pages.update",
        "pages.delete",
        "pages.additional_fields.manage",
    ],
    "users.access": [
        "users.create",
        "users.update",
        "users.delete",
        "users.manage_roles",
    ],
    "roles.access": ["roles.create", "roles.update"],
    "settings.access": [
        "settings.general.manage",
        "settings.appearance.manage",
        "settings.cache.manage",
        "settings.security.manage",
    ],
    "media.access": ["media.upload", "media.delete", "media.manage_folders"],
};

const permissionParent = Object.entries(permissionChildren).reduce(
    (carry, [parent, children]) => {
        children.forEach((child) => {
            carry[child] = parent;
        });

        return carry;
    },
    {},
);

function resetForm() {
    editingId.value = null;
    validationErrors.value = {};
    form.name = "";
    form.slug = "";
    form.permission_slugs = [];
}

function startEdit(role) {
    editingId.value = role.id;
    validationErrors.value = {};
    errorMessage.value = "";
    form.name = role.name;
    form.slug = role.slug;
    form.permission_slugs = [...(role.permissions ?? [])];
}

function togglePermission(slug) {
    if (form.permission_slugs.includes(slug)) {
        const next = new Set(
            form.permission_slugs.filter((value) => value !== slug),
        );
        (permissionChildren[slug] ?? []).forEach((child) => next.delete(child));
        form.permission_slugs = Array.from(next);

        return;
    }

    const next = new Set(form.permission_slugs);
    next.add(slug);

    const parent = permissionParent[slug];

    if (parent) {
        next.add(parent);
    }

    form.permission_slugs = Array.from(next);
}

async function submitForm() {
    saving.value = true;
    errorMessage.value = "";
    validationErrors.value = {};

    try {
        if (editingId.value) {
            const payload = await updateRole(editingId.value, form);
            const index = roles.value.findIndex(
                (role) => role.id === editingId.value,
            );

            if (index !== -1) {
                roles.value[index] = payload.data;
            }

            notifySuccess("Роль обновлена.");
        } else {
            const payload = await createRole(form);
            roles.value.push(payload.data);
            notifySuccess("Роль создана.");
        }

        resetForm();
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {};
        } else {
            errorMessage.value = "Не удалось сохранить роль.";
            notifyError(errorMessage.value);
        }

        console.error(error);
    } finally {
        saving.value = false;
    }
}

async function loadRoles() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const payload = await fetchRoles();
        roles.value = payload.data ?? [];
    } catch (error) {
        errorMessage.value = "Не удалось загрузить роли.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

onMounted(loadRoles);
</script>

<template>
    <AdminPage
        description="Экран ролей и прав открыт как внутренний раздел из страницы пользователей, а не как отдельный пункт общего меню."
    >
        <template #actions>
            <RouterLink :to="{ name: 'users' }" class="button-base">
                <Icon name="users" width="18" height="18" />К пользователям
            </RouterLink>
        </template>

        <div class="roles-grid">
            <AdminCard>
                <div class="section-header">
                    <h2>
                        {{ editingId ? "Редактирование роли" : "Новая роль" }}
                    </h2>
                </div>

                <form
                    class="admin-form-stack admin-stack mt-16"
                    @submit.prevent="submitForm"
                >
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

                    <label class="admin-form-label slug" data-label="Slug:">
                        <input
                            v-model="form.slug"
                            class="admin-input"
                            type="text"
                        />
                        <small
                            v-if="validationErrors.slug"
                            class="error-text"
                            >{{ validationErrors.slug[0] }}</small
                        >
                    </label>
                    <legend>Access</legend>
                    <div class="field-definition-editor__card">
                        <AdminCheckbox
                            v-for="permission in permissionOptions"
                            :key="permission.slug"
                            v-model="form.permission_slugs"
                            :value="permission.slug"
                        >
                            {{ permission.label }}
                        </AdminCheckbox>
                    </div>

                    <p v-if="errorMessage" class="error-text">
                        {{ errorMessage }}
                    </p>

                    <div class="flex-end">
                        <AdminButton
                            type="submit"
                            variant="primary"
                            :disabled="saving"
                            class="button-secondary"
                            title="Создать роль"
                        >
                            <Icon name="new" width="18" height="18" />
                            {{
                                saving
                                    ? "Сохранение..."
                                    : editingId
                                      ? "Сохранить роль"
                                      : "Создать роль"
                            }}
                        </AdminButton>

                        <AdminButton
                            v-if="editingId"
                            type="button"
                            @click="resetForm"
                            class="button-danger"
                        >
                            <Icon name="cancel" width="18" height="18" />Отмена
                        </AdminButton>
                    </div>
                </form>
            </AdminCard>

            <AdminCard>
                <p v-if="loading" class="muted text-center">
                    Загрузка ролей...
                </p>
                <p
                    v-else-if="errorMessage && roles.length === 0"
                    class="error-text"
                >
                    {{ errorMessage }}
                </p>
                <p v-else-if="roles.length === 0" class="muted text-center">
                    Роли пока не найдены.
                </p>

                <table v-else class="table user user-new">
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
                                        name="link"
                                        width="14"
                                        height="14"
                                    />Slug
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
                                        name="users"
                                        width="14"
                                        height="14"
                                    />Пользователи
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
                        <tr v-for="role in roles" :key="role.id">
                            <td>{{ role.id }}</td>
                            <td>
                                <strong>{{ role.name }}</strong>
                            </td>
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
                                <div class="cell-actions">
                                    <AdminButton
                                        type="button"
                                        @click="startEdit(role)"
                                        title="Редактировать роль"
                                        class="button-link"
                                    >
                                        <Icon
                                            name="pencil"
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
</template>
