<?php

namespace Tests\Unit\Http\Controllers\BackOffice;

use App\Http\Controllers\BackOffice\DavController;
use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\DavSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Response;
use Mockery;
use Tests\TestCase;

class DavControllerTest extends TestCase
{
    use RefreshDatabase;

    private DavController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new DavController;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockDavService(array $contacts): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn($contacts);
        $this->app->instance(DavServiceInterface::class, $davMock);
    }

    private function mockDavServiceThrows(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andThrow(new \RuntimeException('Connection failed'));
        $this->app->instance(DavServiceInterface::class, $davMock);
    }

    private function mockDavSyncService(array $methods = []): Mockery\MockInterface
    {
        $syncMock = Mockery::mock(DavSyncService::class);
        foreach ($methods as $method => $return) {
            $syncMock->shouldReceive($method)->andReturn($return);
        }
        $this->app->instance(DavSyncService::class, $syncMock);

        return $syncMock;
    }

    private function mockDavSyncServiceThrows(string $method, string $message = 'Connection failed'): void
    {
        $syncMock = Mockery::mock(DavSyncService::class);
        $syncMock->shouldReceive($method)->andThrow(new \RuntimeException($message));
        $this->app->instance(DavSyncService::class, $syncMock);
    }

    private function makeContact(string $uid, string $n, string $category = 'Actifs'): array
    {
        $parts = explode(';', $n);

        return [
            'url' => "http://example.com/{$uid}.vcf",
            'etag' => '"1"',
            'uid' => $uid,
            'fn' => ($parts[0] ?? '').' '.($parts[1] ?? ''),
            'n' => $n,
            'email' => '',
            'tel' => '',
            'org' => '',
            'category' => $category,
            'vcard_raw' => 'BEGIN:VCARD',
        ];
    }

    // ── Index method ──

    public function test_index_returns_inertia_response(): void
    {
        $this->mockDavService([]);

        $response = $this->controller->index(app(DavServiceInterface::class));

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_index_passes_contacts_and_dav_uids(): void
    {
        $contacts = [
            $this->makeContact('uid-001', 'Doe;John;;;'),
        ];
        $this->mockDavService($contacts);
        Member::factory()->create(['dav_uid' => 'uid-001']);

        $response = $this->controller->index(app(DavServiceInterface::class));

        $props = $this->getInertiaProps($response);
        $this->assertEquals($contacts, $props['contacts']);
        $this->assertEquals(['uid-001'], $props['existingDavUids']);
    }

    public function test_index_returns_empty_contacts_on_exception(): void
    {
        $this->mockDavServiceThrows();

        $response = $this->controller->index(app(DavServiceInterface::class));

        $props = $this->getInertiaProps($response);
        $this->assertEquals([], $props['contacts']);
        $this->assertEquals([], $props['existingDavUids']);
    }

    private function getInertiaProps(Response $response): array
    {
        $reflection = new \ReflectionClass($response);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);

        return $propsProperty->getValue($response);
    }

    // ── Import method ──

    public function test_import_creates_members_from_actifs_contacts(): void
    {
        $this->mockDavSyncService(['importFromDav' => ['created' => 1]]);

        $this->controller->importFromDav(app(DavSyncService::class));

        $this->assertDatabaseCount('members', 0);
    }

    public function test_import_handles_exception(): void
    {
        $this->mockDavSyncServiceThrows('importFromDav');

        $this->controller->importFromDav(app(DavSyncService::class));

        $this->assertDatabaseCount('members', 0);
    }

    // ── Sync preview method ──

    public function test_sync_preview_returns_diff_pairs(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $pairs = [
            [
                'member_id' => $member->id,
                'member_first_name' => 'Pierre',
                'member_last_name' => 'Dupont',
                'dav_first_name' => 'John',
                'dav_last_name' => 'Doe',
                'dav_uid' => 'uid-001',
            ],
        ];
        $this->mockDavSyncService(['syncPreview' => ['pairs' => $pairs]]);

        $response = $this->controller->syncPreview(app(DavSyncService::class));

        $data = $response->getData(true);
        $this->assertCount(1, $data['pairs']);
        $this->assertEquals($member->id, $data['pairs'][0]['member_id']);
        $this->assertEquals('Pierre', $data['pairs'][0]['member_first_name']);
        $this->assertEquals('Dupont', $data['pairs'][0]['member_last_name']);
        $this->assertEquals('John', $data['pairs'][0]['dav_first_name']);
        $this->assertEquals('Doe', $data['pairs'][0]['dav_last_name']);
    }

    public function test_sync_preview_handles_exception(): void
    {
        $this->mockDavSyncServiceThrows('syncPreview');

        $response = $this->controller->syncPreview(app(DavSyncService::class));

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── Sync method ──

    public function test_sync_updates_member_from_dav(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $this->mockDavSyncService(['sync' => ['updated' => 1]]);

        $request = new Request;
        $request->merge(['choices' => [['member_id' => $member->id, 'source' => 'dav']]]);

        $this->controller->sync(app(DavSyncService::class), $request);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
        ]);
    }

    public function test_sync_updates_dav_from_member(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $this->mockDavSyncService(['sync' => ['updated' => 1]]);

        $request = new Request;
        $request->merge(['choices' => [['member_id' => $member->id, 'source' => 'member']]]);

        $this->controller->sync(app(DavSyncService::class), $request);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
        ]);
    }
}
