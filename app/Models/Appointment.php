<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'slot_key', 'reason', 'created_by', 'duration_minutes',
    ];

    protected function casts(): array
    {
        return ['appointment_date' => 'date', 'reason' => 'encrypted'];
    }

    public function clinicalNote()
    {
        return $this->hasOne(ClinicalNote::class);
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'appointment_id', 'id');
    }
}
