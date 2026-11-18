<script setup lang="ts">
import WebLayout from '@/layouts/WebLayout.vue';
import type { MemberRegistration } from '@/types';
import EmailVerification from './MemberRegistration/EmailVerification.vue';
import MemberRegistrationForm from './MemberRegistration/MemberRegistrationForm.vue';

defineProps<{
    registration: MemberRegistration;
    submitted: boolean;
    verified: boolean;
    maskedEmail: string | null;
    resendAvailableIn: number;
}>();
</script>

<template>
    <WebLayout>
        <div class="mx-auto mt-16 w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="submitted && verified" class="space-y-4 text-center">
                <h1 class="text-2xl font-bold sm:text-3xl">Merci pour votre inscription</h1>
                <p class="text-muted-foreground">
                    Votre email a été vérifié et votre formulaire nous est parvenu. Nous vous contacterons prochainement.
                </p>
            </div>
            <EmailVerification
                v-else-if="submitted"
                :form-uuid="registration.form_uuid"
                :masked-email="maskedEmail"
                :resend-available-in="resendAvailableIn"
            />
            <MemberRegistrationForm v-else :registration="registration" />
        </div>
    </WebLayout>
</template>
