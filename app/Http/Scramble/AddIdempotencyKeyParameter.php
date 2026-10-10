<?php

namespace App\Http\Scramble;

use App\Http\Middleware\EnsureIdempotentRequest;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

class AddIdempotencyKeyParameter extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array(EnsureIdempotentRequest::class, $routeInfo->route->gatherMiddleware())) {
            return;
        }

        $operation->addParameters([
            Parameter::make('Idempotency-Key', 'header')
                ->required(true)
                ->description('Client-generated UUID, created once per logical operation and reused on every retry.')
                ->setSchema(Schema::fromType((new StringType)->format('uuid'))),
        ]);
    }
}
