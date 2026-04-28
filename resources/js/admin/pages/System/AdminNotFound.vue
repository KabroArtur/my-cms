<template>
    <section class="panel-card admin-empty-state">
        <p class="eyebrow">CMS</p>
        <h1>404</h1>
        <p class="muted">Такой страницы в административной части нет. Проверь адрес или вернись к списку страниц.</p>

        <div class="admin-actions-row">
            <RouterLink to="/admin" class="button-link">
                На dashboard
            </RouterLink>

            <RouterLink to="/admin/pages" class="button-link">
                К страницам
            </RouterLink>
        </div>
    </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>