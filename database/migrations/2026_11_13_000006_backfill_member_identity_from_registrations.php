<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_registrations')
            ->whereNotNull('member_id')
            ->whereNotNull('form_data')
            ->orderBy('id')
            ->chunkById(100, function ($registrations): void {
                foreach ($registrations as $registration) {
                    $formData = json_decode((string) $registration->form_data, true);

                    if (! is_array($formData)) {
                        continue;
                    }

                    $member = DB::table('members')->where('id', $registration->member_id)->first();

                    if (! $member) {
                        continue;
                    }

                    $values = [];

                    foreach (['email', 'tel', 'avs', 'is_swiss_citizen', 'street', 'npa', 'city', 'canton', 'country'] as $field) {
                        if (property_exists($member, $field) && $member->{$field} === null && array_key_exists($field, $formData)) {
                            $values[$field] = $formData[$field];
                        }
                    }

                    if ($values !== []) {
                        DB::table('members')->where('id', $member->id)->update($values);
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};
