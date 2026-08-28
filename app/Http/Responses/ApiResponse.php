<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

trait ApiResponse
{
    protected function success(JsonResource|array $data, int $status = 200, array $headers = []): JsonResponse
    {
        $body = $data instanceof JsonResource ? $data->response()->getData(true) : $data;

        return response()->json($body, $status, $headers);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function created(JsonResource|array $data): JsonResponse
    {
        return $this->success($data, Response::HTTP_CREATED);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }
}
