<script lang="ts" setup>
import InputError from "@/components/InputError.vue";
import TextLink from "@/components/TextLink.vue";
import {Button} from "@/components/ui/button";
import {Input} from "@/components/ui/input";
import {Label} from "@/components/ui/label";
import AuthBase from "@/layouts/AuthLayout.vue";
import {Head, useForm} from "@inertiajs/vue3";
import {LoaderCircle} from "lucide-vue-next";

defineProps<{ lodges: Array<{ id: number; name: string; number: string }> }>();

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    home_lodge_id: "",
});

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <AuthBase
        description="Enter your details below to create your account"
        title="Create an account"
    >
        <Head title="Register"/>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="home_lodge_id">Home lodge</Label>
                    <select
                        id="home_lodge_id"
                        v-model="form.home_lodge_id"
                        class="rounded-md border bg-background px-3 py-2"
                        required
                    >
                        <option disabled value="">Select your lodge</option>
                        <option
                            v-for="lodge in lodges"
                            :key="lodge.id"
                            :value="lodge.id"
                        >
                            {{ lodge.name }} No. {{ lodge.number }}
                        </option>
                    </select>
                    <InputError :message="form.errors.home_lodge_id"/>
                </div>

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        :tabindex="1"
                        autocomplete="name"
                        autofocus
                        placeholder="Full name"
                        required
                        type="text"
                    />
                    <InputError :message="form.errors.name"/>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        :tabindex="2"
                        autocomplete="email"
                        placeholder="email@example.com"
                        required
                        type="email"
                    />
                    <InputError :message="form.errors.email"/>
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        :tabindex="3"
                        autocomplete="new-password"
                        placeholder="Password"
                        required
                        type="password"
                    />
                    <InputError :message="form.errors.password"/>
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :tabindex="4"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        required
                        type="password"
                    />
                    <InputError :message="form.errors.password_confirmation"/>
                </div>

                <Button
                    :disabled="form.processing"
                    :tabindex="5"
                    class="mt-2 w-full"
                    type="submit"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                    />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="route('login')"
                    :tabindex="6"
                    class="underline underline-offset-4"
                >Log in
                </TextLink
                >
            </div>
        </form>
    </AuthBase>
</template>
