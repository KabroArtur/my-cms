<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminButton from '../../components/ui/AdminButton.vue'
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
import PageMenuTreeItem from './components/PageMenuTreeItem.vue'
import { deletePage, fetchPages, fetchPageTree, fetchTrashedPages, permanentlyDeletePage, restorePage, savePageTree } from '../../api/pages'

const activeTab = ref('list')
const loading = ref(true)
const savingTree = ref(false)
const errorMessage = ref('')
const pages = ref([])
const treePages = ref([])
const trashedPages = ref([])
const draggingPageId = ref(null)
const treeDirty = ref(false)

const statusLabels = {
    draft: 'Черновик',
    pending_review: 'На проверке',
    scheduled: 'Запланирована',
    published: 'Опубликована',
    archived: 'Архив',
}

const visibilityLabels = {
    public: 'Публичная',
    private: 'Скрытая',
}

function formatDateTime(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value))
}

function resolvePublicUrl(page) {
    return page.public_url ?? (page.is_home ? '/' : `/${page.path || page.slug}`)
}

function resolveStatusLabel(status) {
    return statusLabels[status] ?? status
}

function resolveVisibilityLabel(visibility) {
    return visibilityLabels[visibility] ?? visibility
}

const menuTree = computed(() => buildTree(treePages.value))
const orderedPages = computed(() => {
    if (treePages.value.length === 0) {
        return pages.value
    }

    const order = flattenTree(menuTree.value).map((node) => node.id)
    const pageMap = new Map(pages.value.map((page) => [page.id, page]))

    return order
        .map((id) => pageMap.get(id))
        .filter(Boolean)
})

function buildTree(items) {
    const nodes = items
        .map((item) => ({ ...item, children: [] }))
        .sort((left, right) => {
            if (left.sort_order !== right.sort_order) {
                return left.sort_order - right.sort_order
            }

            return String(left.title).localeCompare(String(right.title), 'ru')
        })

    const byId = new Map(nodes.map((node) => [node.id, node]))
    const roots = []

    for (const node of nodes) {
        if (node.parent_id && byId.has(node.parent_id)) {
            byId.get(node.parent_id).children.push(node)
            continue
        }

        roots.push(node)
    }

    return roots
}

function flattenTree(nodes, parentId = null, bucket = []) {
    nodes.forEach((node, index) => {
        bucket.push({ id: node.id, parent_id: parentId, sort_order: index })
        flattenTree(node.children ?? [], node.id, bucket)
    })

    return bucket
}

function containsNode(node, searchedId) {
    if (node.id === searchedId) {
        return true
    }

    return (node.children ?? []).some((child) => containsNode(child, searchedId))
}

function removeNode(nodes, draggedId) {
    for (let index = 0; index < nodes.length; index += 1) {
        const currentNode = nodes[index]

        if (currentNode.id === draggedId) {
            return nodes.splice(index, 1)[0]
        }

        const removedChild = removeNode(currentNode.children ?? [], draggedId)

        if (removedChild) {
            return removedChild
        }
    }

    return null
}

function findChildrenContainer(nodes, parentId) {
    if (parentId === null) {
        return nodes
    }

    for (const node of nodes) {
        if (node.id === parentId) {
            node.children = node.children ?? []

            return node.children
        }

        const nestedContainer = findChildrenContainer(node.children ?? [], parentId)

        if (nestedContainer) {
            return nestedContainer
        }
    }

    return null
}

function rebuildTreePagesFromNodes(nodes) {
    const pageMap = new Map(treePages.value.map((page) => [page.id, page]))

    treePages.value = flattenTree(nodes).map((node) => ({
        ...pageMap.get(node.id),
        parent_id: node.parent_id,
        sort_order: node.sort_order,
    }))
}

function findNodeIndex(container, nodeId) {
    return container.findIndex((node) => node.id === nodeId)
}

function handleTreeMove({ draggedId, targetParentId, targetIndex, anchorNodeId, position }) {
    if (!draggedId) {
        return
    }

    const nodes = buildTree(treePages.value)
    const draggedNode = removeNode(nodes, draggedId)

    if (!draggedNode) {
        return
    }

    if (targetParentId !== null && containsNode(draggedNode, targetParentId)) {
        draggingPageId.value = null

        return
    }

    const targetContainer = findChildrenContainer(nodes, targetParentId) ?? nodes
    const resolvedIndex = anchorNodeId
        ? (() => {
            const anchorIndex = findNodeIndex(targetContainer, anchorNodeId)

            if (anchorIndex === -1) {
                return targetContainer.length
            }

            return position === 'before' ? anchorIndex : anchorIndex + 1
        })()
        : targetIndex

    const safeIndex = Math.max(0, Math.min(resolvedIndex, targetContainer.length))

    targetContainer.splice(safeIndex, 0, draggedNode)

    rebuildTreePagesFromNodes(nodes)
    treeDirty.value = true
    draggingPageId.value = null
}

async function handleTreeSave() {
    savingTree.value = true
    errorMessage.value = ''

    try {
        await savePageTree(flattenTree(menuTree.value))
        treeDirty.value = false
        await loadPages()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Не удалось сохранить структуру меню.'
        console.error(error)
    } finally {
        savingTree.value = false
    }
}

async function loadPages() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [payload, treePayload, trashedPayload] = await Promise.all([
            fetchPages(),
            fetchPageTree(),
            fetchTrashedPages(),
        ])

        pages.value = payload.data ?? []
        treePages.value = treePayload.data ?? []
        trashedPages.value = trashedPayload.data ?? []
        treeDirty.value = false
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить страницы.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function removePage(page) {
    const confirmed = window.confirm(`Переместить страницу "${page.title}" в корзину?`)

    if (!confirmed) {
        return
    }

    errorMessage.value = ''

    try {
        await deletePage(page.id)
        pages.value = pages.value.filter((item) => item.id !== page.id)
        treePages.value = treePages.value.filter((item) => item.id !== page.id)
        trashedPages.value.unshift({
            ...page,
            deleted_at: new Date().toISOString(),
        })
    } catch (error) {
        errorMessage.value = 'Не удалось переместить страницу в корзину.'
        console.error(error)
    }
}

async function restoreTrashedPage(page) {
    errorMessage.value = ''

    try {
        const payload = await restorePage(page.id)
        trashedPages.value = trashedPages.value.filter((item) => item.id !== page.id)
        pages.value.unshift(payload.data)
        treePages.value.unshift(payload.data)
    } catch (error) {
        errorMessage.value = 'Не удалось восстановить страницу.'
        console.error(error)
    }
}

async function forceRemovePage(page) {
    const confirmed = window.confirm(`Удалить страницу "${page.title}" навсегда?`)

    if (!confirmed) {
        return
    }

    errorMessage.value = ''

    try {
        await permanentlyDeletePage(page.id)
        trashedPages.value = trashedPages.value.filter((item) => item.id !== page.id)
    } catch (error) {
        errorMessage.value = 'Не удалось удалить страницу навсегда.'
        console.error(error)
    }
}

onMounted(loadPages)
</script>

<template>
    <AdminPage
        eyebrow="Pages"
        title="Страницы"
        description="Список страниц с переходом в отдельную карточку редактирования."
    >
        <template #actions>
            <div class="admin-actions-row">
                <div class="admin-tabs admin-tabs--subtle" role="tablist" aria-label="Режимы работы со страницами">
                    <button type="button" class="admin-tab" :class="{ 'is-active': activeTab === 'list' }" @click="activeTab = 'list'">
                        Список
                    </button>
                    <button type="button" class="admin-tab admin-tab--ghost" :class="{ 'is-active': activeTab === 'menu' }" @click="activeTab = 'menu'">
                        Меню
                    </button>
                </div>

                <RouterLink to="/admin/pages/create" class="button-link">
                    Новая страница
                </RouterLink>
            </div>
        </template>

        <div class="admin-page-grid">
            <AdminCard v-if="activeTab === 'list'">
                <p v-if="loading" class="muted">Загрузка страниц...</p>
                <p v-else-if="orderedPages.length === 0" class="muted">Страницы пока не созданы.</p>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Description</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Visibility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="page in orderedPages" :key="page.id">
                            <td>{{ page.id }}</td>
                            <td>
                                <RouterLink :to="`/admin/pages/${page.id}`" class="page-title-link">
                                    {{ page.title }}
                                </RouterLink>
                            </td>
                            <td>
                                <span v-if="page.is_home">/</span>
                                <span v-else>/{{ page.path || page.slug }}</span>
                            </td>
                            <td>{{ page.excerpt || '—' }}</td>
                            <td>{{ page.slug }}</td>
                            <td>
                                <span :class="['page-badge', `page-badge--${page.status}`]">
                                    {{ resolveStatusLabel(page.status) }}
                                </span>
                            </td>
                            <td>{{ formatDateTime(page.published_at) }}</td>
                            <td>
                                <span class="page-badge page-badge--visibility">
                                    {{ resolveVisibilityLabel(page.visibility) }}
                                </span>
                            </td>
                            <td>
                                <div class="admin-actions-row">
                                    <a :href="resolvePublicUrl(page)" class="button-link" target="_blank" rel="noopener">
                                        Перейти
                                    </a>

                                    <RouterLink :to="`/admin/pages/${page.id}`" class="button-link">
                                        Открыть
                                    </RouterLink>

                                    <AdminButton type="button" variant="danger" @click="removePage(page)">
                                        В корзину
                                    </AdminButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AdminCard>

            <AdminCard v-if="activeTab === 'list'">
                <h2>Корзина</h2>
                <p v-if="trashedPages.length === 0" class="muted">Удалённых страниц пока нет.</p>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Удалена</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="page in trashedPages" :key="page.id">
                            <td>{{ page.id }}</td>
                            <td>{{ page.title }}</td>
                            <td>{{ page.slug }}</td>
                            <td>{{ formatDateTime(page.deleted_at) }}</td>
                            <td>
                                <div class="admin-actions-row">
                                    <AdminButton type="button" @click="restoreTrashedPage(page)">
                                        Восстановить
                                    </AdminButton>

                                    <AdminButton type="button" variant="danger" @click="forceRemovePage(page)">
                                        Удалить навсегда
                                    </AdminButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </AdminCard>

            <AdminCard v-if="activeTab === 'menu'">
                <div class="panel-header">
                    <div>
                        <h2>Скрытое меню</h2>
                        <p class="muted">Бета-режим без плагинов: перетаскивай страницы мышкой, чтобы менять вложенность и порядок. Это уже готовит основу под будущую модалку меню.</p>
                    </div>

                    <div class="admin-actions-row">
                        <AdminButton type="button" :disabled="savingTree || !treeDirty" @click="handleTreeSave">
                            {{ savingTree ? 'Сохранение...' : 'Сохранить структуру' }}
                        </AdminButton>
                    </div>
                </div>

                <p v-if="loading" class="muted">Загрузка структуры...</p>
                <p v-else-if="treePages.length === 0" class="muted">Для структуры меню пока нет страниц.</p>

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
            </AdminCard>
        </div>
    </AdminPage>
</template>