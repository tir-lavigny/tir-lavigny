<script setup lang="ts">
import WebLayout from '@/layouts/WebLayout.vue';
import type { MemberRegistration } from '@/types';
import { Head } from '@inertiajs/vue3';
import MemberRegistrationForm from './MemberRegistration/MemberRegistrationForm.vue';
import MemberRegistrationSummary from './MemberRegistration/MemberRegistrationSummary.vue';

defineProps<{
    registration: MemberRegistration;
    submitted: boolean;
}>();
</script>

<template>
    <Head title="Inscription" />
    <WebLayout>
        <div class="mx-auto mt-16 w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <MemberRegistrationSummary v-if="submitted" :registration="registration" />
            <MemberRegistrationForm v-else :registration="registration" />
        </div>
    </WebLayout>
</template>
