import { createRouter, createWebHistory } from 'vue-router'
import { adminBasePath } from './utils/adminPath'
import AdminLayout from './layout/AdminLayout.vue'
import BlogCategoriesIndex from './pages/Blog/BlogCategoriesIndex.vue'
import BlogPostsIndex from './pages/Blog/BlogPostsIndex.vue'
import BlogTagsIndex from './pages/Blog/BlogTagsIndex.vue'
import DashboardIndex from './pages/Dashboard/DashboardIndex.vue'
import MediaLibrary from './pages/Media/MediaLibrary.vue'
import PagesIndex from './pages/Pages/PagesIndex.vue'
import PageEditor from './pages/Pages/PageEditor.vue'
import PluginsIndex from './pages/Plugins/PluginsIndex.vue'
import RecordSectionsIndex from './pages/Records/RecordSectionsIndex.vue'
import RecordSectionWorkspace from './pages/Records/RecordSectionWorkspace.vue'
import RolesIndex from './pages/Roles/RolesIndex.vue'
import SettingsIndex from './pages/Settings/SettingsIndex.vue'
import ContentStructure from './pages/Settings/ContentStructure.vue'
import AdminNotFound from './pages/System/AdminNotFound.vue'
import UsersIndex from './pages/Users/UsersIndex.vue'

const routes = [
    {
        path: adminBasePath(),
        component: AdminLayout,
        children: [
            {
                path: '',
                name: 'dashboard',
                component: DashboardIndex,
            },
            {
                path: 'pages',
                name: 'pages',
                component: PagesIndex,
            },
            {
                path: 'pages/create',
                name: 'page-create',
                component: PageEditor,
            },
            {
                path: 'pages/:id',
                name: 'page-edit',
                component: PageEditor,
            },
            {
                path: 'users',
                name: 'users',
                component: UsersIndex,
            },
            {
                path: 'roles',
                name: 'roles',
                component: RolesIndex,
            },
            {
                path: 'media',
                name: 'media',
                component: MediaLibrary,
            },
            {
                path: 'settings',
                name: 'settings',
                component: SettingsIndex,
            },
            {
                path: 'settings/content-structure',
                name: 'content-structure',
                component: ContentStructure,
            },
            {
                path: 'plugins',
                name: 'plugins',
                component: PluginsIndex,
            },
            {
                path: 'records/sections',
                name: 'records-sections',
                component: RecordSectionsIndex,
            },
            {
                path: 'records/:sectionSlug/posts',
                name: 'records-posts',
                component: RecordSectionWorkspace,
            },
            {
                path: 'records/:sectionSlug/categories',
                name: 'records-categories',
                component: RecordSectionWorkspace,
            },
            {
                path: 'records/:sectionSlug/tags',
                name: 'records-tags',
                component: RecordSectionWorkspace,
            },
            {
                path: 'records/:sectionSlug/settings',
                name: 'records-settings',
                component: RecordSectionWorkspace,
            },
            {
                path: 'blog/posts',
                name: 'blog-posts',
                component: BlogPostsIndex,
            },
            {
                path: 'blog/categories',
                name: 'blog-categories',
                component: BlogCategoriesIndex,
            },
            {
                path: 'blog/tags',
                name: 'blog-tags',
                component: BlogTagsIndex,
            },
            {
                path: ':pathMatch(.*)*',
                name: 'admin-not-found',
                component: AdminNotFound,
            },
        ],
    },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})