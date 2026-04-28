import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/admin',
        component: () => import('./layout/AdminLayout.vue'),
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('./pages/Dashboard/DashboardIndex.vue'),
            },
            {
                path: 'pages',
                name: 'pages',
                component: () => import('./pages/Pages/PagesIndex.vue'),
            },
            {
                path: 'pages/create',
                name: 'page-create',
                component: () => import('./pages/Pages/PageEditor.vue'),
            },
            {
                path: 'pages/:id',
                name: 'page-edit',
                component: () => import('./pages/Pages/PageEditor.vue'),
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('./pages/Users/UsersIndex.vue'),
            },
            {
                path: 'roles',
                name: 'roles',
                component: () => import('./pages/Roles/RolesIndex.vue'),
            },
            {
                path: 'media',
                name: 'media',
                component: () => import('./pages/Media/MediaLibrary.vue'),
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('./pages/Settings/SettingsIndex.vue'),
            },
            {
                path: ':pathMatch(.*)*',
                name: 'admin-not-found',
                component: () => import('./pages/System/AdminNotFound.vue'),
            },
        ],
    },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})