<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $type === 'low_stock_check' ? 'Low Stock Check Report' : 'Restock Report' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            color: #1a202c;
            background: #fff;
            padding: 24px 28px;
        }

        /* ─── Header ─────────────────────────────── */
        .header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .store-name {
            font-size: 20px;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: -0.3px;
        }
        .report-title {
            font-size: 13px;
            color: #374151;
            margin-top: 2px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-meta {
            text-align: right;
            color: #6b7280;
            font-size: 10px;
            line-height: 1.6;
        }
        .header-meta strong {
            color: #374151;
        }

        /* ─── Filter info bar ─────────────────────── */
        .filter-bar {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 14px;
            display: flex;
            gap: 24px;
            font-size: 10px;
            color: #0369a1;
        }
        .filter-bar span strong { color: #1e40af; }

        /* ─── Summary cards ───────────────────────── */
        .summary-grid {
            display: flex;
            gap: 0;
            margin-bottom: 16px;
        }
        .summary-card {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 14px;
            margin-right: 10px;
        }
        .summary-card:last-child { margin-right: 0; }
        .summary-card .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #1e40af;
            font-family: 'Courier New', monospace;
        }
        .summary-card .value.green { color: #059669; }

        /* ─── Section heading ────────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }

        /* ─── Tables ─────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead tr {
            background: #1e40af;
            color: #fff;
        }
        thead th {
            padding: 7px 8px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
            text-align: left;
        }
        thead th:last-child { text-align: right; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #eff6ff; }
        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10.5px;
            color: #374151;
            vertical-align: middle;
        }
        tbody td:last-child { text-align: right; }
        .mono { font-family: 'Courier New', monospace; font-size: 10px; }
        .badge-low { color: #dc2626; font-weight: 700; }
        .badge-ok  { color: #059669; }
        .qty-added { color: #1e40af; font-weight: 700; }

        /* ─── Signature block ────────────────────── */
        .signature-section {
            margin-top: 28px;
            display: flex;
            gap: 30px;
        }
        .signature-box {
            flex: 1;
            border-top: 1px solid #374151;
            padding-top: 6px;
        }
        .signature-box .sig-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
        }
        .signature-box .sig-name {
            font-size: 11px;
            font-weight: 600;
            color: #111827;
            margin-top: 2px;
        }
        .signature-spacer { height: 28px; }

        /* ─── Footer ─────────────────────────────── */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }
        .page-num:after { content: counter(page); }
    </style>
</head>
<body>

    {{-- ═══ HEADER ═══ --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="store-name">{{ $storeName }}</div>
                <div class="report-title">
                    @if($type === 'low_stock_check')
                        Low Stock Check Report
                    @else
                        Inventory Restock Log
                    @endif
                </div>
            </div>
            <div class="header-meta">
                <div><strong>Date Generated:</strong> {{ now()->format('M d, Y — h:i A') }}</div>
                <div><strong>Prepared By:</strong> {{ $preparedBy->name }}</div>
                <div><strong>Role:</strong> {{ ucfirst($preparedBy->role) }}</div>
            </div>
        </div>
    </div>

    {{-- ═══ FILTER BAR ═══ --}}
    <div class="filter-bar">
        <span><strong>Date Range:</strong>
            {{ $dateFrom->format('M d, Y') }}
            @if($dateFrom->toDateString() !== $dateTo->toDateString())
                &mdash; {{ $dateTo->format('M d, Y') }}
            @endif
        </span>
        @if($type === 'restock_history')
            <span><strong>Report Type:</strong> Restock History Log</span>
        @else
            <span><strong>Report Type:</strong> Low Stock Checklist</span>
        @endif
    </div>

    @if($type === 'restock_history')

        {{-- ═══ SUMMARY CARDS ═══ --}}
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Restock Events</div>
                <div class="value">{{ $summary['total_items_restocked'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Total Qty Added</div>
                <div class="value green">+{{ number_format($summary['total_qty_added']) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Period</div>
                <div class="value" style="font-size:13px; padding-top:4px;">
                    {{ $dateFrom->format('d M Y') }}
                </div>
            </div>
        </div>

        {{-- ═══ RESTOCK LOG TABLE ═══ --}}
        <div class="section-title">Restock Activity Log</div>

        @if($logs->isEmpty())
            <p style="color:#9ca3af; font-style:italic; padding:12px 0;">No restock activities found for the selected period.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:28px">#</th>
                        <th style="width:68px">Time</th>
                        <th>Product Name</th>
                        <th style="width:70px">SKU</th>
                        <th style="width:70px">Category</th>
                        <th style="width:55px; text-align:center">Prev Stock</th>
                        <th style="width:55px; text-align:center">Qty Added</th>
                        <th style="width:55px; text-align:center">New Stock</th>
                        <th style="width:80px">Restocked By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $i => $log)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="mono">{{ \Carbon\Carbon::parse($log->created_at)->format('H:i') }}</td>
                        <td style="font-weight:600">{{ $log->product?->name ?? '—' }}</td>
                        <td class="mono">{{ $log->product?->sku ?? '—' }}</td>
                        <td>{{ $log->product?->category?->name ?? '—' }}</td>
                        <td style="text-align:center" class="mono">{{ $log->previous_stock }}</td>
                        <td style="text-align:center" class="mono qty-added">+{{ $log->quantity_added }}</td>
                        <td style="text-align:center" class="mono">{{ $log->new_stock }}</td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        <td style="color:#6b7280; font-style:italic">{{ $log->note ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @else

        {{-- ═══ LOW STOCK CHECKLIST ═══ --}}
        <div class="section-title">Low Stock Checklist (Stock &le; 10 units)</div>

        @if($products->isEmpty())
            <p style="color:#059669; font-style:italic; padding:12px 0;">✓ All products have sufficient stock levels.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:28px">#</th>
                        <th>Product Name</th>
                        <th style="width:70px">SKU</th>
                        <th>Category</th>
                        <th style="width:70px; text-align:center">Current Stock</th>
                        <th style="width:70px">Status</th>
                        <th>Action Taken</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $i => $product)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td style="font-weight:600">{{ $product->name }}</td>
                        <td class="mono">{{ $product->sku }}</td>
                        <td>{{ $product->category?->name ?? '—' }}</td>
                        <td style="text-align:center" class="mono {{ $product->stock_qty <= 0 ? 'badge-low' : ($product->stock_qty <= 5 ? 'badge-low' : '') }}">
                            {{ $product->stock_qty }}
                        </td>
                        <td>
                            @if($product->stock_qty <= 0)
                                <span class="badge-low">OUT OF STOCK</span>
                            @else
                                <span class="badge-low">LOW STOCK</span>
                            @endif
                        </td>
                        <td style="color:#d1d5db">_______________</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @endif

    {{-- ═══ SIGNATURE LINES ═══ --}}
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-spacer"></div>
            <div class="sig-label">Stocker Signature</div>
            <div class="sig-name">{{ $preparedBy->role === 'stocker' ? $preparedBy->name : '________________________' }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-spacer"></div>
            <div class="sig-label">Supervisor / Admin Approval</div>
            <div class="sig-name">________________________</div>
        </div>
        <div class="signature-box">
            <div class="signature-spacer"></div>
            <div class="sig-label">Date Approved</div>
            <div class="sig-name">________________________</div>
        </div>
    </div>

    {{-- ═══ FOOTER ═══ --}}
    <div class="footer">
        Generated by {{ $storeName }} POS System &mdash; {{ now()->format('M d, Y h:i A') }}
        &nbsp;&nbsp;|&nbsp;&nbsp; Page <span class="page-num"></span>
    </div>

</body>
</html>
