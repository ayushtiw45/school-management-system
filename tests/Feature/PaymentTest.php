<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Setting;

class PaymentTest extends SchoolTestCase
{
    public function test_full_and_partial_payment_with_receipt(): void
    {
        $student = $this->admit();
        [$admission, $tuition] = $student->fees()->orderBy('id')->take(2)->get()->all();

        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [
                $admission->id => ['amount' => $admission->balance()],
                $tuition->id => ['amount' => 300],
            ],
            'payment_date' => now()->toDateString(),
            'method' => 'upi',
            'reference_no' => 'UPI123',
        ])->assertRedirect();

        $payment = Payment::first();
        $this->assertMatchesRegularExpression('/^RCPT-\d{4}-000001$/', $payment->receipt_no);
        $this->assertEquals($admission->balance() + 300, $payment->amount);
        $this->assertSame('paid', $admission->fresh()->status);
        $this->assertSame('partial', $tuition->fresh()->status);
        $this->assertEquals($tuition->amount - 300, $tuition->fresh()->balance());

        $this->get(route('payments.show', $payment))->assertOk()->assertSee('UPI123')->assertSee($student->full_name);
    }

    public function test_cannot_overpay_a_fee(): void
    {
        $student = $this->admit();
        $fee = $student->fees()->first();

        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => $fee->balance() + 1]],
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, Payment::count());
        $this->assertEquals(0, $fee->fresh()->paid);
    }

    public function test_fine_and_discount_at_collection(): void
    {
        $student = $this->admit();
        $fee = $student->fees()->where('title', 'like', 'Tuition%')->first();

        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => $fee->amount + 50 - 100, 'fine' => 50, 'discount' => 100]],
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
        ])->assertRedirect();

        $fee->refresh();
        $this->assertSame('paid', $fee->status);
        $this->assertEquals(50, $fee->fine);
        $this->assertEquals(100, $fee->discount);
    }

    public function test_non_cash_needs_reference(): void
    {
        $student = $this->admit();
        $fee = $student->fees()->first();

        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => 100]],
            'payment_date' => now()->toDateString(),
            'method' => 'cheque',
        ])->assertSessionHasErrors('reference_no');
    }

    public function test_admin_can_cancel_receipt_and_restore_dues(): void
    {
        $student = $this->admit();
        $fee = $student->fees()->first();
        $accountant = $this->user('accountant');

        $this->actingAs($accountant)->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => $fee->balance(), 'fine' => 20]],
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
        ]);
        $payment = Payment::first();

        $this->actingAs($accountant)->delete(route('payments.destroy', $payment))->assertForbidden();
        $this->actingAs($this->user())->delete(route('payments.destroy', $payment))->assertRedirect();

        $fee->refresh();
        $this->assertSame(0, Payment::count());
        $this->assertSame('unpaid', $fee->status);
        $this->assertEquals(0, $fee->fine);
    }

    public function test_receipt_numbers_increase(): void
    {
        Setting::put('receipt_prefix', 'R');
        $student = $this->admit();
        $user = $this->user('accountant');

        foreach ($student->fees()->take(3)->get() as $fee) {
            $this->actingAs($user)->post(route('payments.store', $student), [
                'lines' => [$fee->id => ['amount' => 10]],
                'payment_date' => now()->toDateString(),
                'method' => 'cash',
            ]);
        }

        $this->assertSame(['R-'.now()->year.'-000001', 'R-'.now()->year.'-000002', 'R-'.now()->year.'-000003'],
            Payment::orderBy('id')->pluck('receipt_no')->all());
    }
}
