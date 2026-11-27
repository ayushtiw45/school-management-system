<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr>
            @foreach($report->columns as $col => $c)
                <th class="{{ in_array($report->type($col), ['money', 'int']) ? 'text-end' : '' }} text-nowrap">{{ $c['label'] }}</th>
            @endforeach
        </tr></thead>
        <tbody>
        @forelse($report->rows as $row)
            <tr>
                @foreach($report->columns as $col => $c)
                    <td class="{{ in_array($report->type($col), ['money', 'int']) ? 'text-end' : '' }}">{{ $report->format($col, $row[$col] ?? null) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report->columns) }}" class="text-center text-muted py-4">No records for the selected filters.</td></tr>
        @endforelse
        </tbody>
        @if($report->totals && count($report->rows))
            <tfoot class="table-light fw-semibold"><tr>
                @foreach($report->columns as $col => $c)
                    <td class="{{ in_array($report->type($col), ['money', 'int']) ? 'text-end' : '' }} text-nowrap">{{ $report->format($col, $report->totals[$col] ?? null) }}</td>
                @endforeach
            </tr></tfoot>
        @endif
    </table>
</div>
