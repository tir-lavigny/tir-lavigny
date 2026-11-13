<?php

namespace Tests\Unit\Services;

use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\DavSyncService;
use App\Services\MemberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DavSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeContact(string $uid, string $n, string $category = 'Actifs'): array
    {
        return [
            'url' => "http://example.com/{$uid}.vcf",
            'etag' => '"1"',
            'uid' => $uid,
            'fn' => explode(';', $n)[0].' '.explode(';', $n)[1],
            'n' => $n,
            'email' => '',
            'tel' => '',
            'org' => '',
            'category' => $category,
            'vcard_raw' => 'BEGIN:VCARD',
        ];
    }

    private function mockDav(array $contacts = [], array $extraMethods = []): DavSyncService
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn($contacts);
        foreach ($extraMethods as $method => $return) {
            $davMock->shouldReceive($method)->andReturn($return);
        }

        return new DavSyncService($davMock, new MemberSyncService($davMock));
    }

    private function mockDavThrows(string $message = 'Connection failed'): DavSyncService
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andThrow(new \RuntimeException($message));

        return new DavSyncService($davMock, new MemberSyncService($davMock));
    }

    // ── importFromDav ───────────────────────────────────────────────────────

    public function test_import_creates_members_from_active_contacts(): void
    {
        $service = $this->mockDav([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $result = $service->importFromDav();

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseHas('members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dav_uid' => 'uid-001',
        ]);
    }

    public function test_import_creates_multiple_members(): void
    {
        $service = $this->mockDav([
            $this->makeContact('uid-001', 'Doe;John;;;'),
            $this->makeContact('uid-002', 'Smith;Jane;;;'),
            $this->makeContact('uid-003', 'Brown;Bob;;;'),
        ]);

        $result = $service->importFromDav();

        $this->assertEquals(3, $result['created']);
        $this->assertDatabaseCount('members', 3);
    }

    public function test_import_skips_non_active_contacts(): void
    {
        $service = $this->mockDav([
            $this->makeContact('uid-001', 'Old;Person;;;', 'Archives'),
            $this->makeContact('uid-002', 'Active;Member;;;', 'Actifs'),
        ]);

        $result = $service->importFromDav();

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseHas('members', ['dav_uid' => 'uid-002']);
        $this->assertDatabaseMissing('members', ['dav_uid' => 'uid-001']);
    }

    public function test_import_skips_existing_dav_uids(): void
    {
        Member::factory()->create(['dav_uid' => 'uid-001']);
        $service = $this->mockDav([
            $this->makeContact('uid-001', 'Doe;John;;;'),
            $this->makeContact('uid-002', 'Smith;Jane;;;'),
        ]);

        $result = $service->importFromDav();

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseHas('members', ['dav_uid' => 'uid-002']);
    }

    public function test_import_returns_zero_for_empty_contacts(): void
    {
        $service = $this->mockDav([]);

        $result = $service->importFromDav();

        $this->assertEquals(0, $result['created']);
    }

    public function test_import_parses_name_from_vcard(): void
    {
        $service = $this->mockDav([$this->makeContact('uid-010', 'Dupont;Marie-Claire;;;')]);

        $service->importFromDav();

        $this->assertDatabaseHas('members', [
            'first_name' => 'Marie-Claire',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-010',
        ]);
    }

    public function test_import_throws_when_dav_throws(): void
    {
        $service = $this->mockDavThrows();

        $this->expectException(\RuntimeException::class);
        $service->importFromDav();
    }

    // ── syncPreview ─────────────────────────────────────────────────────────

    public function test_sync_preview_returns_diff_pairs(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $service = $this->mockDav([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $result = $service->syncPreview();

        $this->assertCount(1, $result['pairs']);
        $this->assertEquals($member->id, $result['pairs'][0]['member_id']);
        $this->assertEquals('Pierre', $result['pairs'][0]['member_first_name']);
        $this->assertEquals('Dupont', $result['pairs'][0]['member_last_name']);
        $this->assertEquals('John', $result['pairs'][0]['dav_first_name']);
        $this->assertEquals('Doe', $result['pairs'][0]['dav_last_name']);
    }

    public function test_sync_preview_skips_matching_names(): void
    {
        Member::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dav_uid' => 'uid-001',
        ]);

        $service = $this->mockDav([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $result = $service->syncPreview();

        $this->assertEmpty($result['pairs']);
    }

    public function test_sync_preview_skips_non_active_contacts(): void
    {
        Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $service = $this->mockDav([$this->makeContact('uid-001', 'Doe;John;;;', 'Archives')]);

        $result = $service->syncPreview();

        $this->assertEmpty($result['pairs']);
    }

    public function test_sync_preview_skips_members_without_matching_dav_contact(): void
    {
        Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-999',
        ]);

        $service = $this->mockDav([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $result = $service->syncPreview();

        $this->assertEmpty($result['pairs']);
    }

    public function test_sync_preview_throws_when_dav_throws(): void
    {
        $service = $this->mockDavThrows();

        $this->expectException(\RuntimeException::class);
        $service->syncPreview();
    }

    // ── sync ────────────────────────────────────────────────────────────────

    public function test_sync_updates_member_from_dav_source(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn([
            $this->makeContact('uid-001', 'Doe;John;;;'),
        ]);
        $davMock->shouldReceive('updateMember')->andReturn(true);

        $service = new DavSyncService($davMock, new MemberSyncService($davMock));
        $result = $service->sync([['member_id' => $member->id, 'source' => 'dav']]);

        $this->assertEquals(1, $result['updated']);
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_sync_updates_dav_from_member_source(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn([]);
        $davMock->shouldReceive('updateMember')
            ->with(Mockery::on(fn ($m) => $m->id === $member->id))
            ->andReturn(true);

        $service = new DavSyncService($davMock, new MemberSyncService($davMock));
        $result = $service->sync([['member_id' => $member->id, 'source' => 'member']]);

        $this->assertEquals(1, $result['updated']);
    }

    public function test_sync_returns_zero_for_empty_choices(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $service = new DavSyncService($davMock, new MemberSyncService($davMock));

        $result = $service->sync([]);

        $this->assertEquals(0, $result['updated']);
    }

    public function test_sync_logs_warning_on_failure(): void
    {
        Log::spy();

        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andThrow(new \RuntimeException('DAV error'));

        $service = new DavSyncService($davMock, new MemberSyncService($davMock));
        $result = $service->sync([['member_id' => $member->id, 'source' => 'dav']]);

        $this->assertEquals(0, $result['updated']);
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Pierre',
        ]);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context): bool => $message === 'Failed to sync member'
                && $context['member_id'] === $member->id
                && $context['dav_uid'] === 'uid-001'
                && $context['source'] === 'dav'
        );
    }
}
