<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use App\Models\NotificationLog;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $logs = NotificationLog::with(['student.schoolClass', 'sender'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->channel, fn ($q, $c) => $q->where('channel', $c))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('recipient', 'like', "%$t%")->orWhereHas('student', fn ($s) => $s->search($t))))
            ->latest('id')->paginate(40)->withQueryString();

        $counts = NotificationLog::where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('notifications.index', [
            'logs' => $logs,
            'counts' => $counts,
            'gateway' => ['sms' => config('services.sms.driver'), 'whatsapp' => config('services.whatsapp.driver')],
        ]);
    }

    public function retry(NotificationLog $log)
    {
        abort_unless(in_array($log->status, ['failed', 'logged'], true), 422);
        $log->update(['status' => 'queued', 'response' => null]);
        SendNotification::dispatch($log->id);

        return back()->with('success', 'Message queued again.');
    }

    /** Announcement to all / a class / a section. */
    public function announceForm()
    {
        return view('notifications.announce', ['classes' => SchoolClass::ordered()->with('sections')->get()]);
    }

    public function announce(Request $request, NotificationService $notifier)
    {
        $data = $request->validate([
            'class_id' => ['nullable', 'exists:school_classes,id'],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('school_class_id', $request->class_id)],
            'channel' => ['required', Rule::in(['sms', 'whatsapp', 'both'])],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $students = Student::with(['schoolClass', 'section'])->where('status', 'active')
            ->when($data['class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->get()
            // one message per parent mobile (siblings share a number)
            ->unique(fn ($s) => preg_replace('/\D/', '', (string) $s->parent_phone));

        $queued = 0;
        foreach ($students as $s) {
            $msg = $notifier->render($data['message'], $notifier->studentVars($s));
            $queued += $notifier->toStudent($s, 'announcement', $msg, $data['channel'], $request->user()->id);
        }

        return redirect()->route('notifications.index', ['type' => 'announcement'])
            ->with('success', "Announcement queued: {$queued} message(s) to {$students->count()} parent(s).");
    }

    /** Fee-due reminders from the Pending Fees screen. */
    public function remind(Request $request, NotificationService $notifier)
    {
        $data = $request->validate([
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'upto' => ['nullable', 'date'],
            'channel' => ['required', Rule::in(['sms', 'whatsapp', 'both'])],
        ]);
        $upto = $request->date('upto') ?? now();

        $students = Student::with(['schoolClass', 'section'])->where('status', 'active')
            ->when($data['student_ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when(! ($data['student_ids'] ?? null) && ($data['class_id'] ?? null), fn ($q) => $q->where('school_class_id', $data['class_id']))
            ->when(! ($data['student_ids'] ?? null) && ($data['section_id'] ?? null), fn ($q) => $q->where('section_id', $data['section_id']))
            ->whereHas('fees', fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto))
            ->get();

        $count = $notifier->feeDueReminders($students, $upto, $data['channel'], $request->user()->id);

        return back()->with('success', "Fee reminders queued for {$count} student(s).");
    }

    public function settings()
    {
        $keys = ['notify_channel', 'notify_payment', 'notify_admission', 'notify_fee_due_auto', 'reminder_days_after_due', 'tpl_fee_due', 'tpl_payment', 'tpl_admission'];

        return view('notifications.settings', [
            'settings' => collect($keys)->mapWithKeys(fn ($k) => [$k => Setting::get($k)]),
            'gateway' => ['sms' => config('services.sms.driver'), 'whatsapp' => config('services.whatsapp.driver')],
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'notify_channel' => ['required', Rule::in(['sms', 'whatsapp', 'both'])],
            'reminder_days_after_due' => ['required', 'integer', 'min:0', 'max:60'],
            'tpl_fee_due' => ['required', 'string', 'max:1000'],
            'tpl_payment' => ['required', 'string', 'max:1000'],
            'tpl_admission' => ['required', 'string', 'max:1000'],
        ]);
        foreach (['notify_payment', 'notify_admission', 'notify_fee_due_auto'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        foreach ($data as $k => $v) {
            Setting::put($k, (string) $v);
        }

        return back()->with('success', 'Notification settings saved.');
    }
}
