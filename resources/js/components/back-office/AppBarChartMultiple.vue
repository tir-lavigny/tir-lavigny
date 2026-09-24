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
        tickValues?: (d: T) => Date | string | number;
    }>(),
    {
        tickValues: undefined,
    },
);

const colorKeys = Object.keys(props.config).filter((k) => props.config[k]?.color);

const parseDate = (v: Date | string | number) => (v instanceof Date ? v : new Date(v));

const mappedTickValues = computed(() => (props.tickValues != null ? props.data.map((d) => parseDate(props.tickValues!(d))) : undefined));
</script>

<template>
    <ChartContainer :config="config">
        <VisXYContainer :data="data">
            <VisGroupedBar
                :x="(d: T) => parseDate(x(d))"
                :y="y"
                :color="colorKeys.map((k) => config[k]?.color)"
                :rounded-corners="4"
                bar-padding="0.15"
                group-padding="0"
            />
            <VisAxis
                type="x"
                :x="(d: T) => parseDate(x(d))"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
                :num-ticks="6"
                :tick-format="
                    tickFormat ??
                    ((d: number) => {
                        const date = new Date(d);
                        const day = String(date.getDate()).padStart(2, '0');
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        return `${day}.${month}`;
                    })
                "
                :tick-values="mappedTickValues"
            />
            <VisAxis type="y" :num-ticks="3" :tick-line="false" :domain-line="false" />
            <ChartTooltip />
            <ChartCrosshair
                :x="(d: T) => parseDate(x(d))"
                :y="(d: T) => y[0](d)"
                :template="componentToString(config, ChartTooltipContent, { indicator: 'dashed', hideLabel: true })"
                color="#0000"
            />
        </VisXYContainer>
    </ChartContainer>
</template>
