<?php

namespace Tests\Feature;

use App\Mail\MemberDavSyncFailed;
use App\Mail\MemberRegistrationCompleted;
use App\Mail\MemberRegistrationError;
use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\MemberStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Services\DavServiceInterface;
use App\Services\MemberRegistrationPipelineService;
use App\Services\MemberSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MemberRegistrationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->assignRole('get member registration mails');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createRegistrationWithData(): MemberRegistration
    {
        return MemberRegistration::create([
            'form_uuid' => Str::uuid(),
            'form_data' => [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
                'is_swiss_citizen' => true,
                'avs' => '756.1234.5678.90',
                'tel' => '+41 21 123 45 67',
                'street' => 'Rue du Marché 1',
                'npa' => '1003',
                'city' => 'Lausanne',
                'canton' => 'VD',
                'country' => 'Suisse',
            ],
            'form_submitted_at' => now(),
            'status' => MemberRegistrationStatus::FORM_FILLED,
        ]);
    }

    private function pipelineWithSuccessfulDav(): MemberRegistrationPipelineService
    {
        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('createContactFromMember')
            ->once()
            ->andReturn([
                'uid' => 'test-uid-123',
                'url' => 'http://example.com/test.vcf',
                'fn' => 'Dupont Jean',
                'vcard_raw' => 'BEGIN:VCARD...',
            ]);

        return new MemberRegistrationPipelineService(new MemberSyncService($dav));
    }

    private function pipelineWithFailingDav(string $message = 'DAV connection failed'): MemberRegistrationPipelineService
    {
        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('createContactFromMember')
            ->once()
            ->andThrow(new \RuntimeException($message));

        return new MemberRegistrationPipelineService(new MemberSyncService($dav));
    }

    public function test_send_validation_request_sends_email(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(MemberSyncService::class));

        $pipeline->sendValidationRequest($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNull($registration->failed_at);
        $this->assertNull($registration->dav_uid);
        $this->assertNull($registration->member_id);

        Mail::assertSent(MemberRegistrationValidationRequested::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
        Mail::assertNotSent(MemberRegistrationCompleted::class);
    }

    public function test_send_validation_request_handles_failure(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mail server down'));

        $registration = $this->createRegistrationWithData();
        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(MemberSyncService::class));

        $pipeline->sendValidationRequest($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNotNull($registration->failed_at);
        $this->assertStringContainsString('Mail server down', $registration->error_message);
    }

    public function test_process_form_submission_completes_full_pipeline(): void
    {
        Mail::fake();

        Season::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $registration = $this->createRegistrationWithData();
        $pipeline = $this->pipelineWithSuccessfulDav();

        $pipeline->processFormSubmission($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertEquals('test-uid-123', $registration->dav_uid);
        $this->assertNotNull($registration->member_id);

        $member = $registration->member;
        $this->assertEquals('Jean', $member->first_name);
        $this->assertEquals('Dupont', $member->last_name);
        $this->assertEquals('jean.dupont@example.com', $member->email);
        $this->assertEquals('756.1234.5678.90', $member->avs);
        $this->assertEquals('VD', $member->canton);
        $this->assertEquals(MemberStatus::ACTIVE, $member->status);
        $this->assertEquals('test-uid-123', $member->dav_uid);
        $this->assertNull($member->dav_sync_error);

        $vouchers = $member->vouchers()->where('season_id', Season::first()->id)->get();
        $this->assertCount(5, $vouchers);

        Mail::assertSent(MemberRegistrationCompleted::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
        Mail::assertNotSent(MemberDavSyncFailed::class);
    }

    public function test_process_form_submission_without_season(): void
    {
        Mail::fake();

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $registration = $this->createRegistrationWithData();
        $pipeline = $this->pipelineWithSuccessfulDav();

        $pipeline->processFormSubmission($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
        $this->assertCount(0, $registration->member->vouchers()->get());
    }

    public function test_dav_failure_does_not_fail_registration(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $pipeline = $this->pipelineWithFailingDav('DAV connection failed');

        $pipeline->processFormSubmission($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
        $this->assertNull($registration->failed_at);

        $member = $registration->member;
        $this->assertSame('DAV connection failed', $member->dav_sync_error);
        $this->assertNull($member->dav_uid);

        Mail::assertSent(MemberDavSyncFailed::class);
        Mail::assertNotSent(MemberRegistrationError::class);
    }

    public function test_retry_from_form_filled_resends_validation_email(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $registration->failed_at = now();
        $registration->error_message = 'Erreur : Mail server down';
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(MemberSyncService::class));

        $pipeline->retryFromStep($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNull($registration->dav_uid);
        $this->assertNull($registration->member_id);

        Mail::assertSent(MemberRegistrationValidationRequested::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
        Mail::assertNotSent(MemberRegistrationCompleted::class);
    }

    public function test_retry_from_member_created_skips_vouchers_for_passive_member(): void
    {
        Mail::fake();

        Season::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $registration = $this->createRegistrationWithData();
        $member = Member::factory()->passive()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $registration->member()->associate($member);
        $registration->status = MemberRegistrationStatus::MEMBER_CREATED;
        $registration->save();

        $pipeline = $this->pipelineWithSuccessfulDav();
        $pipeline->retryFromStep($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertCount(0, $member->vouchers()->get());
    }

    public function test_retry_from_completed_pushes_dav_again(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $member = Member::factory()->create([
            'dav_uid' => 'uid-retry',
            'dav_sync_error' => 'Previous failure',
        ]);
        $registration->member()->associate($member);
        $registration->status = MemberRegistrationStatus::COMPLETED;
        $registration->save();

        $dav = Mockery::mock(DavServiceInterface::class);
        $dav->shouldReceive('updateMember')->once()->andReturn(true);

        $pipeline = new MemberRegistrationPipelineService(new MemberSyncService($dav));
        $pipeline->retryFromStep($registration);

        $this->assertNull($member->fresh()->dav_sync_error);
        Mail::assertNotSent(MemberDavSyncFailed::class);
    }

    public function test_retry_from_completed_without_sync_error_throws(): void
    {
        $registration = $this->createRegistrationWithData();
        $member = Member::factory()->create(['dav_sync_error' => null]);
        $registration->member()->associate($member);
        $registration->status = MemberRegistrationStatus::COMPLETED;
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(MemberSyncService::class));

        $this->expectException(\InvalidArgumentException::class);
        $pipeline->retryFromStep($registration);
    }

    public function test_retry_throws_for_unretryable_status(): void
    {
        $registration = $this->createRegistrationWithData();
        $registration->status = MemberRegistrationStatus::PENDING;
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(MemberSyncService::class));

        $this->expectException(\InvalidArgumentException::class);
        $pipeline->retryFromStep($registration);
    }

    public function test_status_enum_no_longer_has_dav_created(): void
    {
        $this->assertNull(MemberRegistrationStatus::tryFrom('dav_created'));
        $this->assertEquals('member_created', MemberRegistrationStatus::MEMBER_CREATED->value);
    }
}
