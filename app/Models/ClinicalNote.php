<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    protected $fillable = ['appointment_id', 'author_id', 'notes'];

    protected function casts(): array
    {
        return ['notes' => 'encrypted'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
