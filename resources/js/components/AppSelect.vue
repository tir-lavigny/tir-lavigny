<script setup lang="ts">
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PropType } from 'vue';
import { computed, defineEmits, defineProps } from 'vue';

const props = defineProps({
    modelValue: {
        type: String as PropType<string>,
        required: true,
    },
    options: {
        type: Array as PropType<{ label: string; value: string }[]>,
        required: true,
    },
    placeholder: {
        type: String as PropType<string>,
        default: 'Select an option',
    },
    groupLabel: {
        type: String as PropType<string>,
        default: 'Options',
    },
});

const emit = defineEmits(['update:modelValue']);

const selectedValue = computed({
    get: () => props.modelValue,
    set: (value: string) => {
        emit('update:modelValue', value);
    },
});
</script>

<template>
    <Select v-model="selectedValue">
        <SelectTrigger>
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup>
                <SelectLabel>{{ groupLabel }}</SelectLabel>
                <SelectItem v-for="option in options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
