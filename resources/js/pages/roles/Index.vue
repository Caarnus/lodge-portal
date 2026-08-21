<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; roles: any[]; permissions: any[] }>();
const roleForm = useForm({ name: '', permission_ids: [] as number[] });
const roleDrafts = reactive(Object.fromEntries(props.roles.map((role) => [role.id, { name: role.name, permission_ids: role.permissions.map((permission: any) => permission.id) }])));
const createRole = () => roleForm.post(`/lodges/${props.lodge.id}/roles`, { onSuccess: () => roleForm.reset() });
const updateRole = (role: any) => router.put(`/lodges/${props.lodge.id}/roles/${role.id}`, roleDrafts[role.id]);
</script>

<template>
    <Head :title="`${lodge.name} role definitions`" />
    <main class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h1 class="text-2xl font-bold">Role definitions</h1><p class="text-sm text-slate-600">{{ lodge.name }}</p></div>
            <Link :href="`/lodges/${lodge.id}/role-assignments`" class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white">Manage role assignments</Link>
        </div>
        <section class="mt-6 rounded-lg border p-4"><h2 class="font-semibold">Create custom role</h2><form class="mt-3" @submit.prevent="createRole"><label>Role name<input v-model="roleForm.name" required class="mt-1 w-full rounded border p-2" /></label><fieldset class="mt-4"><legend class="font-medium">Permissions you may grant</legend><label v-for="permission in permissions" :key="permission.id" class="mt-2 flex gap-2"><input v-model="roleForm.permission_ids" type="checkbox" :value="permission.id" /> {{ permission.name }}</label></fieldset><p v-for="message in roleForm.errors" :key="message" class="mt-2 text-sm text-red-700">{{ message }}</p><button class="mt-4 rounded bg-slate-900 px-4 py-2 text-white">Create role</button></form></section>
        <section class="mt-6"><h2 class="font-semibold">Existing roles</h2><div v-for="role in roles" :key="role.id" class="mt-3 rounded-lg border p-4"><template v-if="role.is_system"><p class="font-medium">{{ role.name }} <span class="text-xs text-slate-500">Built in</span></p><p class="mt-1 text-sm text-slate-600">{{ role.permissions.map((item: any) => item.name).join(', ') || 'No permissions' }}</p></template><form v-else @submit.prevent="updateRole(role)"><label class="font-medium">Role name<input v-model="roleDrafts[role.id].name" required class="mt-1 w-full rounded border p-2" /></label><fieldset class="mt-3"><legend class="text-sm font-medium">Permissions</legend><label v-for="permission in permissions" :key="permission.id" class="mt-2 flex gap-2 text-sm"><input v-model="roleDrafts[role.id].permission_ids" type="checkbox" :value="permission.id" /> {{ permission.name }}</label></fieldset><button class="mt-3 rounded border px-4 py-2">Save role</button></form></div></section>
    </main>
</template>
