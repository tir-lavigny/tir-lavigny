<?php

namespace App;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->writeActivity('created', $model->getAttributes());
        });
        static::updated(function ($model) {
            $model->writeActivity('updated', $model->getChanges());
        });
        static::deleted(function ($model) {
            $model->writeActivity('deleted');
        });
    }

    protected function getActivityKey(): mixed
    {
        return $this->getKey();
    }

    protected function writeActivity(string $action, array $changes = []): void
    {
        ActivityLog::create([
            'model' => static::class,
            'model_id' => $this->getActivityKey(),
            'action' => $action,
            'changes' => json_encode($changes),
            'user_id' => Auth::id(),
        ]);
    }
}
