<?php

namespace Tests\Feature\BackOffice;

use App\Models\Member;
use App\Models\User;
use App\Services\InfomaniakDavService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class DavControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user = User::factory()->create();
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

    private function mockDavServiceThrows(string $message = 'Connection failed'): void
    {
        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('getContacts')->andThrow(new \RuntimeException($message));
        $this->app->instance(InfomaniakDavService::class, $davMock);
    }

    // ── Index tests ──

    public function test_index_requires_authentication(): void
    {
        $this->get(route('bo.dav.index'))->assertRedirect();
    }

    public function test_index_requires_manage_members_permission(): void
    {
        $this->actingAs($this->user);
        $this->get(route('bo.dav.index'))->assertForbidden();
    }

    public function test_index_returns_ok_with_contacts(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavService([
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
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
        ]);

        $this->get(route('bo.dav.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Dav/Index')
                ->has('contacts', 1)
                ->has('existingDavUids')
            );
    }

    public function test_index_passes_existing_dav_uids(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavService([]);

        Member::factory()->create(['dav_uid' => 'uid-001']);
        Member::factory()->create(['dav_uid' => 'uid-002']);
        Member::factory()->create(['dav_uid' => null]);

        $this->get(route('bo.dav.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('existingDavUids', ['uid-001', 'uid-002'])
            );
    }

    public function test_index_renders_empty_when_dav_throws(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavServiceThrows();

        $this->get(route('bo.dav.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts', [])
                ->has('existingDavUids')
            );
    }

    // ── Import tests ──

    public function test_import_requires_authentication(): void
    {
        $this->post(route('bo.dav.import'))->assertRedirect();
    }

    public function test_import_requires_manage_members_permission(): void
    {
        $this->actingAs($this->user);
        $this->post(route('bo.dav.import'))->assertForbidden();
    }

    public function test_import_creates_members_from_actifs_contacts(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavService([
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
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
            [
                'url' => 'http://example.com/jane.vcf',
                'etag' => '"2"',
                'uid' => 'uid-002',
                'fn' => 'Smith Jane',
                'n' => 'Smith;Jane;;;',
                'email' => 'jane@example.com',
                'tel' => '+41797654321',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
        ]);

        $this->post(route('bo.dav.import'))->assertRedirect();

        $this->assertDatabaseHas('members', ['first_name' => 'John', 'last_name' => 'Doe', 'dav_uid' => 'uid-001']);
        $this->assertDatabaseHas('members', ['first_name' => 'Jane', 'last_name' => 'Smith', 'dav_uid' => 'uid-002']);
        $this->assertDatabaseCount('members', 2);
    }

    public function test_import_skips_contacts_with_existing_dav_uid(): void
    {
        $this->actingAs($this->admin);
        Member::factory()->create(['dav_uid' => 'uid-001']);

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
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
            [
                'url' => 'http://example.com/jane.vcf',
                'etag' => '"2"',
                'uid' => 'uid-002',
                'fn' => 'Smith Jane',
                'n' => 'Smith;Jane;;;',
                'email' => 'jane@example.com',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
        ]);

        $this->post(route('bo.dav.import'))->assertRedirect();

        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseHas('members', ['first_name' => 'Jane', 'last_name' => 'Smith', 'dav_uid' => 'uid-002']);
    }

    public function test_import_skips_non_actifs_contacts(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavService([
            [
                'url' => 'http://example.com/archived.vcf',
                'etag' => '"1"',
                'uid' => 'uid-001',
                'fn' => 'Old Person',
                'n' => 'Old;Person;;;',
                'email' => 'old@example.com',
                'tel' => '',
                'org' => '',
                'category' => 'Archives',
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Archives\r\nEND:VCARD",
            ],
            [
                'url' => 'http://example.com/uncategorized.vcf',
                'etag' => '"2"',
                'uid' => 'uid-002',
                'fn' => 'No Category',
                'n' => 'No;Category;;;',
                'email' => 'no@example.com',
                'tel' => '',
                'org' => '',
                'category' => '',
                'vcard_raw' => "BEGIN:VCARD\r\nEND:VCARD",
            ],
            [
                'url' => 'http://example.com/active.vcf',
                'etag' => '"3"',
                'uid' => 'uid-003',
                'fn' => 'Active Member',
                'n' => 'Active;Member;;;',
                'email' => 'active@example.com',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
        ]);

        $this->post(route('bo.dav.import'))->assertRedirect();

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseHas('members', ['first_name' => 'Member', 'last_name' => 'Active', 'dav_uid' => 'uid-003']);
    }

    public function test_import_handles_dav_exception(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavServiceThrows('Connection timeout');

        $this->post(route('bo.dav.import'))->assertRedirect();
        $this->assertDatabaseCount('members', 0);
    }

    public function test_import_creates_member_with_correct_name_parsing(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavService([
            [
                'url' => 'http://example.com/vcf',
                'etag' => '"1"',
                'uid' => 'uid-010',
                'fn' => 'Dupont Marie-Claire',
                'n' => 'Dupont;Marie-Claire;;;',
                'email' => 'mc@example.com',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
        ]);

        $this->post(route('bo.dav.import'))->assertRedirect();

        $this->assertDatabaseHas('members', [
            'first_name' => 'Marie-Claire',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-010',
        ]);
    }

    public function test_import_redirects_back_with_success_flash(): void
    {
        $this->actingAs($this->admin);
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
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Actifs\r\nEND:VCARD",
            ],
        ]);

        $this->post(route('bo.dav.import'))
            ->assertRedirect()
            ->assertSessionHas('success', '1 membre(s) créé(s) avec succès.');
    }
}
