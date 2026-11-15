<script setup lang="ts">
import WebLayout from '@/layouts/WebLayout.vue';
import type { MemberRegistration } from '@/types';
import { Head } from '@inertiajs/vue3';
import MemberRegistrationForm from './MemberRegistration/MemberRegistrationForm.vue';

defineProps<{
    registration: MemberRegistration;
    submitted: boolean;
}>();
</script>

<template>
    <Head title="Inscription" />
    <WebLayout>
        <div class="mx-auto mt-16 w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="submitted" class="space-y-4 text-center">
                <h1 class="text-2xl font-bold sm:text-3xl">Merci pour votre inscription</h1>
                <p class="text-muted-foreground">Votre formulaire a bien été envoyé. Nous vous contacterons prochainement.</p>
            </div>
            <MemberRegistrationForm v-else :registration="registration" />
        </div>
    </WebLayout>
</template>
