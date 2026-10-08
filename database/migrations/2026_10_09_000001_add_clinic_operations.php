<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateSlots = DB::table('appointments')->where('status', '!=', 'cancelled')->select('doctor_id', 'appointment_date', 'appointment_time')->groupBy('doctor_id', 'appointment_date', 'appointment_time')->havingRaw('COUNT(*) > 1')->exists();
        $duplicateInvoices = DB::table('invoices')->select('appointment_id')->groupBy('appointment_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicateSlots || $duplicateInvoices) {
            throw new RuntimeException('Resolve duplicate active appointments or duplicate appointment invoices before migrating. No records were modified.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('patient')->index();
            $table->string('phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('is_active')->default(true);
        });
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        Schema::table('doctors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->decimal('consultation_fee', 8, 2)->default(0);
            $table->time('starts_at')->default('09:00:00');
            $table->time('ends_at')->default('17:00:00');
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->json('working_days')->nullable();
        });
        Schema::table('appointments', function (Blueprint $table) {
            // Nullable reservation key releases a slot on cancellation; uniqueness is enforced by the DB.
            $table->string('slot_key')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['appointment_date', 'doctor_id', 'status']);
        });
        // Keep historical rows untouched. Fail safely if legacy data contains conflicting active bookings.
        DB::table('appointments')->where('status', '!=', 'cancelled')->orderBy('id')->each(function ($a) {
            DB::table('appointments')->where('id', $a->id)->update([
                'slot_key' => $a->doctor_id.'|'.$a->appointment_date.'|'.substr($a->appointment_time, 0, 5),
            ]);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('appointment_id');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
        });
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('notes');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('clinical_notes');
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropUnique(['appointment_id']);
            $t->dropColumn(['paid_at', 'payment_method']);
        });
        Schema::table('appointments', function (Blueprint $t) {
            $t->dropForeign(['created_by']);
            $t->dropIndex(['appointment_date', 'doctor_id', 'status']);
            $t->dropUnique(['slot_key']);
            $t->dropColumn(['slot_key', 'reason', 'created_by']);
        });
        Schema::table('doctors', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->dropUnique(['user_id']);
            $t->dropColumn(['user_id', 'consultation_fee', 'starts_at', 'ends_at', 'slot_minutes', 'working_days']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['role']);
            $t->dropColumn(['role', 'phone', 'date_of_birth', 'is_active']);
        });
    }
};
