<?php

namespace Tests\Unit\Services;

use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\DavSyncService;
use App\Services\MemberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
