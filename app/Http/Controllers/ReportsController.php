<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->summary($request);

        return view('clinic.reports', $data);
    }

    public function export(Request $request)
    {
        $data = $this->summary($request);
        AuditLog::record('report.exported', $request->user());

        return response()->streamDownload(function () use ($data) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ([
                ['Metric', 'Value', 'Currency'],
                ['From', $data['from'], ''],
                ['Through', $data['to'], ''],
                ['Scheduled visits', $data['total'], ''],
                ['Completed visits', $data['counts']['completed'] ?? 0, ''],
                ['Cancelled visits', $data['counts']['cancelled'] ?? 0, ''],
                ['Payments collected by payment date', $data['collected'], config('clinic.currency')],
                ['Outstanding for visits in range', $data['outstanding'], config('clinic.currency')],
            ] as $row) {
                // Protect spreadsheet consumers even if an operator configures a formula-like currency.
                $row = array_map(fn ($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) ? "'".$value : $value, $row);
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, 'clinic-report-'.$data['from'].'-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function summary(Request $request): array
    {
        $today = CarbonImmutable::now(config('clinic.timezone'));
        $request->merge([
            'from' => $request->input('from', $today->startOfMonth()->toDateString()),
            'to' => $request->input('to', $today->toDateString()),
        ]);
        $range = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $from = CarbonImmutable::parse($range['from'], config('clinic.timezone'));
        $to = CarbonImmutable::parse($range['to'], config('clinic.timezone'));
        abort_if($from->diffInDays($to) > 366, 422, __('Choose a range of up to 366 days.'));
        $visits = Appointment::whereDate('appointment_date', '>=', $range['from'])->whereDate('appointment_date', '<=', $range['to']);
        $counts = (clone $visits)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        // paid_at uses the application timezone, like Laravel's timestamps and now().
        $payments = Invoice::where('status', 'paid')->where('paid_at', '>=', $from->setTimezone(config('app.timezone')))
            ->where('paid_at', '<', $to->addDay()->setTimezone(config('app.timezone')));
        $daily = (clone $visits)->selectRaw('appointment_date, COUNT(*) as total')->groupBy('appointment_date')->orderBy('appointment_date')->get();

        return [
            ...$range,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
            'collected' => (clone $payments)->sum('amount'),
            'outstanding' => Invoice::where('status', 'unpaid')->whereHas('appointment', fn ($q) => $q->whereDate('appointment_date', '>=', $range['from'])->whereDate('appointment_date', '<=', $range['to'])->where('status', '!=', 'cancelled'))->sum('amount'),
            'methods' => (clone $payments)->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as payments')->groupBy('payment_method')->get(),
            'daily' => $daily,
        ];
    }
}
