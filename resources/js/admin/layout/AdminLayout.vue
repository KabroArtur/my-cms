<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { fetchCurrentUser, logout } from '../api/auth'
import { getAdminThemeState, loadCmsSettings, toggleAdminThemeMode } from '../composables/useCmsSettings'
import { adminBasePath, adminLoginPath, adminPath } from '../utils/adminPath'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const user = ref(null)
const errorMessage = ref('')
const siteName = ref('My CMS')
const sidebarOpen = ref(false)
const themeMode = ref(getAdminThemeState().mode)
const openPluginGroups = ref(new Set())
const openPluginSubgroups = ref(new Set())

const displayName = computed(() => {
    const name = String(user.value?.name ?? '').trim()

    if (name !== '') {
        return name
    }

    return String(user.value?.username ?? 'Пользователь')
})

const userAccessLabel = computed(() => {
    const firstRole = String(user.value?.roles?.[0] ?? '').trim()

    if (firstRole === '') {
        return 'Пользователь'
    }

    if (firstRole === 'admin') {
        return 'Админ'
    }

    return firstRole
})

const links = computed(() => {
    const permissions = new Set(user.value?.permissions ?? [])

    return [
        { to: adminBasePath(), label: 'Панель', visible: true, matchNames: ['dashboard'] },
        { to: adminPath('pages'), label: 'Страницы', visible: permissions.has('pages.access'), matchNames: ['pages', 'page-create', 'page-edit'] },
        { to: adminPath('users'), label: 'Пользователи', visible: permissions.has('users.access'), matchNames: ['users', 'roles'] },
        { to: adminPath('media'), label: 'Медиатека', visible: permissions.has('media.access'), matchNames: ['media'] },
        { to: adminPath('settings'), label: 'Настройки', visible: permissions.has('settings.access'), matchNames: ['settings', 'content-structure'] },
        { to: adminPath('plugins'), label: 'Плагины', visible: permissions.has('plugins.access'), matchNames: ['plugins'] },
    ].filter((link) => link.visible)
})

const pluginGroups = computed(() => {
    const pluginMenu = Array.isArray(user.value?.plugin_menu) ? user.value.plugin_menu : []
    const grouped = new Map()

    pluginMenu.forEach((item) => {
        const group = String(item.group ?? '').trim()

        if (group === '') {
            return
        }

        if (!grouped.has(group)) {
            grouped.set(group, {
                directItems: [],
                subgroupMap: new Map(),
            })
        }

        const groupState = grouped.get(group)
        const mappedItem = {
            to: item.path,
            label: item.title,
            external: !String(item.path || '').startsWith(adminBasePath() + '/'),
        }

        const subgroup = String(item.subgroup ?? '').trim()

        if (subgroup === '') {
            groupState.directItems.push(mappedItem)
            return
        }

        if (!groupState.subgroupMap.has(subgroup)) {
            groupState.subgroupMap.set(subgroup, [])
        }

        groupState.subgroupMap.get(subgroup).push(mappedItem)
    })

    return Array.from(grouped.entries()).map(([label, state]) => ({
        label,
        directItems: state.directItems,
        subgroups: Array.from(state.subgroupMap.entries()).map(([subLabel, items]) => ({
            label: subLabel,
            items,
        })),
    }))
})

const standalonePluginLinks = computed(() => {
    const pluginMenu = Array.isArray(user.value?.plugin_menu) ? user.value.plugin_menu : []

    return pluginMenu
        .filter((item) => String(item.group ?? '').trim() === '')
        .map((item) => ({
            to: item.path,
            label: item.title,
            external: !String(item.path || '').startsWith(adminBasePath() + '/'),
        }))
})

const routeLabels = {
    dashboard: 'Панель',
    pages: 'Страницы',
    'page-create': 'Создать страницу',
    'page-edit': 'Редактирование страницы',
    users: 'Пользователи',
    roles: 'Роли и доступы',
    media: 'Медиатека',
    settings: 'Настройки',
    'content-structure': 'Структура контента',
    plugins: 'Плагины',
    'records-sections': 'Записи: разделы',
    'records-posts': 'Записи',
    'records-categories': 'Категории',
    'records-tags': 'Теги',
    'records-settings': 'Настройки',
    'blog-posts': 'Blog: Записи',
    'blog-categories': 'Blog: Категории',
    'blog-tags': 'Blog: Теги',
    'admin-not-found': 'Страница не найдена',
}

const breadcrumbs = computed(() => {
    const currentName = String(route.name ?? '')
    const items = [{ name: 'dashboard', label: routeLabels.dashboard }]

    if (currentName === '' || currentName === 'dashboard') {
        return items
    }

    if ((currentName === 'page-create' || currentName === 'page-edit') && !items.some((item) => item.name === 'pages')) {
        items.push({ name: 'pages', label: routeLabels.pages })
    }

    if (currentName === 'content-structure' && !items.some((item) => item.name === 'settings')) {
        items.push({ name: 'settings', label: routeLabels.settings })
    }

    if (currentName === 'roles' && !items.some((item) => item.name === 'users')) {
        items.push({ name: 'users', label: routeLabels.users })
    }

    items.push({ name: currentName, label: routeLabels[currentName] ?? 'Раздел' })

    return items
})

const themeToggleLabel = computed(() => (themeMode.value === 'dark' ? 'Светлая тема' : 'Темная тема'))

function isLinkActive(link) {
    const currentName = String(route.name ?? '')

    if ((link.matchNames ?? []).includes(currentName)) {
        return true
    }

    if (typeof link.to === 'string' && link.to.startsWith(adminBasePath() + '/')) {
        return String(route.path ?? '').startsWith(link.to)
    }

    return false
}

function togglePluginGroup(groupLabel) {
    if (openPluginGroups.value.has(groupLabel)) {
        openPluginGroups.value.delete(groupLabel)
        return
    }

    openPluginGroups.value.add(groupLabel)
}

function isPluginGroupOpen(groupLabel) {
    return openPluginGroups.value.has(groupLabel)
}

function isPluginGroupActive(group) {
    const directActive = group.directItems.some((item) => {
        if (item.external) {
            return false
        }

        return String(route.path ?? '').startsWith(String(item.to ?? ''))
    })

    if (directActive) {
        return true
    }

    return group.subgroups.some((subgroup) => subgroup.items.some((item) => {
        if (item.external) {
            return false
        }

        return String(route.path ?? '').startsWith(String(item.to ?? ''))
    }))
}

function subgroupKey(groupLabel, subgroupLabel) {
    return `${groupLabel}::${subgroupLabel}`
}

function togglePluginSubgroup(groupLabel, subgroupLabel) {
    const key = subgroupKey(groupLabel, subgroupLabel)

    if (openPluginSubgroups.value.has(key)) {
        openPluginSubgroups.value.delete(key)
        return
    }

    openPluginSubgroups.value.add(key)
}

function isPluginSubgroupOpen(groupLabel, subgroupLabel) {
    return openPluginSubgroups.value.has(subgroupKey(groupLabel, subgroupLabel))
}

function isPluginSubgroupActive(groupLabel, subgroup) {
    return subgroup.items.some((item) => {
        if (item.external) {
            return false
        }

        return String(route.path ?? '').startsWith(String(item.to ?? ''))
    })
}

async function loadCurrentUser() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [payload, settingsPayload] = await Promise.all([
            fetchCurrentUser(),
            loadCmsSettings(),
        ])
        user.value = payload.data
        siteName.value = settingsPayload.settings?.site_name || 'My CMS'

        const defaultOpenGroups = new Set()
        const defaultOpenSubgroups = new Set()

        pluginGroups.value.forEach((group) => {
            if (isPluginGroupActive(group)) {
                defaultOpenGroups.add(group.label)
            }

            group.subgroups.forEach((subgroup) => {
                if (isPluginSubgroupActive(group.label, subgroup)) {
                    defaultOpenGroups.add(group.label)
                    defaultOpenSubgroups.add(subgroupKey(group.label, subgroup.label))
                }
            })
        })

        openPluginGroups.value = defaultOpenGroups
        openPluginSubgroups.value = defaultOpenSubgroups
    } catch (error) {
        errorMessage.value = 'Не удалось загрузить пользователя.'
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function handleLogout() {
    try {
        await logout()
        await router.push(adminLoginPath())
        window.location.href = adminLoginPath()
    } catch (error) {
        errorMessage.value = 'Не удалось завершить сессию.'
        console.error(error)
    }
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
}

function handleThemeToggle() {
    const nextState = toggleAdminThemeMode()
    themeMode.value = nextState.mode
}

watch(() => route.fullPath, () => {
    sidebarOpen.value = false
})

onMounted(async () => {
    themeMode.value = getAdminThemeState().mode
    await loadCurrentUser()
})
</script>

<template>
    <div class="admin-shell">
        <aside class="admin-sidebar" :class="{ 'is-open': sidebarOpen }">
            <div class="admin-sidebar__brand">
                <p class="eyebrow">CMS</p>
                <h1 class="admin-title">{{ siteName }}</h1>
            </div>

            <div class="admin-sidebar__meta">
                <p v-if="loading" class="muted">Загрузка пользователя...</p>
                <p v-else-if="user" class="muted">{{ user.username }} | {{ user.roles.join(', ') }}</p>
            </div>

            <nav class="admin-nav" aria-label="Основная навигация">
                <template v-for="link in links" :key="link.to">
                    <RouterLink
                        v-if="!link.external"
                        :to="link.to"
                        class="nav-link"
                        :class="{ 'is-active': isLinkActive(link) }"
                        active-class=""
                        exact-active-class=""
                    >
                        {{ link.label }}
                    </RouterLink>

                    <a
                        v-else
                        :href="link.to"
                        class="nav-link"
                    >
                        {{ link.label }}
                    </a>
                </template>

                <div
                    v-if="pluginGroups.length > 0 || standalonePluginLinks.length > 0"
                    class="admin-nav__plugin-divider"
                >
                    <span>Плагины</span>
                </div>

                <template v-for="group in pluginGroups" :key="`plugin-group-${group.label}`">
                    <button
                        type="button"
                        class="nav-link nav-link--group"
                        :class="{ 'is-active': isPluginGroupActive(group) }"
                        @click="togglePluginGroup(group.label)"
                    >
                        <span>{{ group.label }}</span>
                        <span class="nav-link__arrow" :class="{ 'is-open': isPluginGroupOpen(group.label) }">▾</span>
                    </button>

                    <div v-if="isPluginGroupOpen(group.label)" class="admin-nav__subgroup">
                        <template v-for="item in group.directItems" :key="`${group.label}-${item.to}`">
                            <RouterLink
                                v-if="!item.external"
                                :to="item.to"
                                class="nav-link nav-link--sub"
                                :class="{ 'is-active': isLinkActive(item) }"
                                active-class=""
                                exact-active-class=""
                            >
                                {{ item.label }}
                            </RouterLink>

                            <a v-else :href="item.to" class="nav-link nav-link--sub">
                                {{ item.label }}
                            </a>
                        </template>

                        <template v-for="subgroup in group.subgroups" :key="`${group.label}-${subgroup.label}`">
                            <button
                                type="button"
                                class="nav-link nav-link--subgroup"
                                :class="{ 'is-active': isPluginSubgroupActive(group.label, subgroup) }"
                                @click="togglePluginSubgroup(group.label, subgroup.label)"
                            >
                                <span>{{ subgroup.label }}</span>
                                <span class="nav-link__arrow" :class="{ 'is-open': isPluginSubgroupOpen(group.label, subgroup.label) }">▾</span>
                            </button>

                            <div v-if="isPluginSubgroupOpen(group.label, subgroup.label)" class="admin-nav__subgroup-inner">
                                <template v-for="item in subgroup.items" :key="`${group.label}-${subgroup.label}-${item.to}`">
                                    <RouterLink
                                        v-if="!item.external"
                                        :to="item.to"
                                        class="nav-link nav-link--sub nav-link--sublevel"
                                        :class="{ 'is-active': isLinkActive(item) }"
                                        active-class=""
                                        exact-active-class=""
                                    >
                                        {{ item.label }}
                                    </RouterLink>

                                    <a v-else :href="item.to" class="nav-link nav-link--sub nav-link--sublevel">
                                        {{ item.label }}
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <template v-for="link in standalonePluginLinks" :key="`plugin-link-${link.to}`">
                    <RouterLink
                        v-if="!link.external"
                        :to="link.to"
                        class="nav-link"
                        :class="{ 'is-active': isLinkActive(link) }"
                        active-class=""
                        exact-active-class=""
                    >
                        {{ link.label }}
                    </RouterLink>

                    <a v-else :href="link.to" class="nav-link">
                        {{ link.label }}
                    </a>
                </template>
            </nav>

            <div class="admin-sidebar__footer">
                <p v-if="errorMessage" class="error-text"><strong>{{ errorMessage }}</strong></p>
                <div v-if="loading" class="muted">Загрузка сессии...</div>

                <div v-else-if="user" class="admin-sidebar__session">
                    <div class="admin-user-badge">
                        <strong>{{ displayName }}</strong>
                        <span class="muted">Доступ: {{ userAccessLabel }}</span>
                    </div>

                    <button type="button" class="button-link" @click="handleLogout">
                        Выход
                    </button>
                </div>

                <button v-else type="button" class="button-link" @click="handleLogout">
                    Выход
                </button>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="admin-sidebar-backdrop" @click="sidebarOpen = false" />

        <section class="admin-main">
            <header class="admin-topbar">
                <button type="button" class="admin-menu-button" @click="toggleSidebar">
                    Меню
                </button>
                <div class="admin-topbar__meta">
                    <p class="eyebrow">Панель управления</p>
                    <p class="muted">{{ loading ? 'Обновляем данные пользователя...' : 'Рабочая область CMS' }}</p>

                    <nav class="admin-breadcrumbs" aria-label="Хлебные крошки">
                        <template v-for="(item, index) in breadcrumbs" :key="`${item.name}-${index}`">
                            <RouterLink
                                v-if="index < breadcrumbs.length - 1"
                                :to="{ name: item.name }"
                                class="admin-breadcrumbs__link"
                            >
                                {{ item.label }}
                            </RouterLink>
                            <span v-else class="admin-breadcrumbs__current">{{ item.label }}</span>
                            <span v-if="index < breadcrumbs.length - 1" class="admin-breadcrumbs__sep">/</span>
                        </template>
                    </nav>
                </div>

                <div class="admin-topbar__actions">
                    <button type="button" class="button-link" @click="handleThemeToggle">
                        {{ themeToggleLabel }}
                    </button>
                </div>
            </header>

            <main class="admin-content">
                <router-view />
            </main>
        </section>
    </div>
</template>

<style scoped>
/* ---- Plugin section divider ---- */
.admin-nav__plugin-divider {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 4px 0;
}

.admin-nav__plugin-divider::before,
.admin-nav__plugin-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--admin-color-border);
}

.admin-nav__plugin-divider span {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--admin-color-text-muted);
    white-space: nowrap;
    opacity: 0.7;
}

/* ---- Plugin group toggle (top level) ---- */
.nav-link--group {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    text-align: left;
}

.nav-link--group.is-active {
    background: var(--admin-color-primary);
    color: var(--admin-color-primary-contrast);
    border-color: var(--admin-color-primary);
}

.nav-link__arrow {
    transition: transform 0.2s ease;
    font-size: 11px;
    opacity: 0.6;
}

.nav-link__arrow.is-open {
    transform: rotate(180deg);
}

/* ---- Items container under group ---- */
.admin-nav__subgroup {
    display: grid;
    gap: 1px;
    margin-top: 2px;
    padding-left: 10px;
    border-left: 2px solid var(--admin-color-border);
    margin-left: 4px;
}

/* ---- Direct sub-items ---- */
.nav-link--sub {
    padding: 7px 10px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid transparent;
    background: transparent;
    color: var(--admin-color-text-muted);
    border-radius: var(--admin-radius-sm);
}

.nav-link--sub:hover {
    background: color-mix(in srgb, var(--admin-color-primary) 7%, var(--admin-color-surface));
    color: var(--admin-color-text);
    transform: none;
}

.nav-link--sub.is-active {
    background: color-mix(in srgb, var(--admin-color-primary) 12%, var(--admin-color-surface));
    border-color: color-mix(in srgb, var(--admin-color-primary) 28%, transparent);
    color: var(--admin-color-primary);
}

/* ---- Subgroup toggle (section name, e.g. «Blog») ---- */
.nav-link--subgroup {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    text-align: left;
    padding: 7px 10px;
    font-size: 13px;
    font-weight: 600;
    color: var(--admin-color-text);
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--admin-radius-sm);
}

.nav-link--subgroup:hover {
    background: color-mix(in srgb, var(--admin-color-primary) 7%, var(--admin-color-surface));
}

.nav-link--subgroup.is-active {
    color: var(--admin-color-primary);
}

/* ---- Deep items under subgroup (Записи, Категории, Теги…) ---- */
.admin-nav__subgroup-inner {
    display: grid;
    gap: 1px;
    padding-left: 8px;
    border-left: 2px solid color-mix(in srgb, var(--admin-color-primary) 30%, transparent);
    margin-left: 6px;
    margin-bottom: 4px;
}

.nav-link--sublevel {
    padding: 5px 8px;
    font-size: 12px;
    font-weight: 500;
    color: var(--admin-color-text-muted);
}

.nav-link--sublevel:hover {
    color: var(--admin-color-text);
}

.nav-link--sublevel.is-active {
    color: var(--admin-color-primary);
    font-weight: 600;
}
</style>