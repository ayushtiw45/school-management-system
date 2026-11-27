<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 14mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; }
    .head { text-align: center; border-bottom: 2px solid #14213d; padding-bottom: 6px; margin-bottom: 8px; }
    .head h1 { font-size: 15px; margin: 0; color: #14213d; }
    .head .t { font-size: 11px; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
    .muted { color: #555; }
    .summary td { padding: 2px 10px 2px 0; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.data th { background: #14213d; color: #fff; padding: 4px; text-align: left; font-size: 8.5px; }
    table.data td { padding: 3px 4px; border-bottom: 1px solid #ddd; }
    table.data tr:nth-child(even) td { background: #f6f7f9; }
    table.data tfoot td { font-weight: bold; background: #e9ecf2; border-top: 1.5px solid #14213d; }
    .r { text-align: right; }
    .foot { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5px; color: #777; text-align: right; }
</style>
</head>
<body>
<div class="head">
    <h1>{{ school('name') }}</h1>
    @if(school('address'))<div class="muted">{{ school('address') }}</div>@endif
    <div class="t">{{ $report->title }}</div>
    <div class="muted">{{ $report->subtitle }}</div>
</div>
@if($report->summary)
    <table class="summary"><tr>
        @foreach($report->summary as $label => $value)<td><span class="muted">{{ $label }}:</span> <b>{{ str_replace('₹', 'Rs.', $value) }}</b></td>@if($loop->iteration % 4 === 0)</tr><tr>@endif @endforeach
    </tr></table>
@endif
<table class="data">
    <thead><tr>@foreach($report->columns as $col => $c)<th class="{{ in_array($report->type($col), ['money', 'int']) ? 'r' : '' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($report->rows as $row)
        <tr>@foreach($report->columns as $col => $c)<td class="{{ in_array($report->type($col), ['money', 'int']) ? 'r' : '' }}">{{ $report->format($col, $row[$col] ?? null) }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report->columns) }}" style="text-align:center;padding:12px">No records.</td></tr>
    @endforelse
    </tbody>
    @if($report->totals && count($report->rows))
        <tfoot><tr>@foreach($report->columns as $col => $c)<td class="{{ in_array($report->type($col), ['money', 'int']) ? 'r' : '' }}">{{ $report->format($col, $report->totals[$col] ?? null) }}</td>@endforeach</tr></tfoot>
    @endif
</table>
<div class="foot">Generated on {{ now()->format('d M Y, h:i A') }} · Amounts in Rs.</div>
</body>
</html>
