<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Link } from '@inertiajs/vue3';

type ButtonVariant = 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';

withDefaults(
    defineProps<{
        label: string;
        href?: string | null;
        variant?: ButtonVariant;
        disabled?: boolean;
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        href: null,
        variant: 'ghost',
        disabled: false,
        type: 'button',
    },
);

const emit = defineEmits<{ click: [] }>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <span v-if="disabled" class="inline-flex">
                <Button size="icon" :variant="variant" disabled :aria-label="label" :type="type">
                    <slot />
                </Button>
            </span>
            <Button v-else-if="href" as-child size="icon" :variant="variant" :aria-label="label">
                <Link :href="href">
                    <slot />
                </Link>
            </Button>
            <Button v-else size="icon" :variant="variant" :aria-label="label" :type="type" @click="emit('click')">
                <slot />
            </Button>
        </TooltipTrigger>
        <TooltipContent>{{ label }}</TooltipContent>
    </Tooltip>
</template>
