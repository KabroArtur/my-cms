<script setup>
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
</script>

<template>
    <AdminPage
        eyebrow="Settings"
        title="Настройки"
        description=""
    >
        <section class="admin-page-grid">
            <AdminCard>
                <div class="admin-stack">
                    <h2>Минимальный функционал</h2>
                    <ul class="admin-list">
                        <li>Название сайта.</li>
                        <li>Email отправителя.</li>
                        <li>Логотип проекта.</li>
                        <li>SEO title по умолчанию.</li>
                        <li>SEO description по умолчанию.</li>
                    </ul>
                </div>
            </AdminCard>
        </section>
    </AdminPage>
</template>