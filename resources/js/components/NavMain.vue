<script lang="ts" setup>
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import {type NavItem, type SharedData} from "@/types";
import {Link, usePage} from "@inertiajs/vue3";

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage<SharedData>();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ label ?? "Platform" }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    :is-active="!item.external && item.href === page.url"
                    as-child
                >
                    <a
                        v-if="item.external"
                        :href="item.href"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        <component :is="item.icon"/>
                        <span>{{ item.title }}</span>
                    </a>
                    <Link v-else :href="item.href">
                        <component :is="item.icon"/>
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
