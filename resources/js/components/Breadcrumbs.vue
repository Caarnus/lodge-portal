<script lang="ts" setup>
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import type {BreadcrumbItemType} from "@/types";

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="item.href">
                <BreadcrumbItem>
                    <BreadcrumbPage v-if="index === breadcrumbs.length - 1">
                        {{ item.title }}
                    </BreadcrumbPage>
                    <BreadcrumbLink v-else :href="item.href">
                        {{ item.title }}
                    </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1"/>
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
