<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// <--- مهم جداً

class Doctor extends Model
{
    protected $fillable = ['name', 'email', 'specialization', 'status', 'user_id', 'consultation_fee', 'starts_at', 'ends_at', 'slot_minutes', 'working_days'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'consultation_fee' => 'decimal:2', 'slot_minutes' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
