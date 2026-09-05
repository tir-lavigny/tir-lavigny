<?php

namespace Tests\Unit\Http\Controllers\BackOffice;

use App\Http\Controllers\BackOffice\DavController;
use App\Models\Member;
use App\Services\InfomaniakDavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            [
                'url' => 'http://example.com/john.vcf',
                'etag' => '"1"',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => 'john@example.com',
                'tel' => '+41791234567',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
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
        $this->mockDavService([
            [
                'url' => 'http://example.com/john.vcf',
                'etag' => '"1"',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => 'john@example.com',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
        ]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseHas('members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dav_uid' => 'uid-001',
        ]);
    }

    public function test_import_skips_non_actifs_contacts(): void
    {
        $this->mockDavService([
            [
                'url' => 'http://example.com/archived.vcf',
                'etag' => '"1"',
                'uid' => 'uid-001',
                'fn' => 'Old Person',
                'n' => 'Old;Person;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Archives',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
        ]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 0);
    }

    public function test_import_skips_contacts_with_existing_dav_uid(): void
    {
        Member::factory()->create(['dav_uid' => 'uid-001']);

        $this->mockDavService([
            [
                'url' => 'http://example.com/john.vcf',
                'etag' => '"1"',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
            [
                'url' => 'http://example.com/jane.vcf',
                'etag' => '"2"',
                'uid' => 'uid-002',
                'fn' => 'Smith Jane',
                'n' => 'Smith;Jane;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
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
            [
                'url' => 'http://example.com/john.vcf',
                'etag' => '"1"',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
            [
                'url' => 'http://example.com/jane.vcf',
                'etag' => '"2"',
                'uid' => 'uid-002',
                'fn' => 'Smith Jane',
                'n' => 'Smith;Jane;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
            [
                'url' => 'http://example.com/bob.vcf',
                'etag' => '"3"',
                'uid' => 'uid-003',
                'fn' => 'Brown Bob',
                'n' => 'Brown;Bob;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
        ]);

        $this->controller->importFromDav(app(InfomaniakDavService::class));

        $this->assertDatabaseCount('members', 3);
        $this->assertDatabaseHas('members', ['first_name' => 'John', 'last_name' => 'Doe']);
        $this->assertDatabaseHas('members', ['first_name' => 'Jane', 'last_name' => 'Smith']);
        $this->assertDatabaseHas('members', ['first_name' => 'Bob', 'last_name' => 'Brown']);
    }
}
