<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Info } from '@lucide/vue';

const props = defineProps<{
    label: string;
    description: string;
    class?: string;
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <span :class="cn('inline-flex cursor-help items-center gap-1', props.class)">
                {{ label }}
                <Info class="h-3.5 w-3.5" />
            </span>
        </TooltipTrigger>
        <TooltipContent>{{ description }}</TooltipContent>
    </Tooltip>
</template>
