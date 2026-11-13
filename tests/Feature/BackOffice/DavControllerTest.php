<?php

namespace Tests\Feature\BackOffice;

use App\Models\Member;
use App\Models\User;
use App\Services\DavServiceInterface;
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
        $this->user->givePermissionTo('access backoffice');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockDavService(array $contacts, array $extraMethods = []): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn($contacts);
        foreach ($extraMethods as $method => $return) {
            $davMock->shouldReceive($method)->andReturn($return);
        }
        $this->app->instance(DavServiceInterface::class, $davMock);
    }

    private function mockDavServiceThrows(string $message = 'Connection failed'): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andThrow(new \RuntimeException($message));
        $this->app->instance(DavServiceInterface::class, $davMock);
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

    // ── Sync preview tests ──

    public function test_sync_preview_requires_authentication(): void
    {
        $this->get(route('bo.dav.sync.preview'))->assertRedirect();
    }

    public function test_sync_preview_requires_manage_members_permission(): void
    {
        $this->actingAs($this->user);
        $this->get(route('bo.dav.sync.preview'))->assertForbidden();
    }

    public function test_sync_preview_returns_paired_diffs(): void
    {
        $this->actingAs($this->admin);

        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

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

        $response = $this->getJson(route('bo.dav.sync.preview'));

        $response->assertOk();
        $response->assertJsonPath('members.0.member_id', $member->id);
        $response->assertJsonPath('members.0.fields.first_name.member', 'Pierre');
        $response->assertJsonPath('members.0.fields.first_name.dav', 'John');
        $response->assertJsonPath('members.0.fields.last_name.member', 'Dupont');
        $response->assertJsonPath('members.0.fields.last_name.dav', 'Doe');
    }

    public function test_sync_preview_skips_matching_pairs(): void
    {
        $this->actingAs($this->admin);

        Member::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => null,
            'tel' => null,
            'dav_uid' => 'uid-001',
        ]);

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

        $response = $this->getJson(route('bo.dav.sync.preview'));

        $response->assertOk();
        $response->assertJsonPath('members', []);
    }

    public function test_sync_preview_includes_archived_contacts(): void
    {
        $this->actingAs($this->admin);

        Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'email' => null,
            'tel' => null,
            'dav_uid' => 'uid-001',
        ]);

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
                'category' => 'Archives',
                'vcard_raw' => "BEGIN:VCARD\r\nCATEGORIES:Archives\r\nEND:VCARD",
            ],
        ]);

        $response = $this->getJson(route('bo.dav.sync.preview'));

        $response->assertOk();
        $response->assertJsonPath('members.0.fields.archived.dav', true);
    }

    public function test_sync_preview_handles_dav_exception(): void
    {
        $this->actingAs($this->admin);
        $this->mockDavServiceThrows();

        $response = $this->getJson(route('bo.dav.sync.preview'));

        $response->assertStatus(500);
    }

    // ── Sync tests ──

    public function test_sync_requires_authentication(): void
    {
        $this->post(route('bo.dav.sync'), ['choices' => [['member_id' => 1, 'fields' => ['first_name' => 'member', 'last_name' => 'member', 'email' => 'member', 'tel' => 'member', 'status' => 'member', 'archived' => 'member']]]])->assertRedirect();
    }

    public function test_sync_requires_manage_members_permission(): void
    {
        $this->actingAs($this->user);
        $this->post(route('bo.dav.sync'), ['choices' => [['member_id' => 1, 'fields' => ['first_name' => 'member', 'last_name' => 'member', 'email' => 'member', 'tel' => 'member', 'status' => 'member', 'archived' => 'member']]]])->assertForbidden();
    }

    public function test_sync_updates_member_from_dav_source(): void
    {
        $this->actingAs($this->admin);

        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn([
            [
                'url' => 'http://localhost/uid-001.vcf',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
        ]);
        $davMock->shouldReceive('updateMember')->andReturn(true);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $this->post(route('bo.dav.sync'), [
            'choices' => [['member_id' => $member->id, 'fields' => ['first_name' => 'dav', 'last_name' => 'dav', 'email' => 'dav', 'tel' => 'dav', 'status' => 'dav', 'archived' => 'dav']]],
        ])->assertRedirect();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_sync_updates_dav_from_member_source(): void
    {
        $this->actingAs($this->admin);

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
        $this->app->instance(DavServiceInterface::class, $davMock);

        $this->post(route('bo.dav.sync'), [
            'choices' => [['member_id' => $member->id, 'fields' => ['first_name' => 'member', 'last_name' => 'member', 'email' => 'member', 'tel' => 'member', 'status' => 'member', 'archived' => 'member']]],
        ])->assertRedirect();
    }

    public function test_sync_redirects_with_success_flash(): void
    {
        $this->actingAs($this->admin);

        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'uid-001',
        ]);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn([
            [
                'url' => 'http://localhost/uid-001.vcf',
                'uid' => 'uid-001',
                'fn' => 'Doe John',
                'n' => 'Doe;John;;;',
                'email' => '',
                'tel' => '',
                'org' => '',
                'category' => 'Actifs',
                'vcard_raw' => 'BEGIN:VCARD',
            ],
        ]);
        $davMock->shouldReceive('updateMember')->andReturn(true);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $this->post(route('bo.dav.sync'), [
            'choices' => [['member_id' => $member->id, 'fields' => ['first_name' => 'dav', 'last_name' => 'dav', 'email' => 'dav', 'tel' => 'dav', 'status' => 'dav', 'archived' => 'dav']]],
        ])->assertRedirect()
            ->assertSessionHas('success', '1 membre(s) synchronisé(s) avec succès.');
    }

    public function test_link_requires_authentication(): void
    {
        $this->post(route('bo.dav.link'), ['member_id' => 1, 'dav_uid' => 'uid-001'])->assertRedirect();
    }

    public function test_link_requires_manage_members_permission(): void
    {
        $this->actingAs($this->user);

        $this->post(route('bo.dav.link'), ['member_id' => 1, 'dav_uid' => 'uid-001'])->assertForbidden();
    }

    public function test_link_links_member_to_contact(): void
    {
        $this->actingAs($this->admin);

        $member = Member::factory()->create(['dav_uid' => null]);

        $this->mockDavService([
            [
                'url' => 'http://localhost/uid-001.vcf',
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

        $this->post(route('bo.dav.link'), [
            'member_id' => $member->id,
            'dav_uid' => 'uid-001',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('uid-001', $member->fresh()->dav_uid);
    }

    public function test_link_rejects_unknown_contact(): void
    {
        $this->actingAs($this->admin);

        $member = Member::factory()->create(['dav_uid' => null]);
        $this->mockDavService([]);

        $this->post(route('bo.dav.link'), [
            'member_id' => $member->id,
            'dav_uid' => 'missing',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNull($member->fresh()->dav_uid);
    }

    public function test_sync_recreates_missing_dav_contact_with_empty_fields(): void
    {
        $this->actingAs($this->admin);

        $member = Member::factory()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Dupont',
            'dav_uid' => 'stale-uid',
        ]);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('getContacts')->andReturn([]);
        $davMock->shouldReceive('createContactFromMember')
            ->once()
            ->andReturn([
                'uid' => 'new-uid',
                'url' => 'http://localhost/new-uid.vcf',
                'fn' => 'Dupont Pierre',
                'vcard_raw' => 'BEGIN:VCARD',
            ]);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $this->post(route('bo.dav.sync'), [
            'choices' => [['member_id' => $member->id, 'fields' => []]],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('new-uid', $member->fresh()->dav_uid);
    }
}
