<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transport\TransportExpense;
use App\Models\Transport\Vehicle;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    use StoresBills;

    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $q = TransportExpense::with('vehicle')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->when($request->vehicle_id, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c));
        $byCategory = (clone $q)->groupBy('category')->selectRaw('category, SUM(amount) as total')->pluck('total', 'category');
        $expenses = $q->latest('expense_date')->latest('id')->paginate(40)->withQueryString();

        return view('transport.expenses.index', ['expenses' => $expenses, 'byCategory' => $byCategory, 'from' => $from, 'to' => $to, 'vehicles' => Vehicle::orderBy('bus_no')->get()]);
    }

    public function create(Request $request)
    {
        return view('transport.expenses.form', ['expense' => new TransportExpense(['expense_date' => now(), 'vehicle_id' => $request->vehicle_id, 'category' => $request->category ?? 'misc', 'method' => 'cash']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('bus_no')->get()]);
    }

    /** Monthly EMI and rent of all vehicles in one click. */
    public function recurring(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $date = \Carbon\Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->endOfMonth()->min(now());
        $n = 0;
        foreach (Vehicle::where('is_active', true)->get() as $v) {
            foreach (['emi' => $v->monthly_emi, 'rent' => $v->monthly_rent] as $cat => $amount) {
                if ((float) $amount <= 0) {
                    continue;
                }
                $title = TransportExpense::CATEGORIES[$cat].' - '.$date->format('M Y');
                if (! TransportExpense::where('vehicle_id', $v->id)->where('category', $cat)->where('title', $title)->exists()) {
                    TransportExpense::create(['vehicle_id' => $v->id, 'expense_date' => $date, 'category' => $cat, 'title' => $title, 'amount' => $amount,
                        'method' => 'bank', 'created_by' => $request->user()->id]);
                    $n++;
                }
            }
        }

        return back()->with('success', $n ? "{$n} EMI / rent entr(ies) added for ".$date->format('M Y').'.' : 'EMI and rent are already recorded for that month.');
    }

    public function store(Request $request)
    {
        TransportExpense::create($this->validated($request) + $this->storeBill($request, 'expenses') + ['created_by' => $request->user()->id]);

        return redirect()->route('transport.expenses.index')->with('success', 'Expense saved.');
    }

    public function edit(TransportExpense $expense)
    {
        return view('transport.expenses.form', ['expense' => $expense, 'vehicles' => Vehicle::orderBy('bus_no')->get()]);
    }

    public function update(Request $request, TransportExpense $expense)
    {
        $expense->update($this->validated($request) + $this->storeBill($request, 'expenses', $expense));

        return redirect()->route('transport.expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(TransportExpense $expense)
    {
        $this->deleteWithBill($expense);

        return back()->with('success', 'Expense deleted.');
    }

    public function bill(TransportExpense $expense)
    {
        return $this->billResponse($expense);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'vehicle_id' => ['nullable', Tenant::exists('vehicles')], 'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(TransportExpense::CATEGORIES))], 'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'], 'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'notes' => ['nullable', 'string', 'max:300'], 'bill' => self::BILL_RULE,
        ]);
        unset($data['bill']);

        return $data;
    }
}
