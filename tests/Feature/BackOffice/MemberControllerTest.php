<?php

namespace Tests\Feature\BackOffice;

use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DavServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('updateMember')->andReturn(true);
        $davMock->shouldReceive('createContactFromMember')->andReturn(['uid' => 'uid-123']);
        $davMock->shouldReceive('softDeleteMember')->andReturn(true);
        $davMock->shouldReceive('deleteMember')->andReturn(true);
        $this->app->instance(DavServiceInterface::class, $davMock);
    }

    public function test_index_returns_members(): void
    {
        Member::factory()->create(['last_name' => 'Dupont']);

        $response = $this->get(route('bo.members.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Members/Index')
            ->has('members.data', 1)
        );
    }

    public function test_index_renders_members_with_legacy_plaintext_avs(): void
    {
        $member = Member::factory()->create();

        DB::table('members')->where('id', $member->id)->update(['avs' => '756.0000.0000.00']);

        $response = $this->get(route('bo.members.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Members/Index')
            ->has('members.data', 1)
        );
    }

    public function test_index_sorts_members_by_name(): void
    {
        Member::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Bernard']);
        Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Zurcher']);
        Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Andre']);

        $response = $this->get(route('bo.members.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.first_name', 'Alice')
            ->where('members.data.0.last_name', 'Andre')
            ->where('members.data.1.first_name', 'Alice')
            ->where('members.data.1.last_name', 'Zurcher')
            ->where('members.data.2.first_name', 'Zoe')
        );
    }

    public function test_index_searches_members_without_accents(): void
    {
        Member::factory()->create(['first_name' => 'Émile', 'last_name' => 'Généreux']);
        Member::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Bernard']);

        $response = $this->get(route('bo.members.index', ['search' => 'emile']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.first_name', 'Émile')
            ->where('filters.search', 'emile')
        );
    }

    public function test_index_filters_by_status(): void
    {
        Member::factory()->create(['first_name' => 'Active']);
        Member::factory()->passive()->create(['first_name' => 'Passive']);

        $response = $this->get(route('bo.members.index', ['status' => 'passive']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.first_name', 'Passive')
        );
    }

    public function test_index_shows_archived_members(): void
    {
        Member::factory()->create(['first_name' => 'Active']);
        Member::factory()->archived()->create(['first_name' => 'Archived']);

        $response = $this->get(route('bo.members.index', ['archived' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.first_name', 'Archived')
        );
    }

    public function test_create_returns_form(): void
    {
        $response = $this->get(route('bo.members.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Members/Create')
            ->has('statuses')
        );
    }

    public function test_store_creates_member(): void
    {
        $response = $this->post(route('bo.members.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
        ]);
    }

    public function test_store_reports_sync_failure(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('createContactFromMember')->andThrow(new \RuntimeException('DAV down'));
        $this->app->instance(DavServiceInterface::class, $davMock);

        $response = $this->post(route('bo.members.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('members', ['first_name' => 'Jean', 'last_name' => 'Dupont']);
    }

    public function test_store_accepts_valid_canton(): void
    {
        $response = $this->post(route('bo.members.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'canton' => 'VS',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['first_name' => 'Jean', 'canton' => 'VS']);
    }

    public function test_store_rejects_invalid_canton(): void
    {
        $response = $this->post(route('bo.members.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'canton' => 'XX',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('canton');
        $this->assertDatabaseMissing('members', ['first_name' => 'Jean']);
    }

    public function test_edit_returns_form(): void
    {
        $member = Member::factory()->create();

        $response = $this->get(route('bo.members.edit', $member));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Members/Edit')
            ->where('member.id', $member->id)
        );
    }

    public function test_update_modifies_member(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean']);

        $response = $this->put(route('bo.members.update', $member), [
            'first_name' => 'Pierre',
            'last_name' => $member->last_name,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'Pierre']);
    }

    public function test_update_reports_sync_failure(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('updateMember')->andReturn(false);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $member = Member::factory()->create(['dav_uid' => 'uid-1']);

        $response = $this->put(route('bo.members.update', $member), [
            'first_name' => 'Pierre',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'Pierre']);
    }

    public function test_update_accepts_valid_canton(): void
    {
        $member = Member::factory()->create(['canton' => 'VD']);

        $response = $this->put(route('bo.members.update', $member), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'canton' => 'VS',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'canton' => 'VS']);
    }

    public function test_update_rejects_invalid_canton(): void
    {
        $member = Member::factory()->create(['canton' => 'VD']);

        $response = $this->put(route('bo.members.update', $member), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'canton' => 'XX',
        ]);

        $response->assertSessionHasErrors('canton');
        $this->assertDatabaseHas('members', ['id' => $member->id, 'canton' => 'VD']);
    }

    public function test_update_does_not_change_canton_when_only_tel_is_sent(): void
    {
        $member = Member::factory()->create(['canton' => 'VD']);

        $response = $this->put(route('bo.members.update', $member), [
            'tel' => '+41 21 123 45 67',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'canton' => 'VD',
            'tel' => '+41 21 123 45 67',
        ]);
    }

    public function test_destroy_archives_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->delete(route('bo.members.destroy', $member));

        $response->assertRedirect();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_destroy_deletes_unused_vouchers_when_requested(): void
    {
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);

        $response = $this->delete(route('bo.members.destroy', $member), ['delete_vouchers' => true]);

        $response->assertRedirect();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
    }

    public function test_destroy_keeps_used_vouchers(): void
    {
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->create(['ticket_id' => $ticket->id, 'voucher_id' => $voucher->id]);

        $response = $this->delete(route('bo.members.destroy', $member), ['delete_vouchers' => true]);

        $response->assertRedirect();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'deleted_at' => null]);
    }

    public function test_restore_restores_member(): void
    {
        $member = Member::factory()->archived()->create();

        $response = $this->post(route('bo.members.restore', $member));

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'deleted_at' => null]);
    }

    public function test_force_delete_requires_confirmation(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);

        $response = $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => 'wrong',
        ]);

        $response->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    public function test_force_delete_hard_deletes_without_vouchers(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);

        $response = $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => 'Jean Dupont',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
    }

    public function test_force_delete_anonymizes_when_vouchers_exist(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        Voucher::factory()->create(['member_id' => $member->id]);

        $response = $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => 'Jean Dupont',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => '[Deleted]',
            'email' => null,
        ]);
        $this->assertNotNull($member->fresh()->anonymized_at);
    }

    public function test_force_delete_anonymizes_when_tickets_exist(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        Ticket::factory()->create(['member_id' => $member->id]);

        $response = $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => 'Jean Dupont',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => '[Deleted]',
        ]);
    }

    public function test_force_delete_purges_activity_logs(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        $member->update(['first_name' => 'Pierre']);

        $this->assertDatabaseCount('activity_logs', 2);

        $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => 'Pierre Dupont',
        ]);

        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'model' => Member::class,
            'model_id' => $member->id,
            'action' => 'deleted',
        ]);
    }

    public function test_force_delete_blocks_anonymized_member(): void
    {
        $member = Member::factory()->create(['anonymized_at' => now()]);

        $response = $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => trim($member->first_name.' '.$member->last_name),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    public function test_sync_retries_member(): void
    {
        $member = Member::factory()->create(['dav_uid' => 'uid-1']);

        $response = $this->post(route('bo.members.sync', $member));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_requires_manage_members_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $response = $this->get(route('bo.members.index'));

        $response->assertForbidden();
    }

    public function test_show_returns_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->get(route('bo.members.show', $member));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Members/Show')
            ->where('member.id', $member->id)
        );
    }

    public function test_show_does_not_expose_plain_avs(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');
        $user->givePermissionTo('manage members');
        $this->actingAs($user);

        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);

        $response = $this->get(route('bo.members.show', $member));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('member.avs_masked', '756.***.***.90')
            ->missing('member.avs')
        );
    }

    public function test_show_links_registration(): void
    {
        $member = Member::factory()->create();
        $registration = MemberRegistration::create(['member_id' => $member->id]);

        $response = $this->get(route('bo.members.show', $member));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('member.registration.id', $registration->id)
        );
    }

    public function test_show_reveals_sensitive_data_with_permission(): void
    {
        $member = Member::factory()->create([
            'email' => 'jean.dupont@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $response = $this->get(route('bo.members.show', $member));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('member.email', 'jean.dupont@example.com')
            ->where('member.avs', '756.1234.5678.90')
        );
    }

    public function test_show_does_not_reveal_sensitive_data_without_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');
        $user->givePermissionTo('manage members');
        $this->actingAs($user);

        $member = Member::factory()->create([
            'email' => 'jean.dupont@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $response = $this->get(route('bo.members.show', $member));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('member.email')
            ->missing('member.avs')
            ->where('member.email_masked', 'j***@example.com')
            ->where('member.avs_masked', '756.***.***.90')
        );
    }

    public function test_edit_does_not_expose_sensitive_data_without_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');
        $user->givePermissionTo('manage members');
        $this->actingAs($user);

        $member = Member::factory()->create([
            'email' => 'jean.dupont@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $response = $this->get(route('bo.members.edit', $member));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('member.email')
            ->missing('member.avs')
        );
    }

    public function test_update_does_not_change_sensitive_data_without_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');
        $user->givePermissionTo('manage members');
        $this->actingAs($user);

        $member = Member::factory()->create([
            'email' => 'jean.dupont@example.com',
            'avs' => '756.1234.5678.90',
        ]);

        $response = $this->put(route('bo.members.update', $member), [
            'first_name' => 'Pierre',
            'email' => 'pierre@example.com',
            'avs' => '756.0000.0000.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Pierre',
            'email' => 'jean.dupont@example.com',
        ]);
        $this->assertSame('756.1234.5678.90', $member->fresh()->avs);
    }

    public function test_force_delete_anonymizes_clears_dav_snapshot(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'dav_synced_snapshot' => ['email' => 'jean.dupont@example.com'],
            'dav_sync_error' => 'DAV down',
        ]);
        Voucher::factory()->create(['member_id' => $member->id]);

        $this->delete(route('bo.members.force-delete', $member), [
            'confirmation' => 'Jean Dupont',
        ]);

        $member->refresh();
        $this->assertNull($member->dav_synced_snapshot);
        $this->assertNull($member->dav_sync_error);
    }

    public function test_destroy_keeps_vouchers_by_default(): void
    {
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);

        $response = $this->delete(route('bo.members.destroy', $member));

        $response->assertRedirect();
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'deleted_at' => null]);
    }

    public function test_destroy_reports_dav_failure(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('updateMember')->andReturn(false);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $member = Member::factory()->create(['dav_uid' => 'uid-1']);

        $response = $this->delete(route('bo.members.destroy', $member));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_restore_reports_dav_failure(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('updateMember')->andReturn(false);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $member = Member::factory()->archived()->create(['dav_uid' => 'uid-1']);

        $response = $this->post(route('bo.members.restore', $member));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('members', ['id' => $member->id, 'deleted_at' => null]);
    }

    public function test_restore_does_not_restore_vouchers(): void
    {
        $member = Member::factory()->archived()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);
        $voucher->delete();

        $response = $this->post(route('bo.members.restore', $member));

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
    }

    public function test_index_searches_by_email(): void
    {
        Member::factory()->create(['email' => 'jean.dupont@example.com']);
        Member::factory()->create(['email' => 'zoe@example.com']);

        $response = $this->get(route('bo.members.index', ['search' => 'jean.dupont']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.email_masked', 'j***@example.com')
        );
    }

    public function test_index_does_not_expose_dav_synced_snapshot(): void
    {
        Member::factory()->create([
            'dav_synced_snapshot' => ['email' => 'jean.dupont@example.com'],
        ]);

        $response = $this->get(route('bo.members.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('members.data.0.dav_synced_snapshot')
        );
    }

    public function test_index_does_not_expose_registration_form_data(): void
    {
        $member = Member::factory()->create();
        MemberRegistration::create([
            'member_id' => $member->id,
            'form_data' => ['avs' => '756.1234.5678.90'],
        ]);

        $response = $this->get(route('bo.members.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('members.data.0.registration.form_data')
        );
    }

    public function test_show_does_not_expose_registration_form_data(): void
    {
        $member = Member::factory()->create();
        MemberRegistration::create([
            'member_id' => $member->id,
            'form_data' => ['avs' => '756.1234.5678.90'],
        ]);

        $response = $this->get(route('bo.members.show', $member));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('member.registration.form_data')
        );
    }
}
