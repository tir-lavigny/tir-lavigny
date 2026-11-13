<?php

namespace Tests\Unit\Services;

use App\MemberStatus;
use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\MemberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MemberSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function contact(array $overrides = []): array
    {
        return array_merge([
            'url' => 'https://dav.example.com/uid-1.vcf',
            'etag' => '"1"',
            'uid' => 'uid-1',
            'fn' => 'Dupont Jean',
            'n' => 'Dupont;Jean;;;',
            'email' => 'jean.dupont@example.com',
            'tel' => '+41 79 123 45 67',
            'org' => '',
            'category' => 'Actifs',
            'vcard_raw' => 'BEGIN:VCARD',
        ], $overrides);
    }

    private function mockDav(array $contacts = []): DavServiceInterface
    {
        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('getContacts')->andReturn($contacts);

        return $dav;
    }

    public function test_preview_returns_field_level_differences(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'old@example.com',
            'tel' => '+41 79 000 00 00',
            'dav_uid' => 'uid-1',
            'status' => MemberStatus::ACTIVE,
        ]);

        $service = new MemberSyncService($this->mockDav([
            $this->contact(['email' => 'new@example.com']),
        ]));

        $preview = $service->preview();
        $row = $preview['members'][0];

        $this->assertSame($member->id, $row['member_id']);
        $this->assertSame('old@example.com', $row['fields']['email']['member']);
        $this->assertSame('new@example.com', $row['fields']['email']['dav']);
        $this->assertFalse($row['fields']['email']['conflict']);
        $this->assertSame('Jean', $row['fields']['first_name']['member']);
        $this->assertSame('Jean', $row['fields']['first_name']['dav']);
    }

    public function test_preview_flags_field_changed_on_both_sides(): void
    {
        Member::factory()->create([
            'email' => 'member@example.com',
            'dav_uid' => 'uid-1',
            'dav_synced_snapshot' => [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'base@example.com',
                'tel' => null,
                'status' => 'active',
                'archived' => false,
            ],
        ]);

        $service = new MemberSyncService($this->mockDav([
            $this->contact(['email' => 'dav@example.com']),
        ]));

        $preview = $service->preview();

        $this->assertTrue($preview['members'][0]['fields']['email']['conflict']);
    }

    public function test_preview_returns_email_for_unlinked_contacts(): void
    {
        $service = new MemberSyncService($this->mockDav([
            $this->contact(['uid' => 'unknown', 'email' => 'john.doe@example.com']),
        ]));

        $preview = $service->preview();

        $this->assertCount(1, $preview['unlinked_contacts']);
        $this->assertSame('john.doe@example.com', $preview['unlinked_contacts'][0]['email']);
        $this->assertSame('active', $preview['unlinked_contacts'][0]['suggested_status']);
    }

    public function test_preview_lists_members_without_dav_uid(): void
    {
        $member = Member::factory()->create(['dav_uid' => null]);

        $service = new MemberSyncService($this->mockDav());

        $preview = $service->preview();

        $this->assertCount(1, $preview['unlinked_members']);
        $this->assertSame($member->id, $preview['unlinked_members'][0]['member_id']);
    }

    public function test_preview_marks_missing_contact(): void
    {
        Member::factory()->create(['dav_uid' => 'missing']);

        $service = new MemberSyncService($this->mockDav());

        $preview = $service->preview();

        $this->assertTrue($preview['members'][0]['missing_contact']);
    }

    public function test_apply_uses_dav_value_when_chosen(): void
    {
        $member = Member::factory()->create([
            'email' => 'old@example.com',
            'dav_uid' => 'uid-1',
        ]);

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('getContacts')->once()->andReturn([$this->contact(['email' => 'new@example.com'])]);
        $dav->shouldReceive('updateMember')->once()->andReturn(true);

        $service = new MemberSyncService($dav);
        $result = $service->apply([
            ['member_id' => $member->id, 'fields' => ['email' => 'dav', 'first_name' => 'member']],
        ]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('new@example.com', $member->fresh()->email);
        $this->assertSame('new@example.com', $member->fresh()->dav_synced_snapshot['email']);
    }

    public function test_apply_keeps_member_value_when_chosen_and_pushes_it(): void
    {
        $member = Member::factory()->create([
            'email' => 'db@example.com',
            'dav_uid' => 'uid-1',
        ]);

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('getContacts')->once()->andReturn([$this->contact(['email' => 'dav@example.com'])]);
        $dav->shouldReceive('updateMember')
            ->once()
            ->with(Mockery::on(fn (Member $pushed) => $pushed->id === $member->id))
            ->andReturn(true);

        $service = new MemberSyncService($dav);
        $result = $service->apply([
            ['member_id' => $member->id, 'fields' => ['email' => 'member']],
        ]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('db@example.com', $member->fresh()->email);
    }

    public function test_apply_archives_member_from_dav_choice(): void
    {
        $member = Member::factory()->create(['dav_uid' => 'uid-1']);

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('getContacts')->once()->andReturn([
            $this->contact(['category' => 'Archives']),
        ]);
        $dav->shouldReceive('updateMember')->once()->andReturn(true);

        $service = new MemberSyncService($dav);
        $service->apply([
            ['member_id' => $member->id, 'fields' => ['archived' => 'dav']],
        ]);

        $this->assertSoftDeleted($member);
    }

    public function test_apply_skips_unknown_member(): void
    {
        $dav = $this->mockDav();

        $service = new MemberSyncService($dav);

        $this->assertSame(['updated' => 0], $service->apply([
            ['member_id' => 999999, 'fields' => ['email' => 'dav']],
        ]));
    }

    public function test_push_creates_contact_when_dav_uid_missing(): void
    {
        $member = Member::factory()->create(['dav_uid' => null]);

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('createContactFromMember')
            ->once()
            ->andReturn(['uid' => 'new-uid', 'url' => 'https://dav.example.com/new-uid.vcf']);

        $service = new MemberSyncService($dav);
        $result = $service->push($member);

        $this->assertTrue($result['success']);
        $this->assertSame('new-uid', $member->fresh()->dav_uid);
        $this->assertNotNull($member->fresh()->dav_synced_at);
        $this->assertNull($member->fresh()->dav_sync_error);
    }

    public function test_push_returns_failure_and_stores_error(): void
    {
        $member = Member::factory()->create(['dav_uid' => 'uid-1']);

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('updateMember')->once()->andReturn(false);

        $service = new MemberSyncService($dav);
        $result = $service->push($member);

        $this->assertFalse($result['success']);
        $this->assertSame('Impossible de mettre à jour le contact DAV.', $result['error']);
        $this->assertSame($result['error'], $member->fresh()->dav_sync_error);
    }

    public function test_push_catches_dav_exception(): void
    {
        $member = Member::factory()->create(['dav_uid' => 'uid-1']);

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('updateMember')->once()->andThrow(new \RuntimeException('DAV down'));

        $service = new MemberSyncService($dav);
        $result = $service->push($member);

        $this->assertFalse($result['success']);
        $this->assertSame('DAV down', $result['error']);
    }

    public function test_link_sets_dav_uid_and_clears_sync_state(): void
    {
        $member = Member::factory()->create([
            'dav_uid' => null,
            'dav_sync_error' => 'old error',
            'dav_synced_snapshot' => ['email' => 'old@example.com'],
        ]);

        $service = new MemberSyncService($this->mockDav([
            $this->contact(['uid' => 'uid-linked']),
        ]));
        $result = $service->link($member, 'uid-linked');

        $member->refresh();

        $this->assertTrue($result['success']);
        $this->assertSame('uid-linked', $member->dav_uid);
        $this->assertNull($member->dav_synced_at);
        $this->assertNull($member->dav_synced_snapshot);
        $this->assertNull($member->dav_sync_error);
    }

    public function test_link_rejects_unknown_contact(): void
    {
        $member = Member::factory()->create(['dav_uid' => null]);

        $service = new MemberSyncService($this->mockDav());
        $result = $service->link($member, 'missing-uid');

        $this->assertFalse($result['success']);
        $this->assertNull($member->fresh()->dav_uid);
    }

    public function test_preview_suggests_member_by_unique_email(): void
    {
        $member = Member::factory()->create([
            'email' => 'jean.dupont@example.com',
            'dav_uid' => null,
        ]);

        $service = new MemberSyncService($this->mockDav([
            $this->contact(['uid' => 'unknown-uid', 'email' => 'jean.dupont@example.com']),
        ]));

        $preview = $service->preview();

        $this->assertSame($member->id, $preview['unlinked_contacts'][0]['suggested_member_id']);
    }

    public function test_preview_does_not_suggest_member_when_email_is_ambiguous(): void
    {
        Member::factory()->create(['email' => 'shared@example.com', 'dav_uid' => null]);
        Member::factory()->create(['email' => 'shared@example.com', 'dav_uid' => null]);

        $service = new MemberSyncService($this->mockDav([
            $this->contact(['uid' => 'unknown-uid', 'email' => 'shared@example.com']),
        ]));

        $preview = $service->preview();

        $this->assertNull($preview['unlinked_contacts'][0]['suggested_member_id']);
    }

    public function test_preview_suggests_contact_for_unlinked_member(): void
    {
        $member = Member::factory()->create([
            'email' => 'jean.dupont@example.com',
            'dav_uid' => null,
        ]);

        $service = new MemberSyncService($this->mockDav([
            $this->contact(['uid' => 'unknown-uid', 'email' => 'jean.dupont@example.com']),
        ]));

        $preview = $service->preview();

        $this->assertSame('unknown-uid', $preview['unlinked_members'][0]['suggested_contact_uid']);
        $this->assertSame($member->id, $preview['unlinked_members'][0]['member_id']);
    }

    public function test_create_from_contact_maps_passive_status(): void
    {
        $service = new MemberSyncService($this->mockDav());

        $member = $service->createFromContact($this->contact([
            'uid' => 'passive-uid',
            'category' => 'Passifs',
        ]));

        $this->assertSame(MemberStatus::PASSIVE, $member->status);
        $this->assertSame('passive-uid', $member->dav_uid);
        $this->assertNull($member->deleted_at);
    }

    public function test_create_from_contact_maps_archive_category(): void
    {
        $service = new MemberSyncService($this->mockDav());

        $member = $service->createFromContact($this->contact([
            'uid' => 'archived-uid',
            'category' => 'Archives',
        ]));

        $this->assertTrue($member->trashed());
    }

    public function test_create_from_contact_defaults_unknown_category_to_active(): void
    {
        $service = new MemberSyncService($this->mockDav());

        $member = $service->createFromContact($this->contact([
            'uid' => 'unknown-uid',
            'category' => 'Something else',
        ]));

        $this->assertSame(MemberStatus::ACTIVE, $member->status);
    }

    public function test_create_from_contact_parses_multiple_categories(): void
    {
        $service = new MemberSyncService($this->mockDav());

        $member = $service->createFromContact($this->contact([
            'uid' => 'multiple-uid',
            'category' => 'Actifs, Passifs',
        ]));

        $this->assertSame(MemberStatus::PASSIVE, $member->status);
    }
}
