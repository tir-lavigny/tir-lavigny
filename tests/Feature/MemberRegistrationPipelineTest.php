<?php

namespace Tests\Feature;

use App\Mail\MemberRegistrationCompleted;
use App\Mail\MemberRegistrationError;
use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Services\DavServiceInterface;
use App\Services\MemberRegistrationPipelineService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

    private function mockDavSuccess(): DavServiceInterface
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('createContactFromMemberRegistration')
            ->once()
            ->andReturn([
                'uid' => 'test-uid-123',
                'url' => 'http://example.com/test.vcf',
                'fn' => 'Dupont Jean',
                'vcard_raw' => 'BEGIN:VCARD...',
            ]);

        return $davMock;
    }

    private function mockDavFailure(string $message = 'DAV connection failed'): DavServiceInterface
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('createContactFromMemberRegistration')
            ->once()
            ->andThrow(new \RuntimeException($message));

        return $davMock;
    }

    // ── Validation request ─────────────────────────────────────────────────

    public function test_send_validation_request_sends_email(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(DavServiceInterface::class));

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
        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(DavServiceInterface::class));

        $pipeline->sendValidationRequest($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNotNull($registration->failed_at);
        $this->assertStringContainsString('Mail server down', $registration->error_message);
    }

    // ── Pipeline: success cases ─────────────────────────────────────────────

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
        $pipeline = new MemberRegistrationPipelineService($this->mockDavSuccess());

        $pipeline->processFormSubmission($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertEquals('test-uid-123', $registration->dav_uid);
        $this->assertNotNull($registration->member_id);

        $member = $registration->member;
        $this->assertEquals('Jean', $member->first_name);
        $this->assertEquals('Dupont', $member->last_name);
        $this->assertEquals('test-uid-123', $member->dav_uid);

        $vouchers = $member->vouchers()->where('season_id', Season::first()->id)->get();
        $this->assertCount(5, $vouchers);

        Mail::assertSent(MemberRegistrationCompleted::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
    }

    public function test_process_form_submission_without_season(): void
    {
        Mail::fake();

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $registration = $this->createRegistrationWithData();
        $pipeline = new MemberRegistrationPipelineService($this->mockDavSuccess());

        $pipeline->processFormSubmission($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
        $this->assertCount(0, $registration->member->vouchers()->get());
    }

    // ── Pipeline: failure cases ─────────────────────────────────────────────

    public function test_process_form_submission_handles_dav_failure(): void
    {
        Mail::fake();
        Log::spy();

        $registration = $this->createRegistrationWithData();
        $pipeline = new MemberRegistrationPipelineService($this->mockDavFailure('DAV connection failed'));

        $pipeline->processFormSubmission($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNotNull($registration->failed_at);
        $this->assertStringContainsString('DAV connection failed', $registration->error_message);

        Mail::assertSent(MemberRegistrationError::class);

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context): bool => $message === 'Member registration pipeline failed'
                && $context['registration_id'] === $registration->id
                && $context['step'] === 'dav_contact'
                && $context['status'] === MemberRegistrationStatus::FORM_FILLED->value
        );
    }

    // ── Retry cases ─────────────────────────────────────────────────────────

    public function test_retry_from_form_filled_resends_validation_email(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $registration->failed_at = now();
        $registration->error_message = 'Erreur : Mail server down';
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(DavServiceInterface::class));

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

    public function test_retry_from_dav_created_processes_remaining_steps(): void
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
        $registration->dav_uid = 'existing-uid';
        $registration->status = MemberRegistrationStatus::DAV_CREATED;
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(DavServiceInterface::class));

        $pipeline->retryFromStep($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
    }

    public function test_retry_from_member_created_sends_email(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();
        $member = Member::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $registration->member()->associate($member);
        $registration->status = MemberRegistrationStatus::MEMBER_CREATED;
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(DavServiceInterface::class));

        $pipeline->retryFromStep($registration);

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);

        Mail::assertSent(MemberRegistrationCompleted::class, function ($mail) {
            return $mail->hasTo($this->admin->email);
        });
    }

    public function test_retry_throws_for_unretryable_status(): void
    {
        $registration = $this->createRegistrationWithData();
        $registration->status = MemberRegistrationStatus::COMPLETED;
        $registration->save();

        $pipeline = new MemberRegistrationPipelineService(Mockery::mock(DavServiceInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $pipeline->retryFromStep($registration);
    }

    // ── Enum check ──────────────────────────────────────────────────────────

    public function test_status_enum_has_member_created(): void
    {
        $this->assertEquals('member_created', MemberRegistrationStatus::MEMBER_CREATED->value);
    }
}
