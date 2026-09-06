<?php

namespace Tests\Feature;

use App\Events\MemberRegistrationCompleted;
use App\Events\MemberRegistrationDavCreated;
use App\Events\MemberRegistrationFormSubmitted;
use App\Listeners\CreateDavContact;
use App\Listeners\CreateMemberAndVouchers;
use App\Listeners\SendCompletionEmail;
use App\Mail\MemberRegistrationError;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Services\InfomaniakDavService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_create_dav_contact_creates_contact_and_dispatches(): void
    {
        Event::fake([MemberRegistrationDavCreated::class]);

        $registration = $this->createRegistrationWithData();

        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('createContactFromMemberRegistration')
            ->once()
            ->andReturn([
                'uid' => 'test-uid-123',
                'url' => 'http://example.com/test.vcf',
                'fn' => 'Dupont Jean',
                'vcard_raw' => 'BEGIN:VCARD...',
            ]);

        $listener = new CreateDavContact($davMock);
        $listener->handle(new MemberRegistrationFormSubmitted($registration));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::DAV_CREATED, $registration->status);
        $this->assertEquals('test-uid-123', $registration->dav_uid);

        Event::assertDispatched(MemberRegistrationDavCreated::class);
    }

    public function test_create_dav_contact_handles_failure(): void
    {
        Mail::fake();

        $registration = $this->createRegistrationWithData();

        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('createContactFromMemberRegistration')
            ->once()
            ->andThrow(new \RuntimeException('DAV connection failed'));

        $listener = new CreateDavContact($davMock);
        $listener->handle(new MemberRegistrationFormSubmitted($registration));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNotNull($registration->failed_at);
        $this->assertStringContainsString('DAV connection failed', $registration->error_message);

        Mail::assertSent(MemberRegistrationError::class);
    }

    public function test_create_member_and_vouchers_creates_member_and_vouchers(): void
    {
        Event::fake([MemberRegistrationCompleted::class]);

        $season = Season::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $registration = $this->createRegistrationWithData();
        $registration->dav_uid = 'test-uid-123';
        $registration->status = MemberRegistrationStatus::DAV_CREATED;
        $registration->save();

        $listener = new CreateMemberAndVouchers;
        $listener->handle(new MemberRegistrationDavCreated($registration));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::MEMBER_CREATED, $registration->status);
        $this->assertNotNull($registration->member_id);

        $member = $registration->member;
        $this->assertEquals('Jean', $member->first_name);
        $this->assertEquals('Dupont', $member->last_name);
        $this->assertEquals('test-uid-123', $member->dav_uid);

        $vouchers = $member->vouchers()->where('season_id', $season->id)->get();
        $this->assertCount(5, $vouchers);

        Event::assertDispatched(MemberRegistrationCompleted::class);
    }

    public function test_create_member_and_vouchers_without_season(): void
    {
        Event::fake([MemberRegistrationCompleted::class]);

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $registration = $this->createRegistrationWithData();
        $registration->dav_uid = 'test-uid-123';
        $registration->status = MemberRegistrationStatus::DAV_CREATED;
        $registration->save();

        $listener = new CreateMemberAndVouchers;
        $listener->handle(new MemberRegistrationDavCreated($registration));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::MEMBER_CREATED, $registration->status);
        $this->assertNotNull($registration->member_id);
        $this->assertCount(0, $registration->member->vouchers()->get());

        Event::assertDispatched(MemberRegistrationCompleted::class);
    }

    public function test_send_completion_email_sends_and_marks_completed(): void
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

        $listener = new SendCompletionEmail;
        $listener->handle(new MemberRegistrationCompleted($registration));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);

        Mail::assertSent(\App\Mail\MemberRegistrationCompleted::class, function ($mail) use ($registration) {
            return $mail->hasTo($this->admin->email)
                && $mail->registration->id === $registration->id;
        });
    }

    public function test_full_pipeline_with_mocked_dav(): void
    {
        Mail::fake();

        $season = Season::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        Product::factory()->create(['name' => 'Tir du jeudi']);
        Product::factory()->create(['name' => 'Ouverture']);
        Product::factory()->create(['name' => 'Clôture']);

        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('createContactFromMemberRegistration')
            ->once()
            ->andReturn([
                'uid' => 'test-uid-123',
                'url' => 'http://example.com/test.vcf',
                'fn' => 'Dupont Jean',
                'vcard_raw' => 'BEGIN:VCARD...',
            ]);

        $registration = $this->createRegistrationWithData();

        $davListener = new CreateDavContact($davMock);
        $davListener->handle(new MemberRegistrationFormSubmitted($registration));

        $memberListener = new CreateMemberAndVouchers;
        $memberListener->handle(new MemberRegistrationDavCreated($registration->fresh()));

        $emailListener = new SendCompletionEmail;
        $emailListener->handle(new MemberRegistrationCompleted($registration->fresh()));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
        $this->assertEquals('test-uid-123', $registration->dav_uid);

        $member = $registration->member;
        $this->assertEquals('Jean', $member->first_name);
        $this->assertEquals('Dupont', $member->last_name);

        $vouchers = $member->vouchers()->where('season_id', $season->id)->get();
        $this->assertCount(5, $vouchers);

        Mail::assertSent(\App\Mail\MemberRegistrationCompleted::class, function ($mail) use ($registration) {
            return $mail->hasTo($this->admin->email)
                && $mail->registration->id === $registration->id;
        });
    }

    public function test_status_enum_has_member_created(): void
    {
        $this->assertEquals('member_created', MemberRegistrationStatus::MEMBER_CREATED->value);
    }

    private function mockDavService(): void
    {
        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('createContactFromMemberRegistration')
            ->once()
            ->andReturn([
                'uid' => 'test-uid-123',
                'url' => 'http://example.com/test.vcf',
                'fn' => 'Dupont Jean',
                'vcard_raw' => 'BEGIN:VCARD...',
            ]);

        $this->app->instance(InfomaniakDavService::class, $davMock);
    }
}
