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
        parseAsDate?: boolean;
    }>(),
    {
        tickValues: undefined,
        yTickFormat: undefined,
        parseAsDate: true,
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

const toX = (v: Date | string | number) => (props.parseAsDate && isDateLike(v) ? parseDate(v) : v);

const mappedTickValues = computed(() => {
    if (props.tickValues == null) return undefined;
    return props.data.map((d) => toX(props.tickValues!(d)));
});
</script>

<template>
    <ChartContainer :config="config">
        <VisXYContainer :data="data" class="h-full" :y-domain="[0, undefined]">
            <VisGroupedBar :x="(d: T) => toX(x(d))" :y="y" :color="config[colorKey]?.color" :rounded-corners="10" bar-padding="0.1" />
            <VisAxis
                type="x"
                :x="(d: T) => toX(x(d))"
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
            <VisAxis type="y" position="left" :num-ticks="3" :tick-line="false" :domain-line="false" :tick-format="yTickFormat" />
            <ChartTooltip />
            <ChartCrosshair
                :x="(d: T) => toX(x(d))"
                :y="(d: T) => y(d)"
                :template="componentToString(config, ChartTooltipContent, { hideLabel: true })"
                color="#0000"
            />
        </VisXYContainer>
    </ChartContainer>
</template>
