<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import LodgeConfigurationDialogs from "@/components/lodge/LodgeConfigurationDialogs.vue";
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
    <LodgeForm :lodge="lodge" :action="`/lodges/${lodge.id}/settings`">
        <LodgeConfigurationDialogs
            :lodge="lodge"
            :can-manage-events="canManageEvents"
            :can-manage-roles="canManageRoles"
            :event-categories="eventCategories ?? []"
            :roles="roles ?? []"
            :permissions="permissions ?? []"
        />
    </LodgeForm>
</template>
