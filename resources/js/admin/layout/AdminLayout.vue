<template>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-logo">CMS</div>

            <nav>
                <RouterLink to="/admin">Dashboard</RouterLink>
                <RouterLink to="/admin/pages">Pages</RouterLink>
                <RouterLink to="/admin/media">Media</RouterLink>
                <RouterLink to="/admin/settings">Settings</RouterLink>
            </nav>
        </aside>

        <main class="admin-main">
            <router-view />
        </main>
    </div>
</template>