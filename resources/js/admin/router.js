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
                path: 'media',
                name: 'media',
                component: () => import('./pages/Media/MediaLibrary.vue'),
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('./pages/Settings/SettingsIndex.vue'),
            },
        ],
    },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})