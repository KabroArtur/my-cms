<script setup>
import { computed, onMounted, ref } from "vue";
import { fetchCurrentUser } from "../../api/auth";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import {
    deletePlugin,
    disablePlugin,
    enablePlugin,
    fetchPlugins,
    installPlugin,
} from "../../api/plugins";

const loading = ref(false);
const workingSlug = ref("");
const errorMessage = ref("");
const plugins = ref([]);
const deleteDialogOpen = ref(false);
const deleteSlug = ref("");
const dropData = ref(false);
const removeFiles = ref(false);
const permissionSet = ref(new Set());

const canInstall = computed(() => permissionSet.value.has("plugins.install"));
const canEnable = computed(() => permissionSet.value.has("plugins.enable"));
const canDelete = computed(() => permissionSet.value.has("plugins.delete"));

async function loadPlugins() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const [pluginsPayload, mePayload] = await Promise.all([
            fetchPlugins(),
            fetchCurrentUser(),
        ]);

        permissionSet.value = new Set(mePayload.data?.permissions ?? []);
        plugins.value = Array.isArray(pluginsPayload.items)
            ? pluginsPayload.items
            : [];
    } catch (error) {
        errorMessage.value = "Не удалось загрузить список плагинов.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function runAction(slug, action) {
    workingSlug.value = slug;
    errorMessage.value = "";

    try {
        await action();
        await loadPlugins();
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message || "Операция не выполнена.";
        console.error(error);
    } finally {
        workingSlug.value = "";
    }
}

const deletePluginLabel = () => {
    const plugin = plugins.value.find((item) => item.slug === deleteSlug.value);

    return plugin?.name || deleteSlug.value;
};

async function handleInstall(slug) {
    if (!canInstall.value) {
        return;
    }

    await runAction(slug, () => installPlugin(slug));
}

async function handleEnable(slug) {
    if (!canEnable.value) {
        return;
    }

    await runAction(slug, () => enablePlugin(slug));
}

async function handleDisable(slug) {
    if (!canEnable.value) {
        return;
    }

    await runAction(slug, () => disablePlugin(slug));
}

async function handleDelete(slug) {
    if (!canDelete.value) {
        return;
    }

    deleteSlug.value = slug;
    dropData.value = false;
    removeFiles.value = false;
    deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
    deleteDialogOpen.value = false;
    deleteSlug.value = "";
    dropData.value = false;
    removeFiles.value = false;
}

async function confirmDelete() {
    if (!canDelete.value) {
        return;
    }

    const slug = deleteSlug.value;

    if (!slug) {
        return;
    }

    await runAction(slug, () =>
        deletePlugin(slug, {
            drop_data: dropData.value,
            remove_files: removeFiles.value,
        }),
    );

    closeDeleteDialog();
}

function statusLabel(status) {
    if (status === "enabled") {
        return "Включен";
    }

    if (status === "installed") {
        return "Установлен";
    }

    if (status === "disabled") {
        return "Выключен";
    }

    return "Не установлен";
}

onMounted(loadPlugins);
</script>

<template>
    <AdminPage
        eyebrow="Модули"
        title="Плагины"
        description="Установка, включение, выключение и удаление модулей CMS."
    >
        <AdminCard>
            <p v-if="errorMessage" class="error-text">
                <strong>{{ errorMessage }}</strong>
            </p>
            <p v-if="loading" class="muted">Загрузка...</p>

            <table v-else class="table">
                <thead>
                    <tr>
                        <th>Плагин</th>
                        <th>Версия</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="plugin in plugins" :key="plugin.slug">
                        <td>
                            <strong>{{ plugin.name }}</strong>
                            <p class="muted">
                                {{ plugin.description || plugin.slug }}
                            </p>
                        </td>
                        <td>{{ plugin.version }}</td>
                        <td>
                            <span
                                class="plugin-status-badge"
                                :class="`is-${plugin.status}`"
                            >
                                {{ statusLabel(plugin.status) }}
                            </span>
                        </td>
                        <td class="table-actions">
                            <AdminButton
                                v-if="!plugin.installed"
                                :disabled="
                                    workingSlug === plugin.slug || !canInstall
                                "
                                @click="handleInstall(plugin.slug)"
                            >
                                Установить
                            </AdminButton>

                            <AdminButton
                                v-if="plugin.installed && !plugin.enabled"
                                :disabled="
                                    workingSlug === plugin.slug || !canEnable
                                "
                                variant="primary"
                                @click="handleEnable(plugin.slug)"
                            >
                                Включить
                            </AdminButton>

                            <AdminButton
                                v-if="plugin.enabled"
                                :disabled="
                                    workingSlug === plugin.slug || !canEnable
                                "
                                @click="handleDisable(plugin.slug)"
                            >
                                Выключить
                            </AdminButton>

                            <AdminButton
                                v-if="plugin.installed"
                                :disabled="
                                    workingSlug === plugin.slug || !canDelete
                                "
                                variant="danger"
                                @click="handleDelete(plugin.slug)"
                            >
                                Удалить
                            </AdminButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <div
            v-if="deleteDialogOpen"
            class="admin-modal"
            @click.self="closeDeleteDialog"
        >
            <div class="admin-modal__dialog">
                <div class="admin-modal__header">
                    <div>
                        <p class="eyebrow">Плагины</p>
                        <h2>Удаление {{ deletePluginLabel() }}</h2>
                    </div>
                </div>

                <div class="admin-modal__body admin-stack">
                    <label class="admin-form-label">
                        <span>
                            <input v-model="dropData" type="checkbox" />
                            Удалить данные плагина (таблицы и права)
                        </span>
                    </label>

                    <label class="admin-form-label">
                        <span>
                            <input v-model="removeFiles" type="checkbox" />
                            Удалить файлы плагина с диска
                        </span>
                    </label>

                    <p class="muted">
                        Если оба флага выключены, удалится только запись об
                        установке, а данные и файлы останутся.
                    </p>
                </div>

                <div class="admin-actions-row">
                    <AdminButton type="button" @click="closeDeleteDialog"
                        >Отмена</AdminButton
                    >
                    <AdminButton
                        type="button"
                        variant="danger"
                        :disabled="workingSlug === deleteSlug || !canDelete"
                        @click="confirmDelete"
                    >
                        {{
                            workingSlug === deleteSlug
                                ? "Удаляем..."
                                : "Удалить плагин"
                        }}
                    </AdminButton>
                </div>
            </div>
        </div>
    </AdminPage>
</template>
