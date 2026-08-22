<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import SettingsLayout from "@/layouts/settings/Layout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import axios from "axios";
import { ref } from "vue";
defineOptions({ layout: [AppLayout, SettingsLayout] });
defineProps<{ enabled: boolean; confirmed: boolean; required: boolean }>();
const code = useForm({ code: "" });
const qr = ref("");
const recovery = ref<string[]>([]);
async function details() {
    const [q, r] = await Promise.all([
        axios.get("/user/two-factor-qr-code"),
        axios.get("/user/two-factor-recovery-codes"),
    ]);
    qr.value = q.data.svg;
    recovery.value = r.data;
}
function enable() {
    router.post("/user/two-factor-authentication", {}, { onSuccess: details });
}
function confirm() {
    code.post("/user/confirmed-two-factor-authentication");
}
function disable() {
    router.delete("/user/two-factor-authentication");
}
</script>
<template>
    <Head title="Two-factor authentication" />
    <div>
        <h2 class="text-xl font-semibold">Two-factor authentication</h2>
        <p class="mt-2 text-muted-foreground">
            Use an authenticator app for additional account security.
            <strong v-if="required"
                >This is required for administrators.</strong
            >
        </p>
        <button
            v-if="!enabled"
            @click="enable"
            class="mt-4 rounded bg-slate-900 px-4 py-2 text-white"
        >
            Enable 2FA</button
        ><template v-else
            ><button
                v-if="!qr"
                @click="details"
                class="mt-4 rounded border px-4 py-2"
            >
                Show setup details
            </button>
            <div v-if="qr" class="mt-4" v-html="qr"></div>
            <form
                v-if="!confirmed"
                @submit.prevent="confirm"
                class="mt-4 flex gap-2"
            >
                <input
                    v-model="code.code"
                    required
                    inputmode="numeric"
                    placeholder="6-digit code"
                    class="rounded border p-2"
                /><button class="rounded bg-slate-900 px-4 text-white">
                    Confirm
                </button>
            </form>
            <div v-if="recovery.length" class="mt-4">
                <h3 class="font-semibold">Recovery codes</h3>
                <code v-for="item in recovery" :key="item" class="block">{{
                    item
                }}</code>
            </div>
            <button
                @click="disable"
                class="mt-6 rounded bg-red-700 px-4 py-2 text-white"
            >
                Disable 2FA
            </button></template
        >
    </div>
</template>
