<?php

namespace Tests\Unit\Http\Controllers\BackOffice;

use App\Http\Controllers\BackOffice\DavController;
use App\Models\Member;
use App\Services\InfomaniakDavService;
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
        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('getContacts')->andReturn($contacts);
        $this->app->instance(InfomaniakDavService::class, $davMock);
    }

    private function mockDavServiceThrows(): void
    {
        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('getContacts')->andThrow(new \RuntimeException('Connection failed'));
        $this->app->instance(InfomaniakDavService::class, $davMock);
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

        $response = $this->controller->index(app(InfomaniakDavService::class));

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_index_passes_contacts_and_dav_uids(): void
    {
        $contacts = [
            $this->makeContact('uid-001', 'Doe;John;;;'),
        ];
        $this->mockDavService($contacts);
        Member::factory()->create(['dav_uid' => 'uid-001']);

        $response = $this->controller->index(app(InfomaniakDavService::class));

        $props = $this->getInertiaProps($response);
        $this->assertEquals($contacts, $props['contacts']);
        $this->assertEquals(['uid-001'], $props['existingDavUids']);
    }

    public function test_index_returns_empty_contacts_on_exception(): void
    {
        $this->mockDavServiceThrows();

        $response = $this->controller->index(app(InfomaniakDavService::class));

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
        $this->mockDavService([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseHas('members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dav_uid' => 'uid-001',
        ]);
    }

    public function test_import_skips_non_actifs_contacts(): void
    {
        $this->mockDavService([$this->makeContact('uid-001', 'Old;Person;;;', 'Archives')]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 0);
    }

    public function test_import_skips_contacts_with_existing_dav_uid(): void
    {
        Member::factory()->create(['dav_uid' => 'uid-001']);

        $this->mockDavService([
            $this->makeContact('uid-001', 'Doe;John;;;'),
            $this->makeContact('uid-002', 'Smith;Jane;;;'),
        ]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseMissing('members', ['dav_uid' => 'uid-001', 'first_name' => 'John']);
        $this->assertDatabaseHas('members', ['dav_uid' => 'uid-002', 'first_name' => 'Jane']);
    }

    public function test_import_handles_empty_contacts(): void
    {
        $this->mockDavService([]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 0);
    }

    public function test_import_handles_exception(): void
    {
        $this->mockDavServiceThrows();

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 0);
    }

    public function test_import_creates_multiple_members(): void
    {
        $this->mockDavService([
            $this->makeContact('uid-001', 'Doe;John;;;'),
            $this->makeContact('uid-002', 'Smith;Jane;;;'),
            $this->makeContact('uid-003', 'Brown;Bob;;;'),
        ]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 3);
        $this->assertDatabaseHas('members', ['first_name' => 'John', 'last_name' => 'Doe']);
        $this->assertDatabaseHas('members', ['first_name' => 'Jane', 'last_name' => 'Smith']);
        $this->assertDatabaseHas('members', ['first_name' => 'Bob', 'last_name' => 'Brown']);
    }

    // ── Sync preview method ──

    public function test_sync_preview_returns_diff_pairs(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $this->mockDavService([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $request = Request::create('/sync/preview', 'GET');
        $response = $this->controller->syncPreview(app(InfomaniakDavService::class));

        $data = $response->getData(true);
        $this->assertCount(1, $data['pairs']);
        $this->assertEquals($member->id, $data['pairs'][0]['member_id']);
        $this->assertEquals('Pierre', $data['pairs'][0]['member_first_name']);
        $this->assertEquals('Dupont', $data['pairs'][0]['member_last_name']);
        $this->assertEquals('John', $data['pairs'][0]['dav_first_name']);
        $this->assertEquals('Doe', $data['pairs'][0]['dav_last_name']);
    }

    public function test_sync_preview_skips_matching_pairs(): void
    {
        Member::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dav_uid' => 'uid-001',
        ]);

        $this->mockDavService([$this->makeContact('uid-001', 'Doe;John;;;')]);

        $response = $this->controller->syncPreview(app(InfomaniakDavService::class));

        $data = $response->getData(true);
        $this->assertCount(0, $data['pairs']);
    }

    public function test_sync_preview_skips_non_actifs(): void
    {
        Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $this->mockDavService([$this->makeContact('uid-001', 'Doe;John;;;', 'Archives')]);

        $response = $this->controller->syncPreview(app(InfomaniakDavService::class));

        $data = $response->getData(true);
        $this->assertCount(0, $data['pairs']);
    }

    public function test_sync_preview_handles_exception(): void
    {
        $this->mockDavServiceThrows();

        $response = $this->controller->syncPreview(app(InfomaniakDavService::class));

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

        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('getAddressbookUrl')->andReturn('http://localhost/');
        $davMock->shouldReceive('getContact')
            ->with('http://localhost/uid-001.vcf')
            ->andReturn([
                'url' => 'http://localhost/uid-001.vcf',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'vcard_raw' => 'BEGIN:VCARD',
            ]);
        $this->app->instance(InfomaniakDavService::class, $davMock);

        $request = new Request;
        $request->merge(['choices' => [['member_id' => $member->id, 'source' => 'dav']]]);

        $this->controller->sync(app(InfomaniakDavService::class), $request);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_sync_updates_dav_from_member(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('updateMember')
            ->with(Mockery::on(fn ($m) => $m->id === $member->id))
            ->andReturn(true);
        $this->app->instance(InfomaniakDavService::class, $davMock);

        $request = new Request;
        $request->merge(['choices' => [['member_id' => $member->id, 'source' => 'member']]]);

        $this->controller->sync(app(InfomaniakDavService::class), $request);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
        ]);
    }
}
