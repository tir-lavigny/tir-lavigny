<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Check, ChevronDown, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

interface Option {
    label: string;
    value: string;
}

const props = defineProps<{
    options: Option[];
    modelValue: string[];
    placeholder?: string;
    disabled?: boolean;
    class?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const open = ref(false);
const containerRef = ref<HTMLElement | null>(null);

const selectedLabels = computed(() =>
    props.options.filter((o) => props.modelValue.includes(o.value)).map((o) => o.label),
);

const toggle = (value: string) => {
    const current = [...props.modelValue];
    const index = current.indexOf(value);
    if (index === -1) {
        current.push(value);
    } else {
        current.splice(index, 1);
    }
    emit('update:modelValue', current);
};

const remove = (value: string) => {
    emit(
        'update:modelValue',
        props.modelValue.filter((v) => v !== value),
    );
};

const handleClickOutside = (e: MouseEvent) => {
    if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
        open.value = false;
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', handleClickOutside);
    } else {
        document.removeEventListener('mousedown', handleClickOutside);
    }
});
</script>

<template>
    <div ref="containerRef" class="relative">
        <button
            type="button"
            :disabled="disabled"
            :class="
                cn(
                    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 dark:hover:bg-input/50 flex min-h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                    props.class,
                )
            "
            @click="open = !open"
        >
            <div class="flex flex-1 flex-wrap gap-1">
                <template v-if="selectedLabels.length > 0">
                    <span
                        v-for="(label, i) in selectedLabels"
                        :key="i"
                        class="bg-secondary text-secondary-foreground inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                    >
                        {{ label }}
                        <X class="size-3 cursor-pointer" @click.stop="remove(options.find((o) => o.label === label)!.value)" />
                    </span>
                </template>
                <span v-else class="text-muted-foreground">{{ placeholder ?? 'Sélectionner...' }}</span>
            </div>
            <ChevronDown class="size-4 shrink-0 opacity-50" />
        </button>

        <div
            v-if="open"
            class="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border shadow-md"
        >
            <div class="p-1">
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    :class="
                        cn(
                            'focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none hover:bg-accent hover:text-accent-foreground',
                        )
                    "
                    @click="toggle(option.value)"
                >
                    <span
                        :class="
                            cn(
                                'flex size-4 items-center justify-center rounded-sm border',
                                modelValue.includes(option.value) ? 'bg-primary border-primary text-primary-foreground' : 'border-input',
                            )
                        "
                    >
                        <Check v-if="modelValue.includes(option.value)" class="size-3" />
                    </span>
                    {{ option.label }}
                </button>
            </div>
        </div>
    </div>
</template>
