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
        y: (d: T) => number;
        tickFormat?: (d: number) => string;
        yTickFormat?: (d: number) => string;
        tickValues?: (d: T) => Date | string | number;
    }>(),
    {
        tickValues: undefined,
        yTickFormat: undefined,
    },
);

const colorKey = Object.keys(props.config).find((k) => props.config[k]?.color) ?? Object.keys(props.config)[0];

const parseDate = (v: Date | string | number) => {
    if (v instanceof Date) return v;
    if (typeof v === 'number') return new Date(v);
    const parsed = new Date(v);
    return isNaN(parsed.getTime()) ? v : parsed;
};

const isDateLike = (v: Date | string | number) => v instanceof Date || (typeof v === 'string' && !isNaN(new Date(v).getTime()));

const mappedTickValues = computed(() => (props.tickValues != null ? props.data.map((d) => props.tickValues!(d)) : undefined));
</script>

<template>
    <ChartContainer :config="config">
        <VisXYContainer :data="data" :margin="{ left: -24 }" :y-domain="[0, undefined]">
            <VisGroupedBar :x="(d: T) => (isDateLike(x(d)) ? parseDate(x(d)) : x(d))" :y="y" :color="config[colorKey]?.color" :rounded-corners="10" />
            <VisAxis
                type="x"
                :x="(d: T) => (isDateLike(x(d)) ? parseDate(x(d)) : x(d))"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
                :num-ticks="6"
                :tick-format="
                    tickFormat ??
                    ((d: number) => {
                        const date = new Date(d);
                        return date.toLocaleDateString('fr-CH', {
                            month: 'short',
                        });
                    })
                "
                :tick-values="mappedTickValues"
            />
            <VisAxis type="y" :num-ticks="3" :tick-line="false" :domain-line="false" :tick-format="yTickFormat" />
            <ChartTooltip />
            <ChartCrosshair
                :x="(d: T) => (isDateLike(x(d)) ? parseDate(x(d)) : x(d))"
                :y="(d: T) => y(d)"
                :template="componentToString(config, ChartTooltipContent, { hideLabel: true })"
                color="#0000"
            />
        </VisXYContainer>
    </ChartContainer>
</template>
