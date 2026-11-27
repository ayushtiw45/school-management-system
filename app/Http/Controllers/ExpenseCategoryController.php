<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return view('expenses.categories', ['categories' => ExpenseCategory::withCount('expenses')->withSum('expenses', 'amount')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        ExpenseCategory::create($this->validated($request));

        return back()->with('success', 'Expense category added.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($this->validated($request, $expenseCategory));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', 'This category has expenses and cannot be deleted.');
        }
        $expenseCategory->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?ExpenseCategory $cat = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->ignore($cat?->id)],
        ]);
        $data['is_salary'] = $request->boolean('is_salary');

        return $data;
    }
}
