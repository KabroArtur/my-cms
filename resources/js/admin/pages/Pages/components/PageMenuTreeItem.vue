<script setup>
import { inject, provide, reactive } from 'vue'
import { RouterLink } from 'vue-router'

const dropStateKey = Symbol('page-menu-drop-state')

defineOptions({
    name: 'PageMenuTreeItem',
})

const props = defineProps({
    nodes: {
        type: Array,
        required: true,
    },
    level: {
        type: Number,
        default: 0,
    },
    parentId: {
        type: [Number, null],
        default: null,
    },
    ancestorIds: {
        type: Array,
        default: () => [],
    },
    draggingId: {
        type: [Number, null],
        default: null,
    },
    statusLabels: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['move', 'dragging-change'])

const inheritedDropState = inject(dropStateKey, null)
const dropState = inheritedDropState ?? reactive({
    activeDropKey: '',
    activeDropLevel: 0,
    activeInsideId: null,
    clearTimer: null,
})

if (!inheritedDropState) {
    provide(dropStateKey, dropState)
}

function resolvePath(page) {
    return page.public_url ?? (page.is_home ? '/' : `/${page.path || page.slug}`)
}

function createDragPreview(sourceElement, pointerX, pointerY) {
    const previewElement = sourceElement.cloneNode(true)
    const bounds = sourceElement.getBoundingClientRect()

    previewElement.style.position = 'fixed'
    previewElement.style.top = '-10000px'
    previewElement.style.left = '-10000px'
    previewElement.style.width = `${bounds.width}px`
    previewElement.style.pointerEvents = 'none'
    previewElement.style.margin = '0'
    previewElement.style.opacity = '1'
    previewElement.style.transform = 'none'
    previewElement.classList.remove('is-dragging-source')

    document.body.append(previewElement)

    return {
        previewElement,
        offsetX: Math.max(0, Math.min(bounds.width - 1, pointerX - bounds.left)),
        offsetY: Math.max(0, Math.min(bounds.height - 1, pointerY - bounds.top)),
    }
}

function handleDragStart(event, nodeId) {
    const sourceElement = event.currentTarget
    const previewSource = sourceElement instanceof HTMLElement
        ? sourceElement.closest('.page-tree-item') ?? sourceElement
        : sourceElement

    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(nodeId))

    if (previewSource instanceof HTMLElement && event.dataTransfer?.setDragImage) {
        const { previewElement, offsetX, offsetY } = createDragPreview(previewSource, event.clientX, event.clientY)

        event.dataTransfer.setDragImage(previewElement, offsetX, offsetY)
        window.setTimeout(() => previewElement.remove(), 0)
    }

    emit('dragging-change', nodeId)
}

function cancelPendingClear() {
    if (dropState.clearTimer !== null) {
        window.clearTimeout(dropState.clearTimer)
        dropState.clearTimer = null
    }
}

function resetDropIndicators() {
    cancelPendingClear()
    dropState.activeDropKey = ''
    dropState.activeDropLevel = 0
    dropState.activeInsideId = null
}

function scheduleDropIndicatorClear() {
    cancelPendingClear()
    dropState.clearTimer = window.setTimeout(() => {
        dropState.activeDropKey = ''
        dropState.activeDropLevel = 0
        dropState.activeInsideId = null
        dropState.clearTimer = null
    }, 120)
}

function setActiveSlot(dropKey, level) {
    cancelPendingClear()
    dropState.activeDropKey = dropKey
    dropState.activeDropLevel = level
    dropState.activeInsideId = null
}

function setActiveInside(nodeId) {
    cancelPendingClear()
    dropState.activeDropKey = ''
    dropState.activeDropLevel = props.level
    dropState.activeInsideId = nodeId
}

function handleDragEnd() {
    resetDropIndicators()
    emit('dragging-change', null)
}

function resolveDraggedId(event) {
    const rawValue = Number(event.dataTransfer?.getData('text/plain') ?? 0)

    return Number.isNaN(rawValue) || rawValue === 0 ? null : rawValue
}

function handleDrop(event, targetIndex) {
    const draggedId = resolveDraggedId(event)

    resetDropIndicators()

    if (draggedId === null) {
        return
    }

    emit('move', {
        draggedId,
        targetParentId: props.parentId,
        targetIndex,
    })
}

function resolveNodeDropPosition(event) {
    const bounds = event.currentTarget.getBoundingClientRect()
    const offsetY = event.clientY - bounds.top
    const ratio = offsetY / bounds.height

    if (ratio < 0.28) {
        return 'before'
    }

    if (ratio > 0.72) {
        return 'after'
    }

    return 'inside'
}

function resolveSlotKey(targetIndex) {
    return `slot-${props.parentId ?? 'root'}-${targetIndex}`
}

function handleNodeDragOver(event, node, index) {
    event.preventDefault()
    const position = resolveNodeDropPosition(event)

    if (position === 'before') {
        setActiveSlot(resolveSlotKey(index), props.level)

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move'
        }

        return
    }

    if (position === 'inside') {
        setActiveInside(node.id)

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move'
        }

        return
    }

    setActiveSlot(resolveSlotKey(index + 1), props.level)

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move'
    }
}

function handleSlotDragOver(event, dropKey, targetIndex) {
    event.preventDefault()
    setActiveSlot(dropKey, props.level)

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move'
    }
}

function handleNodeDrop(event, node, index) {
    const draggedId = resolveDraggedId(event)
    const position = resolveNodeDropPosition(event)

    resetDropIndicators()

    if (draggedId === null) {
        return
    }

    if (position === 'inside') {
        emit('move', {
            draggedId,
            targetParentId: node.id,
            targetIndex: node.children?.length ?? 0,
        })

        return
    }

    if (position === 'before') {
        emit('move', {
            draggedId,
            targetParentId: props.parentId,
            targetIndex: index,
        })

        return
    }

    emit('move', {
        draggedId,
        targetParentId: props.parentId,
        targetIndex: index + 1,
    })
}

function handleDragLeave(event, dropKey) {
    if (event.currentTarget instanceof HTMLElement && event.relatedTarget instanceof Node && event.currentTarget.contains(event.relatedTarget)) {
        return
    }

    if (dropState.activeDropKey === dropKey) {
        scheduleDropIndicatorClear()
    }
}

function clearNodeDrop(event) {
    if (event.currentTarget instanceof HTMLElement && event.relatedTarget instanceof Node && event.currentTarget.contains(event.relatedTarget)) {
        return
    }

    scheduleDropIndicatorClear()
}
</script>

<template>
    <div class="page-tree-branch">
        <div
            class="page-tree-level-dropzone"
            :class="{ 'is-active': dropState.activeDropKey === `slot-${parentId ?? 'root'}-0`, 'is-dragging': draggingId !== null }"
            :style="{ '--drop-level': dropState.activeDropKey === `slot-${parentId ?? 'root'}-0` ? dropState.activeDropLevel : level }"
            @dragover="handleSlotDragOver($event, `slot-${parentId ?? 'root'}-0`, 0)"
            @dragleave="handleDragLeave($event, `slot-${parentId ?? 'root'}-0`)"
            @drop="handleDrop($event, 0)"
        ></div>

        <template v-for="(node, index) in nodes" :key="node.id">
            <article
                class="page-tree-item"
                :class="{
                    'is-dragging-source': draggingId === node.id,
                    'is-drop-inside': dropState.activeInsideId === node.id
                }"
                @dragover="handleNodeDragOver($event, node, index)"
                @dragleave="clearNodeDrop($event)"
                @drop="handleNodeDrop($event, node, index)"
            >
                <div class="page-tree-item__main">
                    <span
                        class="page-tree-item__handle"
                        draggable="true"
                        @dragstart="handleDragStart($event, node.id)"
                        @dragend="handleDragEnd"
                    >
                        ::
                    </span>

                    <div>
                        <div class="page-tree-item__title-row">
                            <RouterLink :to="{ name: 'page-edit', params: { id: node.id } }" class="page-title-link">
                                {{ node.title }}
                            </RouterLink>

                            <span :class="['page-badge', `page-badge--${node.status}`]">
                                {{ statusLabels[node.status] ?? node.status }}
                            </span>
                        </div>

                        <p class="page-tree-item__path">{{ resolvePath(node) }}</p>
                    </div>
                </div>

                <div class="admin-actions-row">
                    <a :href="resolvePath(node)" class="button-link" target="_blank" rel="noopener">
                        Перейти
                    </a>
                </div>
            </article>

            <div class="page-tree-item__children">
                <PageMenuTreeItem
                    :nodes="node.children ?? []"
                    :level="level + 1"
                    :parent-id="node.id"
                    :ancestor-ids="[...ancestorIds, node.id]"
                    :dragging-id="draggingId"
                    :status-labels="statusLabels"
                    @move="emit('move', $event)"
                    @dragging-change="emit('dragging-change', $event)"
                />
            </div>

            <div
                class="page-tree-level-dropzone"
                :class="{ 'is-active': dropState.activeDropKey === `slot-${parentId ?? 'root'}-${index + 1}`, 'is-dragging': draggingId !== null }"
                :style="{ '--drop-level': dropState.activeDropKey === `slot-${parentId ?? 'root'}-${index + 1}` ? dropState.activeDropLevel : level }"
                @dragover="handleSlotDragOver($event, `slot-${parentId ?? 'root'}-${index + 1}`, index + 1)"
                @dragleave="handleDragLeave($event, `slot-${parentId ?? 'root'}-${index + 1}`)"
                @drop="handleDrop($event, index + 1)"
            ></div>
        </template>
    </div>
</template>