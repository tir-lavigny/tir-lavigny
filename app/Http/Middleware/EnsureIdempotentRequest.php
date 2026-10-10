<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentRequest
{
    private const LOCK_SECONDS = 30;

    private const POLL_ATTEMPTS = 10;

    private const POLL_INTERVAL_MICROSECONDS = 300_000;

    private const MAX_ACQUIRE_ATTEMPTS = 2;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            abort(400, 'Idempotency-Key header is required for this endpoint.');
        }

        $user = $request->user();
        assert($user instanceof User);

        $requestHash = self::requestHash($request);

        for ($attempt = 0; $attempt < self::MAX_ACQUIRE_ATTEMPTS; $attempt++) {
            $record = $this->acquire($user, $key, $requestHash);

            if ($record !== null) {
                return $this->process($record, $request, $next);
            }

            $existing = $this->findRecord($user, $key);

            if ($existing === null) {
                // The previous attempt failed and released the key — take it over.
                continue;
            }

            if (! hash_equals($existing->request_hash, $requestHash)) {
                abort(422, 'Idempotency-Key was already used with a different request body.');
            }

            if ($existing->status === IdempotencyKey::STATUS_COMPLETED) {
                return $this->replay($existing);
            }

            if ($this->releaseIfStale($user, $key)) {
                // The original attempt never finished — make the key retryable again.
                continue;
            }

            if ($attempt === 0) {
                // Genuinely concurrent duplicate — the original is likely to finish soon.
                $completed = $this->waitForCompletion($user, $key);

                if ($completed !== null) {
                    return $this->replay($completed);
                }
            }
        }

        abort(409, 'Request with this Idempotency-Key is still processing.');
    }

    public static function requestHash(Request $request): string
    {
        return hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
    }

    private function acquire(User $user, string $key, string $requestHash): ?IdempotencyKey
    {
        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $user->id,
            'key' => $key,
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'locked_until' => now()->addSeconds(self::LOCK_SECONDS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return null;
        }

        return $this->findRecord($user, $key);
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    private function process(IdempotencyKey $record, Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Nothing meaningful happened — release the key so a retry can run again.
            $record->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 400) {
            // The request was rejected (validation error, closed day, ...) — no side
            // effect happened, so the key must stay retryable instead of being
            // locked to a stale error response.
            $record->delete();

            return $response;
        }

        $record->update([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
        ]);

        return $response;
    }

    private function replay(IdempotencyKey $record): Response
    {
        return response($record->response_body, $record->response_status)
            ->header('Content-Type', 'application/json')
            ->header('Idempotency-Replayed', 'true');
    }

    private function waitForCompletion(User $user, string $key): ?IdempotencyKey
    {
        for ($i = 0; $i < self::POLL_ATTEMPTS; $i++) {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            $record = $this->findRecord($user, $key);

            if ($record === null) {
                return null;
            }

            if ($record->status === IdempotencyKey::STATUS_COMPLETED) {
                return $record;
            }
        }

        return null;
    }

    private function releaseIfStale(User $user, string $key): bool
    {
        $record = $this->findRecord($user, $key);

        if ($record !== null
            && $record->status === IdempotencyKey::STATUS_PROCESSING
            && ($record->locked_until === null || $record->locked_until->isPast())
        ) {
            $record->delete();

            return true;
        }

        return false;
    }

    private function findRecord(User $user, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->first();
    }
}
