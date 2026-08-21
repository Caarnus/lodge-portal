<script setup lang="ts">
defineOptions({ name: 'PublicNavigationItem' });
defineProps<{ item: any; lodgeSlug: string }>();
</script>

<template>
    <li>
        <a :href="item.is_home ? `/l/${lodgeSlug}` : `/l/${lodgeSlug}/${item.slug}`" class="block rounded px-3 py-2 font-medium hover:bg-black/10 focus-visible:outline-2 focus-visible:outline-offset-2">
            {{ item.title }}
        </a>
        <ul v-if="item.children?.length" class="ml-4 border-l pl-2">
            <PublicNavigationItem v-for="child in item.children" :key="child.slug" :item="child" :lodge-slug="lodgeSlug" />
        </ul>
    </li>
</template>
