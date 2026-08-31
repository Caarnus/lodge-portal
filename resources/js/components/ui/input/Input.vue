<script lang="ts" setup>
import {cn} from "@/lib/utils";
import {useVModel} from "@vueuse/core";
import type {HTMLAttributes, InputHTMLAttributes} from "vue";

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes["class"];
    type?: InputHTMLAttributes["type"];
    autocomplete?: string;
    autofocus?: boolean;
    name?: string;
    readonly?: boolean;
    required?: boolean;
    tabindex?: string | number;
    placeholder?: string;
}>();

const emits = defineEmits<{
    (e: "update:modelValue", payload: string | number): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <input
        v-model="modelValue"
        :autocomplete="props.autocomplete"
        :autofocus="props.autofocus"
        :class="
            cn(
                'flex h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/35 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                props.class,
            )
        "
        :name="props.name"
        :placeholder="props.placeholder"
        :readonly="props.readonly"
        :required="props.required"
        :tabindex="props.tabindex"
        :type="props.type"
    />
</template>
