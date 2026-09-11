<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\EventChain;
use App\Models\Location;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventService;
    }

    // ── compareEventToChain ─────────────────────────────────────────────────

    public function test_compare_returns_empty_when_no_chain(): void
    {
        $event = Event::factory()->create(['event_chain_id' => null]);

        $result = $this->service->compareEventToChain($event, []);

        $this->assertEmpty($result);
    }

    public function test_compare_detects_changed_title(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Chain Title']);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Event Title',
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('Event Title', $result['title']['event_value']);
        $this->assertEquals('Chain Title', $result['title']['chain_value']);
    }

    public function test_compare_returns_empty_when_all_fields_match(): void
    {
        $location = Location::factory()->create();
        $chain = EventChain::factory()->withoutEvents()->create([
            'title' => 'Same Title',
            'location_id' => $location->id,
            'content_id' => null,
            'start_time' => '09:00:00',
            'end_time' => null,
            'custom_body' => null,
        ]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Same Title',
            'location_id' => $location->id,
            'content_id' => null,
            // 08:00 UTC = 09:00 Zurich (winter, UTC+1) — matches chain
            'start_datetime' => Carbon::parse('2099-01-01 08:00:00'),
            'end_datetime' => null,
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertEmpty($result);
    }

    public function test_compare_detects_changed_location_id(): void
    {
        $location1 = Location::factory()->create();
        $location2 = Location::factory()->create();
        $chain = EventChain::factory()->withoutEvents()->create(['location_id' => $location1->id]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'location_id' => $location2->id,
            'title' => $chain->title,
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertArrayHasKey('location_id', $result);
        $this->assertEquals($location2->id, $result['location_id']['event_value']);
        $this->assertEquals($location1->id, $result['location_id']['chain_value']);
    }

    public function test_compare_returns_empty_when_start_time_matches(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create([
            'start_time' => '10:00:00',
        ]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => $chain->title,
            // 09:00 UTC = 10:00 Zurich (winter, UTC+1) — matches chain
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertArrayNotHasKey('start_time', $result);
    }

    public function test_compare_detects_changed_start_time(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create([
            'start_time' => '09:00:00',
        ]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => $chain->title,
            // 10:00 Zurich in winter = 09:00 UTC
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertArrayHasKey('start_time', $result);
        // 09:00 UTC = 10:00 Zurich (winter, UTC+1)
        $this->assertEquals('10:00:00', $result['start_time']['event_value']);
        $this->assertEquals('09:00:00', $result['start_time']['chain_value']);
    }

    public function test_compare_detects_changed_end_time(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create([
            'end_time' => '10:00:00',
        ]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => $chain->title,
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
            // 11:00 Zurich in winter = 10:00 UTC
            'end_datetime' => Carbon::parse('2099-01-01 10:00:00'),
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertArrayHasKey('end_time', $result);
        // 10:00 UTC = 11:00 Zurich (winter, UTC+1)
        $this->assertEquals('11:00:00', $result['end_time']['event_value']);
        $this->assertEquals('10:00:00', $result['end_time']['chain_value']);
    }

    public function test_compare_detects_changed_custom_body(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create([
            'custom_body' => 'Chain body',
        ]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => $chain->title,
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => 'Custom body']);

        $this->assertArrayHasKey('custom_body', $result);
        $this->assertEquals('Custom body', $result['custom_body']['event_value']);
        $this->assertEquals('Chain body', $result['custom_body']['chain_value']);
    }

    public function test_compare_handles_null_end_datetime_and_chain_end_time(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create([
            'end_time' => null,
        ]);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => $chain->title,
            'start_datetime' => Carbon::parse('2099-01-01 09:00:00'),
            'end_datetime' => null,
        ]);

        $result = $this->service->compareEventToChain($event, ['body' => null]);

        $this->assertArrayNotHasKey('end_time', $result);
    }

    // ── applyChainOverrides ─────────────────────────────────────────────────

    public function test_apply_marks_changed_fields(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'overridden_fields' => [],
        ]);

        $this->service->applyChainOverrides($event, [
            'title' => ['event_value' => 'New', 'chain_value' => 'Old'],
        ]);

        $this->assertContains('title', $event->overridden_fields);
    }

    public function test_apply_unmarks_unchanged_fields(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'overridden_fields' => ['title', 'location_id'],
        ]);

        $this->service->applyChainOverrides($event, [
            'title' => ['event_value' => 'New', 'chain_value' => 'Old'],
        ]);

        $this->assertContains('title', $event->overridden_fields);
        $this->assertNotContains('location_id', $event->overridden_fields);
    }

    public function test_apply_does_nothing_without_chain(): void
    {
        $event = Event::factory()->create([
            'event_chain_id' => null,
            'overridden_fields' => ['title'],
        ]);

        $this->service->applyChainOverrides($event, [
            'title' => ['event_value' => 'New', 'chain_value' => 'Old'],
        ]);

        $this->assertContains('title', $event->overridden_fields);
    }

    public function test_apply_handles_multiple_changed_fields(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'overridden_fields' => [],
        ]);

        $this->service->applyChainOverrides($event, [
            'title' => ['event_value' => 'New', 'chain_value' => 'Old'],
            'location_id' => ['event_value' => 2, 'chain_value' => 1],
        ]);

        $this->assertContains('title', $event->overridden_fields);
        $this->assertContains('location_id', $event->overridden_fields);
    }
}
