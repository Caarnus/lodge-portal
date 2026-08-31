<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import LodgeForm from "../platform/LodgeForm.vue";
defineOptions({ layout: AppLayout });
defineProps<{
    lodge: any;
    canManageEvents: boolean;
    canManageRoles: boolean;
    eventCategories?: any[];
    roles?: any[];
    permissions?: any[];
}>();
</script>
<template>
    <LodgeForm :lodge="lodge" :action="`/lodges/${lodge.id}/settings`"/>
</template>
