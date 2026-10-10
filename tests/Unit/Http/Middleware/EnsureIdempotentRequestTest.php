<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureIdempotentRequest;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureIdempotentRequestTest extends TestCase
{
    public function test_request_hash_is_stable_for_identical_requests(): void
    {
        $body = '{"amount":50}';

        $first = Request::create('api/register/tickets', 'POST', content: $body);
        $second = Request::create('api/register/tickets', 'POST', content: $body);

        $this->assertSame(
            EnsureIdempotentRequest::requestHash($first),
            EnsureIdempotentRequest::requestHash($second),
        );
    }

    public function test_request_hash_differs_for_different_bodies(): void
    {
        $first = Request::create('api/register/tickets', 'POST', content: '{"amount":50}');
        $second = Request::create('api/register/tickets', 'POST', content: '{"amount":99}');

        $this->assertNotSame(
            EnsureIdempotentRequest::requestHash($first),
            EnsureIdempotentRequest::requestHash($second),
        );
    }

    public function test_request_hash_differs_for_different_paths(): void
    {
        $first = Request::create('api/register/tickets', 'POST', content: '{}');
        $second = Request::create('api/register/tickets/1/refund', 'POST', content: '{}');

        $this->assertNotSame(
            EnsureIdempotentRequest::requestHash($first),
            EnsureIdempotentRequest::requestHash($second),
        );
    }

    public function test_request_hash_differs_for_different_methods(): void
    {
        $first = Request::create('api/register/days/1', 'PATCH', content: '{}');
        $second = Request::create('api/register/days/1', 'POST', content: '{}');

        $this->assertNotSame(
            EnsureIdempotentRequest::requestHash($first),
            EnsureIdempotentRequest::requestHash($second),
        );
    }
}
