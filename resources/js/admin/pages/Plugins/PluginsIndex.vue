<script setup>
import { computed, onMounted, ref } from "vue";
import { fetchCurrentUser } from "../../api/auth.js";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import Icon from "../../components/ui/Icon.vue";
import {
    deletePlugin,
    disablePlugin,
    enablePlugin,
    fetchPlugins,
    installPlugin,
} from "../../api/plugins.js";
import { useAdminNotifications } from "../../composables/useAdminNotifications.js";

const { notify } = useAdminNotifications();

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

        notify({
            message: "Операция выполнена успешно",
            tone: "success",
        });
    } catch (error) {
        notify({
            message: error?.response?.data?.message || "Операция не выполнена.",
            tone: "error",
        });
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
        description="Установка, включение, выключение и удаление модулей CMS."
    >
        <AdminCard>
            <p v-if="loading" class="muted">Загрузка...</p>

            <table v-else class="table">
                <thead>
                    <tr>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="plugins"
                                    width="14"
                                    height="14"
                                />Плагин
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="version"
                                    width="14"
                                    height="14"
                                />Версия
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="status"
                                    width="14"
                                    height="14"
                                />Статус
                            </div>
                        </th>
                        <th>
                            <div class="table-inner">
                                <Icon
                                    name="action"
                                    width="14"
                                    height="14"
                                />Действия
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="plugin in plugins" :key="plugin.slug">
                        <td>
                            {{ plugin.name }}
                            <p class="muted">
                                {{ plugin.description || plugin.slug }}
                            </p>
                        </td>
                        <td>
                            <div class="table-inner">{{ plugin.version }}</div>
                        </td>
                        <td>
                            <div class="table-inner">
                                <span
                                    class="plugin-status-badge"
                                    :class="`is-${plugin.status}`"
                                >
                                    {{ statusLabel(plugin.status) }}
                                </span>
                            </div>
                        </td>
                        <td class="table-actions">
                            <div class="table-inner">
                                <AdminButton
                                    v-if="!plugin.installed"
                                    :disabled="
                                        workingSlug === plugin.slug ||
                                        !canInstall
                                    "
                                    @click="handleInstall(plugin.slug)"
                                >
                                    <Icon
                                        name="install"
                                        width="18"
                                        height="18"
                                    />Установить
                                </AdminButton>

                                <AdminButton
                                    v-if="plugin.installed && !plugin.enabled"
                                    :disabled="
                                        workingSlug === plugin.slug ||
                                        !canEnable
                                    "
                                    variant="primary"
                                    @click="handleEnable(plugin.slug)"
                                >
                                    <Icon
                                        name="on"
                                        width="18"
                                        height="18"
                                    />Включить
                                </AdminButton>

                                <AdminButton
                                    v-if="plugin.enabled"
                                    :disabled="
                                        workingSlug === plugin.slug ||
                                        !canEnable
                                    "
                                    @click="handleDisable(plugin.slug)"
                                >
                                    <Icon
                                        name="on"
                                        width="18"
                                        height="18"
                                    />Выключить
                                </AdminButton>

                                <AdminButton
                                    v-if="plugin.installed"
                                    :disabled="
                                        workingSlug === plugin.slug ||
                                        !canDelete
                                    "
                                    variant="danger"
                                    @click="handleDelete(plugin.slug)"
                                >
                                    <Icon
                                        name="trash"
                                        width="18"
                                        height="18"
                                    />Удалить
                                </AdminButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AdminCard>

        <Transition name="modal">
            <div
                v-if="deleteDialogOpen"
                class="admin-modal"
                @click.self="closeDeleteDialog"
            >
                <div class="admin-modal__dialog">
                    <div class="admin-modal__header">
                        <h2>Удаление {{ deletePluginLabel() }}</h2>

                        <AdminButton
                            type="button"
                            class="button-close"
                            @click="closeDeleteDialog"
                            ><Icon name="close" width="22" height="22"
                        /></AdminButton>
                    </div>

                    <div class="admin-modal__body admin-stack">
                        <p class="muted text-center">
                            Если оба флага выключены, удалится только запись об
                            установке, а данные и файлы останутся.
                        </p>

                        <label class="admin-checkbox">
                            <input v-model="dropData" type="checkbox" />
                            <span
                                >Удалить данные плагина (таблицы и права)</span
                            >
                        </label>

                        <label class="admin-checkbox">
                            <input v-model="removeFiles" type="checkbox" />
                            <span>Удалить файлы плагина с диска</span>
                        </label>

                        <div class="admin-actions-row">
                            <AdminButton
                                type="button"
                                variant="danger"
                                :disabled="
                                    workingSlug === deleteSlug || !canDelete
                                "
                                @click="confirmDelete"
                            >
                                <Icon name="trash" width="18" height="18" />{{
                                    workingSlug === deleteSlug
                                        ? "Удаляем..."
                                        : "Удалить плагин"
                                }}
                            </AdminButton>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminPage>
</template>
