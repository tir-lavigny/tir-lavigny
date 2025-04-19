<script setup lang="ts">
import AppSelect from '@/components/AppSelect.vue';
import { H4 } from '@/components/typography/headings';

defineProps<{
    modelValue: string | number;
    options: { label: string; value: string }[];
    placeholder?: string;
    groupLabel?: string;
    label: string;
}>();

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-2">
        <H4>{{ label }}</H4>
        <AppSelect
            :modelValue="String(modelValue)"
            :options="options"
            @update:modelValue="$emit('update:modelValue', $event)"
            :placeholder="placeholder"
            :groupLabel="groupLabel"
        />
    </div>
</template>
