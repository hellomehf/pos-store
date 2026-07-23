<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #222; margin: 24px; }
        .header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #3271a8; padding-bottom: 12px; }
        .header h2 { margin: 0; font-size: 18px; color: #1b212a; }
        .header small { color: #666; font-size: 11px; }
        .section { margin-bottom: 18px; }
        .section h3 { font-size: 13px; color: #3271a8; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.3px; padding: 5px 8px; border-bottom: 2px solid #ddd; }
        table td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 12px; }
        table th:last-child, table td:last-child { text-align: right; }
        table th:nth-child(2), table td:nth-child(2) { text-align: center; }
        .summary-grid { display: flex; flex-wrap: wrap; gap: 0; }
        .summary-item { width: 33.33%; box-sizing: border-box; padding: 10px; }
        .summary-item .label { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.3px; }
        .summary-item .value { font-size: 18px; font-weight: 700; color: #1b212a; margin-top: 2px; font-family: monospace; }
        .summary-item .value.green { color: #28a745; }
        .summary-item .value.red { color: #dc3545; }
        .summary-item .value.blue { color: #3271a8; }
        .summary-item .value.purple { color: #7732a8; }
        .payment-row td:last-child { font-weight: 600; }
        .footer { text-align: center; margin-top: 20px; color: #999; font-size: 10px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Sales Report</h2>
        <small>{{ $dateFrom->format('M d, Y') }} &mdash; {{ $dateTo->format('M d, Y') }}</small>
    </div>

    <div class="section">
        <h3>Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">Total Revenue</div>
                <div class="value green">${{ number_format($summary['total_revenue'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Total Expenses (COGS)</div>
                <div class="value red">${{ number_format($summary['total_expenses'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Net Profit</div>
                <div class="value {{ $summary['net_profit'] >= 0 ? 'green' : 'red' }}">${{ number_format($summary['net_profit'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Tax Collected</div>
                <div class="value blue">${{ number_format($summary['total_tax'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Transactions</div>
                <div class="value purple">{{ $summary['total_transactions'] }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Avg Transaction</div>
                <div class="value">${{ number_format($summary['avg_transaction'], 2) }}</div>
            </div>
        </div>
    </div>

    @if(count($salesData) > 0)
    <div class="section">
        <h3>Revenue by Day</h3>
        <table>
            <thead><tr><th>Date</th><th>Transactions</th><th>Revenue</th></tr></thead>
            <tbody>
                @foreach($salesData as $row)
                <tr>
                    <td>{{ $row->date }}</td>
                    <td>{{ $row->transactions }}</td>
                    <td>${{ number_format($row->revenue, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($paymentBreakdown) > 0)
    <div class="section">
        <h3>Payment Methods</h3>
        <table>
            <thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead>
            <tbody>
                @foreach($paymentBreakdown as $pb)
                <tr class="payment-row">
                    <td style="text-transform: capitalize">{{ $pb->payment_method }}</td>
                    <td>{{ $pb->count }}</td>
                    <td>${{ number_format($pb->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($categoryData) > 0)
    <div class="section">
        <h3>Top Products</h3>
        <table>
            <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
            <tbody>
                @foreach($categoryData as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->total_qty }}</td>
                    <td>${{ number_format($item->total_revenue, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">Generated by POS System &mdash; {{ now()->format('M d, Y h:i A') }}</div>
</body>
</html>
