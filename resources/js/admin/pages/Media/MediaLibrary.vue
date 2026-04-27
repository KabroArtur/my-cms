<script setup>
import AdminCard from '../../components/ui/AdminCard.vue'
import AdminPage from '../../components/ui/AdminPage.vue'
</script>

<template>
    <AdminPage
        eyebrow="Media"
        title="Медиабиблиотека"
        description=""
    >
        <section class="admin-page-grid">
            <AdminCard>
                <div class="admin-stack">
                    <h2>Минимальный функционал</h2>
                    <ul class="admin-list">
                        <li>Загрузка изображений и документов.</li>
                        <li>Список загруженных файлов.</li>
                        <li>Удаление файлов.</li>
                        <li>Копирование ссылки на файл.</li>
                        <li>Выбор файла для страниц.</li>
                    </ul>
                </div>
            </AdminCard>
        </section>
    </AdminPage>
</template>