<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;
use Illuminate\Support\Facades\DB;

class CreateMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{member: Member, success: bool, error: string|null} */
    public function execute(array $data): array
    {
        $member = DB::transaction(fn (): Member => Member::create($data));

        $result = $this->sync->push($member);

        return [
            'member' => $member,
            'success' => $result['success'],
            'error' => $result['error'],
        ];
    }
}
