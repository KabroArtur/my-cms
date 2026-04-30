import { createRouter, createWebHistory } from 'vue-router'
import { adminBasePath } from './utils/adminPath'

const routes = [
    {
        path: adminBasePath(),
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
                path: 'settings/content-structure',
                name: 'content-structure',
                component: () => import('./pages/Settings/ContentStructure.vue'),
            },
            {
                path: 'plugins',
                name: 'plugins',
                component: () => import('./pages/Plugins/PluginsIndex.vue'),
            },
            {
                path: 'records/sections',
                name: 'records-sections',
                component: () => import('./pages/Records/RecordSectionsIndex.vue'),
            },
            {
                path: 'records/:sectionSlug/posts',
                name: 'records-posts',
                component: () => import('./pages/Records/RecordSectionWorkspace.vue'),
            },
            {
                path: 'records/:sectionSlug/categories',
                name: 'records-categories',
                component: () => import('./pages/Records/RecordSectionWorkspace.vue'),
            },
            {
                path: 'records/:sectionSlug/tags',
                name: 'records-tags',
                component: () => import('./pages/Records/RecordSectionWorkspace.vue'),
            },
            {
                path: 'records/:sectionSlug/settings',
                name: 'records-settings',
                component: () => import('./pages/Records/RecordSectionWorkspace.vue'),
            },
            {
                path: 'blog/posts',
                name: 'blog-posts',
                component: () => import('./pages/Blog/BlogPostsIndex.vue'),
            },
            {
                path: 'blog/categories',
                name: 'blog-categories',
                component: () => import('./pages/Blog/BlogCategoriesIndex.vue'),
            },
            {
                path: 'blog/tags',
                name: 'blog-tags',
                component: () => import('./pages/Blog/BlogTagsIndex.vue'),
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