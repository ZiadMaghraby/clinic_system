<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, Model $subject): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action,
            'subject_type' => class_basename($subject), 'subject_id' => $subject->id]);
    }
}
