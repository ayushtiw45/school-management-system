<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ExpenseTest extends SchoolTestCase
{
    public function test_add_edit_delete_expense_with_bill(): void
    {
        Storage::fake('local');
        $cat = ExpenseCategory::where('name', 'Electricity & Utilities')->first();
        $this->actingAs($this->user('accountant'));

        $this->post(route('expenses.store'), [
            'expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'title' => 'Bill', 'amount' => 4500,
            'payment_method' => 'upi', 'bill' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf'),
        ])->assertRedirect(route('expenses.index'));

        $e = Expense::first();
        Storage::disk('local')->assertExists($e->bill_path);
        $this->get(route('expenses.bill', $e))->assertOk();
        $this->get(route('expenses.index'))->assertOk()->assertSee('4,500.00');
        $this->get(route('expenses.edit', $e))->assertOk();

        $this->put(route('expenses.update', $e), ['expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'title' => 'Bill', 'amount' => 5000, 'payment_method' => 'cash'])->assertRedirect();
        $this->assertEquals(5000, $e->fresh()->amount);

        $this->delete(route('expenses.destroy', $e))->assertRedirect();
        $this->assertSame(0, Expense::count());
        Storage::disk('local')->assertMissing($e->bill_path);
    }

    public function test_salary_needs_staff_name_and_month(): void
    {
        $cat = ExpenseCategory::where('is_salary', true)->first();
        $this->actingAs($this->user())->post(route('expenses.store'), [
            'expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'title' => 'Salary', 'amount' => 15000, 'payment_method' => 'bank',
        ])->assertSessionHasErrors(['paid_to', 'salary_month']);
    }

    public function test_category_with_expenses_cannot_be_deleted(): void
    {
        $cat = ExpenseCategory::first();
        Expense::create(['expense_category_id' => $cat->id, 'expense_date' => now(), 'title' => 'x', 'amount' => 10, 'payment_method' => 'cash']);
        $this->actingAs($this->user())->delete(route('expense-categories.destroy', $cat))->assertSessionHas('error');
        $this->get(route('expense-categories.index'))->assertOk();
    }

    public function test_receptionist_cannot_see_expenses(): void
    {
        $this->actingAs($this->user('receptionist'))->get(route('expenses.index'))->assertForbidden();
    }
}
