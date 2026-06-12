<script setup>
import { RouterLink } from "vue-router";
import Icon from "../../components/ui/Icon.vue";
</script>

<template>
    <section class="panel-card admin-empty-state">
        <p>
            Такой страницы в административной части нет. Проверь адрес или
            вернись к списку страниц.
        </p>

        <div class="admin-empty-state__links">
            <RouterLink
                :to="{ name: 'dashboard' }"
                class="button-base button-link"
            >
                <Icon name="home" width="18" height="18" />Назад на главную
            </RouterLink>

            <RouterLink :to="{ name: 'pages' }" class="button-base">
                <Icon name="pages" width="18" height="18" />К страницам
            </RouterLink>
        </div>
    </section>
</template>
