<script setup lang="ts">
import { P } from '@/components/typography/texts';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import { Location } from '@/types';
import { Edit } from 'lucide-vue-next';

defineProps<{
    locations?: Location[];
}>();

defineEmits<{
    (e: 'edit-location', id: number): void;
}>();
</script>

<template>
    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card v-for="location in locations" :key="location.id">
            <CardHeader class="flex items-center justify-between">
                <CardTitle>{{ location.name }}</CardTitle>
                <Edit
                    class="text-muted-foreground hover:text-primary h-4 w-4 cursor-pointer transition-colors"
                    @click="$emit('edit-location', location.id)"
                />
            </CardHeader>
            <CardContent>
                <P>{{ location.address }}</P>
                <div class="text-muted-foreground">
                    <p><strong>Latitude:</strong> {{ location.latitude }}</p>
                    <p><strong>Longitude:</strong> {{ location.longitude }}</p>
                </div>
            </CardContent>
            <CardFooter class="text-muted-foreground flex justify-between text-xs">
                <p><strong>Crée le:</strong> {{ formatDate(location.created_at, 'DD-MM-YYYY HH:mm') }}</p>
                <p><strong>Mise à jour le:</strong> {{ formatDate(location.updated_at, 'DD-MM-YYYY HH:mm') }}</p>
            </CardFooter>
        </Card>
    </div>
</template>

<style scoped></style>
