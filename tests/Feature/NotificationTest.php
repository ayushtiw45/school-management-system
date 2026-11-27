<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\Student;
use App\Services\MessageGateway;
use Illuminate\Support\Facades\Http;

class NotificationTest extends SchoolTestCase
{
    public function test_admission_and_payment_messages_are_logged_without_gateway(): void
    {
        $student = $this->admit();
        $this->assertSame(1, NotificationLog::where('type', 'admission')->count());

        $fee = $student->fees()->first();
        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => 100]], 'payment_date' => now()->toDateString(), 'method' => 'cash',
        ]);

        $log = NotificationLog::where('type', 'payment')->first();
        $this->assertSame('logged', $log->status);
        $this->assertSame('919876543210', $log->recipient);
        $this->assertStringContainsString('Rs.100.00', $log->message);
        $this->assertStringContainsString($student->full_name, $log->message);
    }

    public function test_http_gateway_sends_and_records_status(): void
    {
        config(['services.sms.driver' => 'http', 'services.sms.url' => 'https://sms.example.test/send?to={to}&msg={message}']);
        Http::fake(['sms.example.test/*' => Http::response('OK', 200)]);

        $this->admit();

        $log = NotificationLog::first();
        $this->assertSame('sent', $log->status);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'to=919876543210') && str_contains($req->url(), 'msg=Dear%20Parent'));
    }

    public function test_failed_gateway_can_be_retried(): void
    {
        config(['services.sms.driver' => 'http', 'services.sms.url' => 'https://sms.example.test/send?to={to}&msg={message}']);
        Http::fakeSequence('sms.example.test/*')->push('error', 500)->push('ok', 200);

        $this->admit();
        $log = NotificationLog::first();
        $this->assertSame('failed', $log->status);

        $this->actingAs($this->user('accountant'))->post(route('notifications.retry', $log))->assertRedirect();
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_switching_off_payment_messages(): void
    {
        Setting::put('notify_payment', '0');
        $student = $this->admit();
        $fee = $student->fees()->first();
        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => 100]], 'payment_date' => now()->toDateString(), 'method' => 'cash',
        ]);
        $this->assertSame(0, NotificationLog::where('type', 'payment')->count());
    }

    public function test_fee_reminders_from_pending_page_and_command(): void
    {
        $student = $this->admit(); // admitted at session start, so early dues are overdue
        $this->actingAs($this->user('accountant'))
            ->post(route('notifications.remind'), ['student_ids' => [$student->id], 'channel' => 'both'])
            ->assertSessionHas('success');

        $this->assertSame(2, NotificationLog::where('type', 'fee_due')->count()); // sms + whatsapp
        $this->assertStringContainsString('pending', NotificationLog::where('type', 'fee_due')->first()->message);

        // automatic command is off by default, and skips students reminded this week when on
        $this->artisan('fees:send-reminders')->expectsOutputToContain('off')->assertSuccessful();
        Setting::put('notify_fee_due_auto', '1');
        $this->artisan('fees:send-reminders')->expectsOutputToContain('0 student')->assertSuccessful();
    }

    public function test_announcement_goes_once_per_parent_mobile(): void
    {
        $this->admit();
        $this->admit(['first_name' => 'Sibling']); // same father phone
        $this->admit(['first_name' => 'Other', 'father_phone' => '9123456780']);
        NotificationLog::query()->delete();

        $this->actingAs($this->user())->post(route('notifications.announce.send'), [
            'channel' => 'sms', 'message' => 'Holiday tomorrow. - {school}',
        ])->assertRedirect();

        $this->assertSame(2, NotificationLog::where('type', 'announcement')->count());
        $this->assertStringContainsString('My School', NotificationLog::first()->message);
    }

    public function test_pages_render_and_settings_save(): void
    {
        $this->admit();
        $this->actingAs($this->user());
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('notifications.announce'))->assertOk();
        $this->get(route('notifications.settings'))->assertOk();
        $this->get(route('fees.pending'))->assertOk()->assertSee('wa.me/919876543210', false);

        $this->put(route('notifications.settings.update'), [
            'notify_channel' => 'whatsapp', 'reminder_days_after_due' => 5,
            'tpl_fee_due' => 'Due {amount}', 'tpl_payment' => 'Paid {amount}', 'tpl_admission' => 'Welcome {student}', 'notify_payment' => 1,
        ])->assertSessionHas('success');
        $this->assertSame('whatsapp', Setting::get('notify_channel'));
        $this->assertSame('0', Setting::get('notify_admission'));

        $this->actingAs($this->user('accountant'))->get(route('notifications.settings'))->assertForbidden();
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('919876543210', MessageGateway::normalize('98765 43210'));
        $this->assertSame('919876543210', MessageGateway::normalize('+91-9876543210'));
        $this->assertSame('919876543210', MessageGateway::normalize('09876543210'));
        $this->assertNull(MessageGateway::normalize('12345'));
    }
}
