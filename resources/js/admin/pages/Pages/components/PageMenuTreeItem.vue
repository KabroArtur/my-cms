<script setup>
import { inject, provide, reactive } from 'vue'
import { RouterLink } from 'vue-router'

const OUTDENT_THRESHOLD = 24
const INDENT_STEP = 28
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

    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(nodeId))

    if (sourceElement instanceof HTMLElement && event.dataTransfer?.setDragImage) {
        const { previewElement, offsetX, offsetY } = createDragPreview(sourceElement, event.clientX, event.clientY)

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
}

function scheduleDropIndicatorClear() {
    cancelPendingClear()
    dropState.clearTimer = window.setTimeout(() => {
        dropState.activeDropKey = ''
        dropState.activeDropLevel = 0
        dropState.clearTimer = null
    }, 40)
}

function setActiveSlot(dropKey, level) {
    cancelPendingClear()
    dropState.activeDropKey = dropKey
    dropState.activeDropLevel = level
}

function handleDragEnd() {
    resetDropIndicators()
    emit('dragging-change', null)
}

function resolveDraggedId(event) {
    const rawValue = Number(event.dataTransfer?.getData('text/plain') ?? 0)

    return Number.isNaN(rawValue) || rawValue === 0 ? null : rawValue
}

function findPreviousSibling(targetIndex) {
    for (let index = targetIndex - 1; index >= 0; index -= 1) {
        const sibling = props.nodes[index]

        if (sibling?.id !== props.draggingId) {
            return sibling
        }
    }

    return null
}

function handleDrop(event, targetIndex) {
    const instruction = resolveSlotDropInstruction(event, targetIndex)

    resetDropIndicators()

    if (instruction.payload.draggedId === null) {
        return
    }

    emit('move', instruction.payload)
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

function resolveSlotDropInstruction(event, targetIndex) {
    const draggedId = resolveDraggedId(event)
    const desiredLevel = resolveDesiredLevel(event)

    if (desiredLevel < props.level) {
        return {
            level: desiredLevel,
            payload: {
                draggedId,
                targetParentId: desiredLevel === 0 ? null : props.ancestorIds[desiredLevel - 1],
                anchorNodeId: props.ancestorIds[desiredLevel] ?? props.parentId,
                position: 'after',
            },
        }
    }

    return {
        level: props.level,
        payload: {
            draggedId,
            targetParentId: props.parentId,
            targetIndex,
        },
    }
}

function resolveDesiredLevel(event) {
    const bounds = event.currentTarget.getBoundingClientRect()
    const rootLeft = bounds.left - (props.level * INDENT_STEP)
    const absoluteOffset = event.clientX - rootLeft
    const snappedLevel = Math.floor((absoluteOffset + (INDENT_STEP / 2) - OUTDENT_THRESHOLD) / INDENT_STEP)

    return Math.max(0, Math.min(props.level + 1, snappedLevel))
}

function resolveNodeDropInstruction(event, node) {
    const draggedId = resolveDraggedId(event)
    const desiredLevel = resolveDesiredLevel(event)

    if (desiredLevel > props.level) {
        return {
            key: resolveSlotKey(props.nodes.findIndex((entry) => entry.id === node.id) + 1),
            level: props.level + 1,
            payload: {
                draggedId,
                targetParentId: node.id,
                targetIndex: node.children?.length ?? 0,
            },
        }
    }

    return null
}

function resolveSlotKey(targetIndex) {
    return `slot-${props.parentId ?? 'root'}-${targetIndex}`
}

function handleNodeDragOver(event, node, index) {
    event.preventDefault()
    const instruction = resolveNodeDropInstruction(event, node)
    const position = resolveNodeDropPosition(event)
    const desiredLevel = resolveDesiredLevel(event)

    if (desiredLevel > props.level) {
        setActiveSlot(resolveSlotKey(index + 1), instruction.level)

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move'
        }

        return
    }

    if (position === 'before') {
        setActiveSlot(resolveSlotKey(index), Math.min(desiredLevel, props.level))

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move'
        }

        return
    }

    setActiveSlot(resolveSlotKey(index + 1), Math.min(desiredLevel, props.level))

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move'
    }
}

function handleSlotDragOver(event, dropKey, targetIndex) {
    event.preventDefault()

    const instruction = resolveSlotDropInstruction(event, targetIndex)

    setActiveSlot(dropKey, instruction.level)

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move'
    }
}

function handleNodeDrop(event, node, index) {
    const draggedId = resolveDraggedId(event)
    const position = resolveNodeDropPosition(event)
    const desiredLevel = resolveDesiredLevel(event)

    resetDropIndicators()

    if (draggedId === null) {
        return
    }

    if (desiredLevel > props.level) {
        emit('move', resolveNodeDropInstruction(event, node).payload)

        return
    }

    if (position === 'before') {
        emit('move', resolveSlotDropInstruction(event, index).payload)

        return
    }

    emit('move', resolveSlotDropInstruction(event, index + 1).payload)
}

function handleDragLeave(dropKey) {
    if (dropState.activeDropKey === dropKey) {
        scheduleDropIndicatorClear()
    }
}

function clearNodeDrop() {
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
            @dragleave="handleDragLeave(`slot-${parentId ?? 'root'}-0`)"
            @drop="handleDrop($event, 0)"
        ></div>

        <template v-for="(node, index) in nodes" :key="node.id">
            <article
                class="page-tree-item"
                :class="{
                    'is-dragging-source': draggingId === node.id
                }"
                draggable="true"
                @dragstart="handleDragStart($event, node.id)"
                @dragend="handleDragEnd"
                @dragover="handleNodeDragOver($event, node, index)"
                @dragleave="clearNodeDrop"
                @drop="handleNodeDrop($event, node, index)"
            >
                <div class="page-tree-item__main">
                    <span class="page-tree-item__handle">::</span>

                    <div>
                        <div class="page-tree-item__title-row">
                            <RouterLink :to="`/admin/pages/${node.id}`" class="page-title-link">
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
                @dragleave="handleDragLeave(`slot-${parentId ?? 'root'}-${index + 1}`)"
                @drop="handleDrop($event, index + 1)"
            ></div>
        </template>
    </div>
</template>