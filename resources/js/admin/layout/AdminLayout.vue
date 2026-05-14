<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import {
    fetchCurrentUser,
    getBootstrappedSiteName,
    getCurrentUserSnapshot,
    logout,
} from "../api/auth";
import AdminNotifications from "../components/ui/AdminNotifications.vue";
import {
    getAdminThemeState,
    loadCmsSettings,
    toggleAdminThemeMode,
} from "../composables/useCmsSettings";
import { adminBasePath, adminLoginPath, adminPath } from "../utils/adminPath";
import Icon from "../components/ui/Icon.vue";

const route = useRoute();
const router = useRouter();
const initialUser = getCurrentUserSnapshot();
const loading = ref(!initialUser);
const user = ref(initialUser);
const errorMessage = ref("");
const siteName = ref(getBootstrappedSiteName());
const sidebarOpen = ref(false);
const themeMode = ref(getAdminThemeState().mode);
const openPluginGroups = ref(new Set());
const openPluginSubgroups = ref(new Set());
const userMenuOpen = ref(false);

const displayName = computed(() => {
    const name = String(user.value?.name ?? "").trim();

    if (name !== "") {
        return name;
    }

    return String(user.value?.username ?? "Пользователь");
});

const userAccessLabel = computed(() => {
    const firstRole = String(user.value?.roles?.[0] ?? "").trim();

    if (firstRole === "") {
        return "Пользователь";
    }

    if (firstRole === "admin") {
        return "Админ";
    }

    return firstRole;
});

const links = computed(() => {
    const permissions = new Set(user.value?.permissions ?? []);

    return [
        {
            to: adminBasePath(),
            label: "Панель",
            visible: true,
            matchNames: ["dashboard"],
            icon: "dashboard",
        },
        {
            to: adminPath("pages"),
            label: "Страницы",
            visible: permissions.has("pages.access"),
            matchNames: ["pages", "page-create", "page-edit"],
            icon: "pages",
        },
        {
            to: adminPath("users"),
            label: "Пользователи",
            visible: permissions.has("users.access"),
            matchNames: ["users", "roles"],
            icon: "users",
        },
        {
            to: adminPath("media"),
            label: "Медиатека",
            visible: permissions.has("media.access"),
            matchNames: ["media"],
            icon: "media",
        },
        {
            to: adminPath("settings"),
            label: "Настройки",
            visible: permissions.has("settings.access"),
            matchNames: ["settings", "content-structure"],
            icon: "settings",
        },
        {
            to: adminPath("plugins"),
            label: "Плагины",
            visible: permissions.has("plugins.access"),
            matchNames: ["plugins"],
            icon: "plugins",
        },
    ].filter((link) => link.visible);
});

const pluginGroups = computed(() => {
    const pluginMenu = Array.isArray(user.value?.plugin_menu)
        ? user.value.plugin_menu
        : [];
    const grouped = new Map();

    pluginMenu.forEach((item) => {
        const group = String(item.group ?? "").trim();

        if (group === "") {
            return;
        }

        if (!grouped.has(group)) {
            grouped.set(group, {
                directItems: [],
                subgroupMap: new Map(),
            });
        }

        const groupState = grouped.get(group);
        const mappedItem = {
            to: item.path,
            label: item.title,
            external: !String(item.path || "").startsWith(
                adminBasePath() + "/",
            ),
        };

        const subgroup = String(item.subgroup ?? "").trim();

        if (subgroup === "") {
            groupState.directItems.push(mappedItem);
            return;
        }

        if (!groupState.subgroupMap.has(subgroup)) {
            groupState.subgroupMap.set(subgroup, []);
        }

        groupState.subgroupMap.get(subgroup).push(mappedItem);
    });

    return Array.from(grouped.entries()).map(([label, state]) => ({
        label,
        directItems: state.directItems,
        subgroups: Array.from(state.subgroupMap.entries()).map(
            ([subLabel, items]) => ({
                label: subLabel,
                items,
            }),
        ),
    }));
});

const standalonePluginLinks = computed(() => {
    const pluginMenu = Array.isArray(user.value?.plugin_menu)
        ? user.value.plugin_menu
        : [];

    return pluginMenu
        .filter((item) => String(item.group ?? "").trim() === "")
        .map((item) => ({
            to: item.path,
            label: item.title,
            external: !String(item.path || "").startsWith(
                adminBasePath() + "/",
            ),
        }));
});

const allNavItems = computed(() => {
    const items = [];

    links.value.forEach((l) => items.push(l));

    standalonePluginLinks.value.forEach((l) => items.push(l));

    pluginGroups.value.forEach((group) => {
        group.directItems.forEach((i) => items.push(i));

        group.subgroups.forEach((sub) => {
            sub.items.forEach((i) => items.push(i));
        });
    });

    return items;
});

const routeLabels = {
    dashboard: "Панель",
    pages: "Страницы",
    "page-create": "Создать страницу",
    "page-edit": "Редактирование страницы",
    users: "Пользователи",
    roles: "Роли и доступы",
    media: "Медиатека",
    settings: "Настройки",
    "content-structure": "Структура контента",
    plugins: "Плагины",
    "records-sections": "Записи: разделы",
    "records-posts": "Записи",
    "records-categories": "Категории",
    "records-tags": "Теги",
    "records-settings": "Настройки",
    "blog-posts": "Blog: Записи",
    "blog-categories": "Blog: Категории",
    "blog-tags": "Blog: Теги",
    "admin-not-found": "Страница не найдена",
};

const getIconNameByRoute = (to) => {
    if (to.includes("posts")) return "note";
    if (to.includes("categories")) return "category";
    if (to.includes("tags")) return "tags";
    if (to.includes("settings")) return "settings";
    if (to.includes("sections") || to.includes("notes")) return "note";

    return "note";
};

const breadcrumbs = computed(() => {
    const currentName = String(route.name ?? "");
    const items = [{ name: "dashboard", label: routeLabels.dashboard }];

    if (currentName === "" || currentName === "dashboard") {
        return items;
    }

    if (
        (currentName === "page-create" || currentName === "page-edit") &&
        !items.some((item) => item.name === "pages")
    ) {
        items.push({ name: "pages", label: routeLabels.pages });
    }

    if (
        currentName === "content-structure" &&
        !items.some((item) => item.name === "settings")
    ) {
        items.push({ name: "settings", label: routeLabels.settings });
    }

    if (
        currentName === "roles" &&
        !items.some((item) => item.name === "users")
    ) {
        items.push({ name: "users", label: routeLabels.users });
    }

    items.push({
        name: currentName,
        label: routeLabels[currentName] ?? "Раздел",
    });

    return items;
});

const currentNavLink = computed(() => {
    const path = route.path;

    if (path === "/admin") {
        return { label: "Обзор CMS" };
    }

    const segments = path.split("/").filter(Boolean);
    const recordsIndex = segments.indexOf("records");

    if (recordsIndex !== -1 && segments[recordsIndex + 1] === "sections") {
        return { label: "Создать раздел" };
    }

    if (recordsIndex !== -1 && segments[recordsIndex + 1]) {
        return { label: segments[recordsIndex + 1] };
    }

    return (
        [...allNavItems.value]
            .sort((a, b) => b.to.length - a.to.length)
            .find((item) => path.startsWith(item.to)) ?? {
            label: "Раздел",
        }
    );
});

const isDark = computed(() => themeMode.value === "dark");

const themeToggleText = computed(() =>
    isDark.value ? "Светлая тема" : "Темная тема",
);

const themeToggleIcon = computed(() => (isDark.value ? "light" : "dark"));

const publicSiteUrl = computed(() => {
    const adminBase = adminBasePath();

    if (adminBase === "/admin") {
        return "/";
    }

    return adminBase.endsWith("/admin")
        ? adminBase.slice(0, -"/admin".length) || "/"
        : "/";
});

function isLinkActive(link) {
    const currentName = String(route.name ?? "");

    if ((link.matchNames ?? []).includes(currentName)) {
        return true;
    }

    if (
        typeof link.to === "string" &&
        link.to.startsWith(adminBasePath() + "/")
    ) {
        return String(route.path ?? "").startsWith(link.to);
    }

    return false;
}

function togglePluginGroup(groupLabel) {
    if (openPluginGroups.value.has(groupLabel)) {
        openPluginGroups.value.delete(groupLabel);
        return;
    }

    openPluginGroups.value.add(groupLabel);
}

function isPluginGroupOpen(groupLabel) {
    return openPluginGroups.value.has(groupLabel);
}

function isPluginGroupActive(group) {
    const directActive = group.directItems.some((item) => {
        if (item.external) {
            return false;
        }

        return String(route.path ?? "").startsWith(String(item.to ?? ""));
    });

    if (directActive) {
        return true;
    }

    return group.subgroups.some((subgroup) =>
        subgroup.items.some((item) => {
            if (item.external) {
                return false;
            }

            return String(route.path ?? "").startsWith(String(item.to ?? ""));
        }),
    );
}

function subgroupKey(groupLabel, subgroupLabel) {
    return `${groupLabel}::${subgroupLabel}`;
}

function togglePluginSubgroup(groupLabel, subgroupLabel) {
    const key = subgroupKey(groupLabel, subgroupLabel);

    if (openPluginSubgroups.value.has(key)) {
        openPluginSubgroups.value.delete(key);
        return;
    }

    openPluginSubgroups.value.add(key);
}

function isPluginSubgroupOpen(groupLabel, subgroupLabel) {
    return openPluginSubgroups.value.has(
        subgroupKey(groupLabel, subgroupLabel),
    );
}

function isPluginSubgroupActive(groupLabel, subgroup) {
    return subgroup.items.some((item) => {
        if (item.external) {
            return false;
        }

        return String(route.path ?? "").startsWith(String(item.to ?? ""));
    });
}

async function loadCurrentUser() {
    if (!user.value) {
        loading.value = true;
    }

    errorMessage.value = "";

    try {
        const [payload, settingsPayload] = await Promise.all([
            fetchCurrentUser(Boolean(user.value)),
            loadCmsSettings(),
        ]);
        user.value = payload.data;
        siteName.value = settingsPayload.settings?.site_name || "My CMS";

        const defaultOpenGroups = new Set();
        const defaultOpenSubgroups = new Set();

        pluginGroups.value.forEach((group) => {
            if (isPluginGroupActive(group)) {
                defaultOpenGroups.add(group.label);
            }

            group.subgroups.forEach((subgroup) => {
                if (isPluginSubgroupActive(group.label, subgroup)) {
                    defaultOpenGroups.add(group.label);
                    defaultOpenSubgroups.add(
                        subgroupKey(group.label, subgroup.label),
                    );
                }
            });
        });

        openPluginGroups.value = defaultOpenGroups;
        openPluginSubgroups.value = defaultOpenSubgroups;
    } catch (error) {
        errorMessage.value = "Не удалось загрузить пользователя.";
        console.error(error);
    } finally {
        loading.value = false;
    }
}

const formattedSiteName = computed(() => {
    const name = siteName.value || "My CMS";
    const [first, ...rest] = name.split(" ");
    return `<span>${first}</span> ${rest.join(" ")}`;
});

async function handleLogout() {
    try {
        await logout();
        await router.push(adminLoginPath());
        window.location.href = adminLoginPath();
    } catch (error) {
        errorMessage.value = "Не удалось завершить сессию.";
        console.error(error);
    }
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function handleThemeToggle() {
    const nextState = toggleAdminThemeMode();
    themeMode.value = nextState.mode;
    userMenuOpen.value = false;
}

async function openUserEditor() {
    userMenuOpen.value = false;

    if (route.name !== "users") {
        await router.push({ name: "users" });
    }
}

function toggleUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
}

function handleDocumentClick() {
    userMenuOpen.value = false;
}

watch(
    () => route.fullPath,
    () => {
        sidebarOpen.value = false;
        userMenuOpen.value = false;
    },
);

onMounted(async () => {
    themeMode.value = getAdminThemeState().mode;
    document.addEventListener("click", handleDocumentClick);
    loadCurrentUser();
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <div class="admin-shell">
        <aside class="admin-sidebar" :class="{ 'is-open': sidebarOpen }">
            <div class="admin-sidebar__brand">
                <h1 class="admin-title"><span>my</span> CMS</h1>
                <a
                    :href="publicSiteUrl"
                    class="admin-sidebar__site-link"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Посмотреть сайт"
                >
                    <Icon
                        name="show"
                        width="20"
                        height="20"
                        class="admin-sidebar__brand-icon"
                    />
                </a>
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
                        <Icon
                            v-if="link.icon"
                            :name="link.icon"
                            width="18"
                            height="18"
                            class="nav-link__icon"
                        />
                        {{ link.label }}
                    </RouterLink>

                    <a v-else :href="link.to" class="nav-link">
                        {{ link.label }}
                    </a>
                </template>

                <div
                    v-if="
                        pluginGroups.length > 0 ||
                        standalonePluginLinks.length > 0
                    "
                    class="admin-nav__plugin-divider"
                >
                    <span>Плагины</span>
                </div>

                <template
                    v-for="group in pluginGroups"
                    :key="`plugin-group-${group.label}`"
                >
                    <button
                        type="button"
                        class="nav-link nav-link--group"
                        :class="{ 'is-active': isPluginGroupActive(group) }"
                        @click="togglePluginGroup(group.label)"
                    >
                        <span
                            ><Icon
                                name="record"
                                width="20"
                                height="20"
                                class="nav-link__icon"
                            />{{ group.label }}</span
                        >
                        <Icon
                            name="arrow-down"
                            width="20"
                            height="20"
                            class="nav-link__arrow"
                            :class="{
                                'is-open': isPluginGroupOpen(group.label),
                            }"
                        />
                    </button>
                    <Transition name="slide">
                        <div
                            v-if="isPluginGroupOpen(group.label)"
                            class="admin-nav__subgroup"
                        >
                            <template
                                v-for="item in group.directItems"
                                :key="`${group.label}-${item.to}`"
                            >
                                <RouterLink
                                    v-if="!item.external"
                                    :to="item.to"
                                    class="nav-link-create"
                                    :class="{ 'is-active': isLinkActive(item) }"
                                    active-class=""
                                    exact-active-class=""
                                >
                                    {{ item.label
                                    }}<Icon
                                        name="create"
                                        width="22"
                                        height="22"
                                    />
                                </RouterLink>

                                <a
                                    v-else
                                    :href="item.to"
                                    class="nav-link nav-link--sub"
                                >
                                    {{ item.label }}
                                </a>
                            </template>

                            <template
                                v-for="subgroup in group.subgroups"
                                :key="`${group.label}-${subgroup.label}`"
                            >
                                <button
                                    type="button"
                                    class="nav-link nav-link--subgroup"
                                    :class="{
                                        'is-open': isPluginSubgroupOpen(
                                            group.label,
                                            subgroup.label,
                                        ),
                                    }"
                                    @click="
                                        togglePluginSubgroup(
                                            group.label,
                                            subgroup.label,
                                        )
                                    "
                                >
                                    <span>{{ subgroup.label }}</span>
                                    <Icon
                                        name="arrow-down"
                                        width="18"
                                        height="18"
                                        class="nav-link__arrow"
                                        :class="{
                                            'is-open': isPluginSubgroupOpen(
                                                group.label,
                                                subgroup.label,
                                            ),
                                        }"
                                    />
                                </button>

                                <Transition name="slide">
                                    <div
                                        v-if="
                                            isPluginSubgroupOpen(
                                                group.label,
                                                subgroup.label,
                                            )
                                        "
                                        class="admin-nav__subgroup-inner"
                                    >
                                        <template
                                            v-for="item in subgroup.items"
                                            :key="`${group.label}-${subgroup.label}-${item.to}`"
                                        >
                                            <RouterLink
                                                v-if="!item.external"
                                                :to="item.to"
                                                class="nav-link nav-link--sub"
                                                :class="{
                                                    'is-active':
                                                        isLinkActive(item),
                                                }"
                                                active-class=""
                                                exact-active-class=""
                                            >
                                                <Icon
                                                    :name="
                                                        getIconNameByRoute(
                                                            item.to,
                                                        )
                                                    "
                                                    width="16"
                                                    height="16"
                                                    class=""
                                                />
                                                {{ item.label }}
                                            </RouterLink>

                                            <a
                                                v-else
                                                :href="item.to"
                                                class="nav-link nav-link--sub"
                                            >
                                                {{ item.label }}
                                            </a>
                                        </template>
                                    </div>
                                </Transition>
                            </template>
                        </div>
                    </Transition>
                </template>

                <template
                    v-for="link in standalonePluginLinks"
                    :key="`plugin-link-${link.to}`"
                >
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
                <p v-if="errorMessage" class="error-text">
                    {{ errorMessage }}
                </p>
                <div v-if="loading" class="muted">Загрузка сессии...</div>

                <div
                    v-else-if="user"
                    class="admin-sidebar__session"
                    @click.stop
                >
                    <button
                        type="button"
                        class="admin-user-badge"
                        @click.stop="toggleUserMenu"
                    >
                        <span
                            ><Icon
                                name="avatar"
                                width="16"
                                height="16"
                                class="admin-user-menu__item__icon"
                            />{{ displayName }}</span
                        >

                        <Icon
                            name="arrow-down"
                            width="20"
                            height="20"
                            class="admin-user-badge__arrow"
                            :class="{ 'is-open': userMenuOpen }"
                        />
                    </button>
                    <transition name="user-menu">
                        <div v-if="userMenuOpen" class="admin-user-menu">
                            <button
                                type="button"
                                class="admin-user-menu__item"
                                @click="handleThemeToggle"
                            >
                                <Icon
                                    :name="themeToggleIcon"
                                    width="20"
                                    height="20"
                                    class="admin-user-menu__item__icon"
                                />
                                {{ themeToggleText }}
                            </button>
                            <button
                                type="button"
                                class="admin-user-menu__item"
                                @click="openUserEditor"
                            >
                                <Icon
                                    name="user"
                                    width="20"
                                    height="20"
                                    class="admin-user-menu__item__icon"
                                />Профиль
                            </button>
                            <button
                                type="button"
                                class="admin-user-menu__item"
                                @click="handleLogout"
                            >
                                <Icon
                                    name="exit"
                                    width="20"
                                    height="20"
                                    class="admin-user-menu__item__icon"
                                />Выход
                            </button>
                        </div>
                    </transition>
                </div>

                <button
                    v-else
                    type="button"
                    class="button-link"
                    @click="handleLogout"
                >
                    Выход
                </button>
            </div>
        </aside>

        <div
            v-if="sidebarOpen"
            class="admin-sidebar-backdrop"
            @click="sidebarOpen = false"
        />

        <section class="admin-main">
            <AdminNotifications />

            <header class="admin-topbar">
                <button
                    type="button"
                    class="admin-menu-button"
                    @click="toggleSidebar"
                >
                    <Icon
                        name="menu"
                        width="34"
                        height="34"
                        class="admin-menu-button__icon"
                    />
                </button>
                <div class="admin-topbar__meta">
                    <h1 class="admin-page-header__title">
                        {{ currentNavLink.label }}
                    </h1>
                    <nav class="admin-breadcrumbs" aria-label="Хлебные крошки">
                        <template
                            v-for="(item, index) in breadcrumbs"
                            :key="`${item.name}-${index}`"
                        >
                            <RouterLink
                                v-if="index < breadcrumbs.length - 1"
                                :to="{ name: item.name }"
                                class="admin-breadcrumbs__link"
                            >
                                {{ item.label }}
                            </RouterLink>
                            <span v-else class="admin-breadcrumbs__current">{{
                                item.label
                            }}</span>
                            <span
                                v-if="index < breadcrumbs.length - 1"
                                class="admin-breadcrumbs__sep"
                            >
                                <Icon name="arrow-down" width="22" height="22"
                            /></span>
                        </template>
                    </nav>
                </div>
            </header>

            <main class="admin-content">
                <router-view />
            </main>
        </section>
    </div>
</template>
