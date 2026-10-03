<script setup lang="ts" generic="T">
import type { ChartConfig } from '@/components/ui/chart';

import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import { ChartContainer, ChartCrosshair, ChartTooltip, ChartTooltipContent, componentToString } from '@/components/ui/chart';

const props = withDefaults(
    defineProps<{
        data: T[];
        config: ChartConfig;
        x: (d: T) => Date | string | number;
        y: ((d: T) => number)[];
        tickFormat?: (d: number) => string;
        ordinal?: boolean;
    }>(),
    {
        ordinal: true,
    },
);

const colorKeys = Object.keys(props.config).filter((k) => props.config[k]?.color);

const tickLabels = computed(() =>
    props.data.map((d, i) => ({
        index: i,
        label: props.tickFormat ? props.tickFormat(parseDateToNumber(props.x(d))) : formatDefault(parseDateToNumber(props.x(d))),
    })),
);

function parseDateToNumber(v: Date | string | number): number {
    if (v instanceof Date) return v.getTime();
    if (typeof v === 'number') return v;
    return new Date(v).getTime();
}

function formatDefault(ts: number): string {
    const date = new Date(ts);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}.${month}`;
}

const xAccessor = computed(() => {
    if (props.ordinal) {
        return (_d: T, i: number) => i;
    }
    return (_d: T, i: number) => parseDateToNumber(props.x(_d));
});

const tickValues = computed(() => {
    if (!props.ordinal) return undefined;
    return props.data.map((_, i) => i);
});

const tickFormatFn = (v: number) => {
    if (props.ordinal) {
        return tickLabels.value[v]?.label ?? '';
    }
    return formatDefault(v);
};
</script>

<template>
    <ChartContainer :config="config">
        <VisXYContainer :data="data" class="h-full">
            <VisGroupedBar
                :x="xAccessor"
                :y="[...y]"
                :color="colorKeys.map((k) => config[k]?.color)"
                :rounded-corners="4"
                bar-padding="0"
                group-padding="0.3"
            />
            <VisAxis
                type="x"
                :x="xAccessor"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
                :num-ticks="data.length"
                :tick-format="tickFormatFn"
                :tick-values="tickValues"
            />
            <VisAxis type="y" :num-ticks="3" :tick-line="false" :domain-line="false" />
            <ChartTooltip />
            <ChartCrosshair
                :x="xAccessor"
                :y="(d: T, i: number) => y[0](d)"
                :template="componentToString(config, ChartTooltipContent, { indicator: 'dashed', hideLabel: true })"
                color="#0000"
            />
        </VisXYContainer>
    </ChartContainer>
</template>
