<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorTimeOff extends Model
{
    protected $fillable = ['doctor_id', 'starts_at', 'ends_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
