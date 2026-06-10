<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import AdminButton from "../../components/ui/AdminButton.vue";
import AdminCard from "../../components/ui/AdminCard.vue";
import AdminPage from "../../components/ui/AdminPage.vue";
import Icon from "../../components/ui/Icon.vue";
import AdminModal from "../../components/ui/AdminModal.vue";
import AdminSelect from "../../components/ui/AdminSelect.vue";
import AdminViewSettings from "../../components/ui/AdminViewSettings.vue";
import { useAdminNotifications } from "../../composables/useAdminNotifications";
import {
    formatCmsDateTime,
    loadCmsSettings,
} from "../../composables/useCmsSettings";
import PageMenuTreeItem from "./components/PageMenuTreeItem.vue";

import {
    deletePage,
    fetchPages,
    fetchPageTree,
    fetchTrashedPages,
    permanentlyDeletePage,
    restorePage,
    savePageTree,
} from "../../api/pages";

const activeTab = ref("all");
const loading = ref(true);
const savingTree = ref(false);
const errorMessage = ref("");
const pages = ref([]);
const treePages = ref([]);
const trashedPages = ref([]);
const draggingPageId = ref(null);
const treeDirty = ref(false);
const menuModalOpen = ref(false);
const languageFilter = ref("all");
const languageOptions = ref([]);
const visibleFields = ref({
    shab: true,
    language: true,
    author: true,
    status: true,
    compact: false,
});

const deleteModalOpen = ref(false);
const pageToDelete = ref(null);
const fieldLabels = {
    shab: "Показывать шаблон",
    language: "Показывать язык",
    author: "Показывать автора",
    status: "Показывать статус",
    compact: "Компактный режим",
};

const searchQuery = ref("");
const statusFilter = ref("all");
const templateFilter = ref("all");
const sortBy = ref("updated_at");
const { notifyError, notifySuccess } = useAdminNotifications();

function pageQueryParams() {
    return languageFilter.value === "all"
        ? {}
        : { language_id: languageFilter.value };
}

function handleLanguageFilterChange() {
    loadPages();
}

const statusLabels = {
    draft: "Черновик",
    pending_review: "На проверке",
    scheduled: "Запланирована",
    published: "Опубликована",
    archived: "Архив",
};

const visibilityLabels = {
    public: "Публичная",
    private: "Скрытая",
};

function formatDateTime(value) {
    return formatCmsDateTime(value);
}

function resolvePublicUrl(page) {
    return (
        page.public_url ?? (page.is_home ? "/" : `/${page.path || page.slug}`)
    );
}

function resolveStatusLabel(status) {
    return statusLabels[status] ?? status;
}

function resolveVisibilityLabel(visibility) {
    return visibilityLabels[visibility] ?? visibility;
}

function resolveCreatorLabel(page) {
    if (page.creator?.name) {
        return page.creator.name;
    }

    if (page.creator?.username) {
        return page.creator.username;
    }

    return "Не указан";
}

const menuTree = computed(() => buildTree(treePages.value));
const orderedPages = computed(() => {
    if (treePages.value.length === 0) {
        return pages.value;
    }

    const order = flattenTree(menuTree.value).map((node) => node.id);
    const pageMap = new Map(pages.value.map((page) => [page.id, page]));

    return order.map((id) => pageMap.get(id)).filter(Boolean);
});

const visiblePages = computed(() => {
    let result = [...pages.value];

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) =>
                (p.title || "").toLowerCase().includes(q) ||
                (p.slug || "").toLowerCase().includes(q),
        );
    }

    if (languageFilter.value !== "all") {
        result = result.filter((p) => p.language_id == languageFilter.value);
    }

    const statusToUse =
        statusFilter.value !== "all"
            ? statusFilter.value
            : activeTab.value !== "all"
              ? activeTab.value
              : null;

    if (statusToUse) {
        result = result.filter((p) => p.status === statusToUse);
    }

    if (templateFilter.value !== "all") {
        result = result.filter(
            (p) => (p.layout || "default") === templateFilter.value,
        );
    }

    switch (sortBy.value) {
        case "created_at":
            result.sort(
                (a, b) => new Date(b.created_at) - new Date(a.created_at),
            );
            break;

        case "updated_at":
            result.sort(
                (a, b) => new Date(b.updated_at) - new Date(a.updated_at),
            );
            break;

        case "title":
            result.sort((a, b) => (a.title || "").localeCompare(b.title || ""));
            break;
    }

    return result;
});

function buildTree(items) {
    const nodes = items
        .map((item) => ({ ...item, children: [] }))
        .sort((left, right) => {
            if (left.sort_order !== right.sort_order) {
                return left.sort_order - right.sort_order;
            }

            return String(left.title).localeCompare(String(right.title), "ru");
        });

    const byId = new Map(nodes.map((node) => [node.id, node]));
    const roots = [];

    for (const node of nodes) {
        if (node.parent_id && byId.has(node.parent_id)) {
            byId.get(node.parent_id).children.push(node);
            continue;
        }

        roots.push(node);
    }

    return roots;
}

function flattenTree(nodes, parentId = null, bucket = []) {
    nodes.forEach((node, index) => {
        bucket.push({ id: node.id, parent_id: parentId, sort_order: index });
        flattenTree(node.children ?? [], node.id, bucket);
    });

    return bucket;
}

function containsNode(node, searchedId) {
    if (node.id === searchedId) {
        return true;
    }

    return (node.children ?? []).some((child) =>
        containsNode(child, searchedId),
    );
}

function removeNode(nodes, draggedId) {
    for (let index = 0; index < nodes.length; index += 1) {
        const currentNode = nodes[index];

        if (currentNode.id === draggedId) {
            return nodes.splice(index, 1)[0];
        }

        const removedChild = removeNode(currentNode.children ?? [], draggedId);

        if (removedChild) {
            return removedChild;
        }
    }

    return null;
}

function findChildrenContainer(nodes, parentId) {
    if (parentId === null) {
        return nodes;
    }

    for (const node of nodes) {
        if (node.id === parentId) {
            node.children = node.children ?? [];

            return node.children;
        }

        const nestedContainer = findChildrenContainer(
            node.children ?? [],
            parentId,
        );

        if (nestedContainer) {
            return nestedContainer;
        }
    }

    return null;
}

function rebuildTreePagesFromNodes(nodes) {
    const pageMap = new Map(treePages.value.map((page) => [page.id, page]));

    treePages.value = flattenTree(nodes).map((node) => ({
        ...pageMap.get(node.id),
        parent_id: node.parent_id,
        sort_order: node.sort_order,
    }));
}

function findNodeIndex(container, nodeId) {
    return container.findIndex((node) => node.id === nodeId);
}

function handleTreeMove({
    draggedId,
    targetParentId,
    targetIndex,
    anchorNodeId,
    position,
}) {
    if (!draggedId) {
        return;
    }

    const nodes = buildTree(treePages.value);
    const draggedNode = removeNode(nodes, draggedId);

    if (!draggedNode) {
        return;
    }

    if (targetParentId !== null && containsNode(draggedNode, targetParentId)) {
        draggingPageId.value = null;

        return;
    }

    const targetContainer =
        findChildrenContainer(nodes, targetParentId) ?? nodes;
    const resolvedIndex = anchorNodeId
        ? (() => {
              const anchorIndex = findNodeIndex(targetContainer, anchorNodeId);

              if (anchorIndex === -1) {
                  return targetContainer.length;
              }

              return position === "before" ? anchorIndex : anchorIndex + 1;
          })()
        : targetIndex;

    const safeIndex = Math.max(
        0,
        Math.min(resolvedIndex, targetContainer.length),
    );

    targetContainer.splice(safeIndex, 0, draggedNode);

    rebuildTreePagesFromNodes(nodes);
    treeDirty.value = true;
    draggingPageId.value = null;
}

async function handleTreeSave() {
    savingTree.value = true;
    errorMessage.value = "";

    try {
        await savePageTree(flattenTree(menuTree.value));
        treeDirty.value = false;
        await loadPages();
        notifySuccess("Структура меню сохранена.");
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            "Не удалось сохранить структуру меню.";
        notifyError(errorMessage.value);
        console.error(error);
    } finally {
        savingTree.value = false;
    }
}

async function loadPages() {
    loading.value = true;
    errorMessage.value = "";

    try {
        const [payload, treePayload, trashedPayload] = await Promise.all([
            fetchPages(pageQueryParams()),
            fetchPageTree(pageQueryParams()),
            fetchTrashedPages(pageQueryParams()),
        ]);

        pages.value = payload.data ?? [];
        treePages.value = treePayload.data ?? [];
        trashedPages.value = trashedPayload.data ?? [];
        treeDirty.value = false;
    } catch (error) {
        errorMessage.value = "Не удалось загрузить страницы.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

function removePage(page) {
    pageToDelete.value = page;
    deleteModalOpen.value = true;
}

async function confirmRemovePage() {
    if (!pageToDelete.value) {
        return;
    }

    errorMessage.value = "";

    try {
        const page = pageToDelete.value;

        await deletePage(page.id);

        pages.value = pages.value.filter((item) => item.id !== page.id);

        treePages.value = treePages.value.filter((item) => item.id !== page.id);

        trashedPages.value.unshift({
            ...page,
            deleted_at: new Date().toISOString(),
        });

        notifySuccess("Страница перемещена в корзину.");

        closeDeleteModal();
    } catch (error) {
        errorMessage.value = "Не удалось переместить страницу в корзину.";

        notifyError(errorMessage.value);

        console.error(error);
    }
}

function closeDeleteModal() {
    deleteModalOpen.value = false;
    pageToDelete.value = null;
}

async function restoreTrashedPage(page) {
    errorMessage.value = "";

    try {
        const payload = await restorePage(page.id);
        trashedPages.value = trashedPages.value.filter(
            (item) => item.id !== page.id,
        );
        pages.value.unshift(payload.data);
        treePages.value.unshift(payload.data);
        notifySuccess("Страница восстановлена.");
    } catch (error) {
        errorMessage.value = "Не удалось восстановить страницу.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

async function forceRemovePage(page) {
    const confirmed = window.confirm(
        `Удалить страницу "${page.title}" навсегда?`,
    );

    if (!confirmed) {
        return;
    }

    errorMessage.value = "";

    try {
        await permanentlyDeletePage(page.id);
        trashedPages.value = trashedPages.value.filter(
            (item) => item.id !== page.id,
        );
        notifySuccess("Страница удалена навсегда.");
    } catch (error) {
        errorMessage.value = "Не удалось удалить страницу навсегда.";
        notifyError(errorMessage.value);
        console.error(error);
    }
}

onMounted(async () => {
    const settingsPayload = await loadCmsSettings();
    languageOptions.value = settingsPayload.options?.languages ?? [];
    await loadPages();
});

function langIcon(code) {
    const map = {
        uk: "UA",
        ru: "RU",
        en: "EN",
    };

    return map[(code || "").toLowerCase()] || null;
}

const allCount = computed(() => pages.value.length);
const publishedCount = computed(
    () => pages.value.filter((p) => p.status === "published").length,
);
const archivedCount = computed(
    () => pages.value.filter((p) => p.status === "archived").length,
);
const trashCount = computed(() => trashedPages.value.length);

const tableColumns = computed(() => {
    const columns = ["minmax(260px, 2.2fr)"];

    if (visibleFields.value.author) {
        columns.push("minmax(140px, 1fr)");
    }

    if (visibleFields.value.language) {
        columns.push("minmax(120px, 0.8fr)");
    }

    if (visibleFields.value.shab) {
        columns.push("minmax(120px, 0.8fr)");
    }

    if (visibleFields.value.status) {
        columns.push("minmax(160px, 1.2fr)");
    }

    columns.push("minmax(180px, 1.4fr)");

    return columns.join(" ");
});

const trashColumns = computed(() => {
    const columns = ["2fr"];

    if (visibleFields.value.author) {
        columns.push("1.2fr");
    }

    columns.push("1fr");

    return columns.join(" ");
});
</script>

<template>
    <AdminPage
        description="Список страниц с переходом в отдельную карточку редактирования."
    >
        <template #actions>
            <div class="admin-actions-row">
                <AdminButton
                    type="button"
                    class="button-link"
                    @click="menuModalOpen = true"
                >
                    <Icon name="menu-btn" width="20" height="20" />Меню
                </AdminButton>

                <AdminViewSettings
                    v-model="visibleFields"
                    :labels="fieldLabels"
                >
                    <template #trigger>
                        <Icon name="setting" width="20" height="20" />
                    </template>
                </AdminViewSettings>

                <RouterLink :to="{ name: 'page-create' }" class="button-base">
                    <Icon name="new" width="18" height="18" />Новая страница
                </RouterLink>
            </div>
        </template>

        <div class="admin-page-grid">
            <AdminCard>
                <div class="admin-page-head">
                    <div
                        class="admin-tabs"
                        role="tablist"
                        aria-label="Фильтр страниц"
                    >
                        <button
                            type="button"
                            class="admin-tab"
                            :class="{ 'is-active': activeTab === 'all' }"
                            @click="activeTab = 'all'"
                        >
                            Все
                            <span class="tab-count">{{ allCount }}</span>
                        </button>

                        <button
                            type="button"
                            class="admin-tab"
                            :class="{ 'is-active': activeTab === 'published' }"
                            @click="activeTab = 'published'"
                        >
                            Опубликованные
                            <span class="tab-count">{{ publishedCount }}</span>
                        </button>

                        <button
                            type="button"
                            class="admin-tab"
                            :class="{ 'is-active': activeTab === 'archived' }"
                            @click="activeTab = 'archived'"
                        >
                            Архив
                            <span class="tab-count">{{ archivedCount }}</span>
                        </button>

                        <button
                            type="button"
                            class="admin-tab"
                            :class="{ 'is-active': activeTab === 'trash' }"
                            @click="activeTab = 'trash'"
                        >
                            Корзина
                            <span class="tab-count">{{ trashCount }}</span>
                        </button>
                    </div>
                </div>
                <div class="admin-filter-bar">
                    <div class="admin-filter-field admin-filter-field--search">
                        <Icon name="search" width="18" height="18" />

                        <input
                            v-model="searchQuery"
                            type="text"
                            class="admin-input"
                            placeholder="Поиск страниц"
                        />
                    </div>

                    <div class="admin-filter-field">
                        <AdminSelect
                            class="lang"
                            data-label="Язык:"
                            v-model="languageFilter"
                            :options="[
                                { value: 'all', label: 'Все языки' },
                                ...languageOptions,
                            ]"
                        />
                    </div>

                    <div class="admin-filter-field">
                        <AdminSelect
                            class="status"
                            data-label="Статус:"
                            v-model="statusFilter"
                            :options="[
                                { value: 'all', label: 'Все статусы' },
                                { value: 'draft', label: 'Черновик' },
                                {
                                    value: 'pending_review',
                                    label: 'На проверке',
                                },
                                { value: 'scheduled', label: 'Запланирована' },
                                { value: 'published', label: 'Опубликована' },
                                { value: 'archived', label: 'Архив' },
                            ]"
                        />
                    </div>

                    <div class="admin-filter-field">
                        <AdminSelect
                            class="shab"
                            data-label="Шаблон:"
                            v-model="templateFilter"
                            :options="[
                                { value: 'all', label: 'Все' },
                                { value: 'default', label: 'Default' },
                                { value: 'landing', label: 'Landing' },
                            ]"
                        />
                    </div>

                    <div class="admin-filter-field">
                        <AdminSelect
                            class="sort"
                            data-label="Сортировка:"
                            v-model="sortBy"
                            :options="[
                                { value: 'updated_at', label: 'Обновлено' },
                                { value: 'created_at', label: 'Создано' },
                                { value: 'title', label: 'Название' },
                            ]"
                        />
                    </div>
                </div>

                <p v-if="loading" class="muted text-center">
                    Загрузка страниц...
                </p>
                <p
                    v-else-if="
                        activeTab !== 'trash' && visiblePages.length === 0
                    "
                    class="muted text-center"
                >
                    По выбранному фильтру страниц нет.
                </p>
                <p
                    v-else-if="
                        activeTab === 'trash' && trashedPages.length === 0
                    "
                    class="muted text-center"
                >
                    Удалённых страниц пока нет.
                </p>

                <div v-else-if="activeTab !== 'trash'" class="admin-table">
                    <div
                        class="admin-table__head"
                        :style="{ gridTemplateColumns: tableColumns }"
                    >
                        <div>
                            <Icon name="page" width="14" height="14" />Страница
                        </div>
                        <div v-if="visibleFields.author">
                            <Icon name="avatar" width="14" height="14" />Автор
                        </div>
                        <div v-if="visibleFields.language">
                            <Icon name="lang" width="14" height="14" />Язык
                        </div>
                        <div v-if="visibleFields.shab">
                            <Icon name="shab" width="14" height="14" />Шаблон
                        </div>
                        <div v-if="visibleFields.status">
                            <Icon name="status" width="14" height="14" />Статус
                        </div>
                        <div>
                            <Icon
                                name="settings"
                                width="16"
                                height="16"
                            />Действия
                        </div>
                    </div>
                    <div
                        v-for="page in visiblePages"
                        :key="page.id"
                        class="admin-table__row"
                        :style="{ gridTemplateColumns: tableColumns }"
                    >
                        <div class="cell-page">
                            <RouterLink
                                :to="{
                                    name: 'page-edit',
                                    params: { id: page.id },
                                }"
                                class="page-title-link"
                            >
                                {{ page.title }}
                            </RouterLink>

                            <div v-if="visibleFields.slug" class="page-sub">
                                <span>ID {{ page.id }}</span>
                                <span>{{ resolvePublicUrl(page) }}</span>
                                <span>{{ page.slug }}</span>
                            </div>
                        </div>

                        <div
                            v-if="visibleFields.author"
                            class="admin-table__inner"
                        >
                            {{ resolveCreatorLabel(page) }}
                        </div>

                        <div
                            v-if="visibleFields.language"
                            class="admin-table__language"
                        >
                            <span
                                ><Icon
                                    v-if="page.language?.code"
                                    :name="langIcon(page.language.code)"
                                    width="36"
                                    height="38"
                            /></span>
                            {{ page.language?.native_name || "—" }}
                        </div>

                        <div
                            v-if="visibleFields.shab"
                            class="admin-table__inner"
                        >
                            {{ page.template || "default" }}
                        </div>

                        <div class="cell-status" v-if="visibleFields.status">
                            <div
                                :class="[
                                    'page-badge',
                                    `page-badge--${page.status}`,
                                ]"
                            >
                                <div class="cell-status__date">
                                    {{ formatDateTime(page.published_at) }}
                                </div>
                                {{ resolveStatusLabel(page.status) }}
                            </div>
                        </div>

                        <div class="cell-actions admin-table__inner">
                            <a
                                :href="resolvePublicUrl(page)"
                                target="_blank"
                                class="button-base"
                                title="Просмотреть страницу на сайте"
                            >
                                <Icon name="show" width="22" height="22" />
                            </a>

                            <RouterLink
                                :to="{
                                    name: 'page-edit',
                                    params: { id: page.id },
                                }"
                                class="button-link"
                                title="Редактировать страницу"
                            >
                                <Icon name="pencil" width="20" height="20" />
                            </RouterLink>

                            <AdminButton
                                type="button"
                                variant="danger"
                                title="Удалить страницу"
                                :disabled="!page.can?.delete"
                                @click="removePage(page)"
                            >
                                <Icon name="trash" width="20" height="20" />
                            </AdminButton>
                        </div>
                    </div>
                </div>

                <div v-else class="admin-table">
                    <div
                        class="admin-table__head trash"
                        :style="{ gridTemplateColumns: trashColumns }"
                    >
                        <div>
                            <Icon name="page" width="14" height="14" />Страница
                        </div>
                        <div v-if="visibleFields.author">
                            <Icon name="avatar" width="14" height="14" />Создал
                        </div>
                        <div>
                            <Icon
                                name="settings"
                                width="16"
                                height="16"
                            />Действия
                        </div>
                    </div>
                    <article
                        v-for="page in trashedPages"
                        :key="page.id"
                        class="page-trash-card"
                        :style="{ gridTemplateColumns: trashColumns }"
                    >
                        <div class="page-trash-card__title-block">
                            <h3>{{ page.title }}</h3>

                            <p>
                                ID{{ page.id }}<span></span>{{ page.slug }}
                                <span></span>Удалена:
                                <strong>
                                    {{
                                        formatDateTime(page.deleted_at)
                                    }}</strong
                                >
                            </p>
                        </div>
                        <p
                            class="admin-table__inner"
                            v-if="visibleFields.author"
                        >
                            <strong>{{ resolveCreatorLabel(page) }}</strong>
                        </p>
                        <div class="cell-actions">
                            <AdminButton
                                type="button"
                                @click="restoreTrashedPage(page)"
                                title="Восстановить страницу"
                            >
                                <Icon name="return" width="18" height="18" />
                            </AdminButton>

                            <AdminButton
                                type="button"
                                variant="danger"
                                :disabled="!page.can?.delete"
                                @click="forceRemovePage(page)"
                                title="Удалить навсегда"
                            >
                                <Icon name="trash" width="20" height="20" />
                            </AdminButton>
                        </div>
                    </article>
                </div>
            </AdminCard>
        </div>
        <Transition name="modal">
            <div
                v-if="menuModalOpen"
                class="admin-modal"
                @click.self="menuModalOpen = false"
            >
                <div class="admin-modal__dialog admin-modal__dialog--wide">
                    <div class="admin-modal__header">
                        <div>
                            <h2>Меню страниц</h2>
                        </div>

                        <div class="admin-actions-row">
                            <AdminButton
                                type="button"
                                class="button-save"
                                :disabled="savingTree || !treeDirty"
                                @click="handleTreeSave"
                            >
                                <Icon name="save" width="16" height="16" />
                                {{
                                    savingTree
                                        ? "Сохранение..."
                                        : "Сохранить структуру"
                                }}
                            </AdminButton>
                            <AdminButton
                                type="button"
                                class="button-close"
                                @click="menuModalOpen = false"
                                ><Icon name="close" width="22" height="22" />
                            </AdminButton>
                        </div>
                    </div>

                    <div class="admin-modal__body">
                        <p v-if="loading" class="muted">
                            Загрузка структуры...
                        </p>
                        <p v-else-if="treePages.length === 0" class="muted">
                            Для структуры меню пока нет страниц.
                        </p>

                        <div v-else class="page-tree-shell">
                            <PageMenuTreeItem
                                :nodes="menuTree"
                                :level="0"
                                :ancestor-ids="[]"
                                :dragging-id="draggingPageId"
                                :status-labels="statusLabels"
                                @move="handleTreeMove"
                                @dragging-change="draggingPageId = $event"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminPage>
    <AdminModal
        :open="deleteModalOpen"
        title="Удаление страницы"
        @close="closeDeleteModal"
    >
        <p>
            Переместить страницу
            <strong> "{{ pageToDelete?.title }}" </strong>
            в корзину?
        </p>

        <template #footer>
            <div class="admin-actions-row">
                <AdminButton
                    type="button"
                    variant="secondary"
                    @click="closeDeleteModal"
                >
                    Отмена
                </AdminButton>

                <AdminButton
                    type="button"
                    variant="danger"
                    @click="confirmRemovePage"
                >
                    Удалить
                </AdminButton>
            </div>
        </template>
    </AdminModal>
</template>
