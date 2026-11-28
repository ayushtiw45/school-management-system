<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $query = Expense::with(['category', 'creator'])
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('title', 'like', "%$t%")->orWhere('paid_to', 'like', "%$t%")));

        $byCategory = (clone $query)->selectRaw('expense_category_id, SUM(amount) as total')->groupBy('expense_category_id')->pluck('total', 'expense_category_id');
        $total = $byCategory->sum();
        $expenses = $query->latest('expense_date')->latest('id')->paginate(30)->withQueryString();
        $categories = ExpenseCategory::orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories', 'byCategory', 'total', 'from', 'to'));
    }

    public function create()
    {
        return view('expenses.form', ['expense' => new Expense(['expense_date' => now(), 'payment_method' => 'cash']), 'categories' => ExpenseCategory::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('bill')) {
            $data['bill_path'] = $request->file('bill')->store('expenses');
        }
        Expense::create($data);

        return redirect()->route('expenses.index')->with('success', 'Expense saved.');
    }

    public function edit(Expense $expense)
    {
        return view('expenses.form', ['expense' => $expense, 'categories' => ExpenseCategory::orderBy('name')->get()]);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);
        if ($request->hasFile('bill')) {
            if ($expense->bill_path) {
                Storage::delete($expense->bill_path);
            }
            $data['bill_path'] = $request->file('bill')->store('expenses');
        }
        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        if ($expense->bill_path) {
            Storage::delete($expense->bill_path);
        }
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    public function bill(Expense $expense)
    {
        abort_unless($expense->bill_path && Storage::exists($expense->bill_path), 404);

        return Storage::response($expense->bill_path);
    }

    private function validated(Request $request): array
    {
        $isSalary = ExpenseCategory::whereKey($request->expense_category_id)->value('is_salary');

        return $request->validate([
            'expense_category_id' => ['required', \App\Support\Tenant::exists('expense_categories')],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_to' => [$isSalary ? 'required' : 'nullable', 'string', 'max:150'],
            'salary_month' => [$isSalary ? 'required' : 'nullable', 'date_format:Y-m'],
            'payment_method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'bill' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ], ['paid_to.required' => 'Enter the staff member\'s name.', 'salary_month.required' => 'Select the salary month.']);
    }
}
