<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { DateFormatter, type DateValue } from '@internationalized/date';
import { CalendarIcon } from 'lucide-vue-next';
import { defineEmits, ref, watch } from 'vue';

const df = new DateFormatter('fr-CH', {
    dateStyle: 'long',
});

defineProps<{
    placeholder?: string;
}>();

const value = ref<DateValue>();
const emit = defineEmits(['update:value']);

watch(value, (newValue) => {
    emit('update:value', newValue ? newValue.toDate('UTC').toISOString().split('T').at(0) : null);
});
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button variant="outline" :class="cn('w-[280px] justify-start text-left font-normal', !value && 'text-muted-foreground')">
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ value ? df.format(value.toDate('UTC')) : (placeholder ?? 'Pick a date') }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0">
            <Calendar v-model="value" initial-focus />
        </PopoverContent>
    </Popover>
</template>
