import { useState, useEffect, useRef } from 'react'
import { Bar, Pie } from 'react-chartjs-2'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend, ArcElement } from 'chart.js'
import api from '../api/api'
import { useToast } from '../contexts/ToastContext'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend)

export default function Reports() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [dateFrom, setDateFrom] = useState(() => { const d = new Date(); d.setDate(1); return d.toISOString().split('T')[0] })
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().split('T')[0])
  const [period, setPeriod] = useState('daily')
  const [dailyReports, setDailyReports] = useState([])
  const [dailyPage, setDailyPage] = useState(1)
  const [dailyMeta, setDailyMeta] = useState(null)
  const [dailyLoading, setDailyLoading] = useState(false)
  const [exportOpen, setExportOpen] = useState(null)
  const dropdownRef = useRef(null)
  const toast = useToast()

  const load = () => {
    setLoading(true)
    api.get(`/reports?period=${period}&date_from=${dateFrom}&date_to=${dateTo}`)
      .then(res => setData(res.data))
      .catch(() => toast.error('Failed to load reports'))
      .finally(() => setLoading(false))
  }

  const handleExportPdf = () => {
    api.get(`/reports/export-pdf?date_from=${dateFrom}&date_to=${dateTo}`, {
      responseType: 'blob',
      headers: { 'Accept': 'application/pdf' },
    })
      .then(res => {
        if (res.data.size === 0) { toast.error('Export failed'); return }
        const a = document.createElement('a')
        a.href = URL.createObjectURL(res.data)
        a.download = `sales_report_${dateFrom}_to_${dateTo}.pdf`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(a.href)
        toast.success('PDF report downloaded')
      })
      .catch(() => toast.error('Failed to generate PDF report'))
  }

  const loadDaily = (page = 1) => {
    setDailyLoading(true)
    api.get(`/daily-reports?page=${page}`)
      .then(res => {
        setDailyReports(res.data.data)
        setDailyMeta({ current_page: res.data.current_page, last_page: res.data.last_page })
        setDailyPage(res.data.current_page)
      })
      .catch(() => toast.error('Failed to load daily reports'))
      .finally(() => setDailyLoading(false))
  }

  useEffect(() => { load(); loadDaily() }, [])

  useEffect(() => {
    const handler = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) setExportOpen(null)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const handleExport = (id, format) => {
    const ext = format === 'pdf' ? 'pdf' : 'csv'
    const mimeTypes = { csv: 'text/csv', pdf: 'application/pdf' }
    api.get(`/daily-reports/${id}/export/${format}`, {
      responseType: 'blob',
      headers: { 'Accept': mimeTypes[format] },
    })
      .then(res => {
        if (res.data.size === 0) { toast.error('Export failed'); return }
        const a = document.createElement('a')
        a.href = URL.createObjectURL(res.data)
        a.download = `daily-report-${id}.${ext}`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(a.href)
        toast.success(`Exported as ${ext.toUpperCase()}`)
      })
      .catch(() => toast.error('Export failed'))
    setExportOpen(null)
  }

  const barData = data ? {
    labels: data.sales_data.map(d => d.date),
    datasets: [{
      label: 'Revenue',
      data: data.sales_data.map(d => parseFloat(d.revenue)),
      backgroundColor: 'rgba(50, 113, 168, 0.7)',
      borderColor: '#3271a8',
      borderWidth: 1,
      borderRadius: 4,
    }]
  } : null

  const pieData = data ? {
    labels: data.payment_breakdown.map(d => d.payment_method),
    datasets: [{
      data: data.payment_breakdown.map(d => parseFloat(d.total)),
      backgroundColor: ['#3271a8', '#7732a8', '#e8a33d'],
      borderWidth: 0,
    }]
  } : null

  const chartOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#8b95a5' } } },
    scales: {
      x: { grid: { color: 'rgba(46,55,66,0.5)' }, ticks: { color: '#8b95a5', font: { size: 11 } } },
      y: { grid: { color: 'rgba(46,55,66,0.5)' }, ticks: { color: '#8b95a5', font: { family: 'IBM Plex Mono', size: 11 } } },
    },
  }

  const fmtDate = (d) => {
    if (!d) return '—'
    const parsed = new Date(d)
    if (isNaN(parsed.getTime())) return '—'
    return parsed.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
  }

  return (
    <div>
      <div className="page-header">
        <h4>Reports</h4>
      </div>

      <div className="filter-bar">
        <div>
          <label className="form-label-dark" style={{ marginBottom: 2 }}>From</label>
          <input type="date" className="form-control-dark" value={dateFrom} onChange={e => setDateFrom(e.target.value)} style={{ width: 160 }} />
        </div>
        <div>
          <label className="form-label-dark" style={{ marginBottom: 2 }}>To</label>
          <input type="date" className="form-control-dark" value={dateTo} onChange={e => setDateTo(e.target.value)} style={{ width: 160 }} />
        </div>
        <button className="btn-primary-custom" onClick={load} disabled={loading}>
          {loading ? <i className="fas fa-spinner fa-spin" /> : <><i className="fas fa-sync" /> Generate</>}
        </button>
        <button className="btn-outline-custom" onClick={handleExportPdf} disabled={loading} style={{ marginLeft: 6 }}>
          <i className="fas fa-file-pdf" /> Export PDF
        </button>
      </div>

      {data && (
        <>
          <div className="row g-3 mb-4">
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card success"><div className="stat-card-icon"><i className="fas fa-dollar-sign" /></div><div><div className="stat-card-value">${data.summary.total_revenue.toFixed(2)}</div><div className="stat-card-label">Total Revenue</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card danger"><div className="stat-card-icon"><i className="fas fa-receipt" /></div><div><div className="stat-card-value">${data.summary.total_expenses.toFixed(2)}</div><div className="stat-card-label">Total Spend (COGS)</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-card-icon"><i className="fas fa-chart-line" /></div><div><div className="stat-card-value">${data.summary.net_profit.toFixed(2)}</div><div className="stat-card-label">Net Profit</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card warning"><div className="stat-card-icon"><i className="fas fa-coins" /></div><div><div className="stat-card-value">{data.summary.total_transactions}</div><div className="stat-card-label">Transactions</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-card-icon"><i className="fas fa-percent" /></div><div><div className="stat-card-value">${data.summary.total_tax.toFixed(2)}</div><div className="stat-card-label">Tax Collected</div></div></div>
            </div>
            <div className="col-sm-6 col-xl-3">
              <div className="stat-card"><div className="stat-card-icon"><i className="fas fa-calculator" /></div><div><div className="stat-card-value">${data.summary.avg_transaction.toFixed(2)}</div><div className="stat-card-label">Avg Transaction</div></div></div>
            </div>
          </div>

          <div className="row g-3 mb-4">
            <div className="col-lg-8">
              <div className="chart-container">
                <h6 style={{ marginBottom: 16 }}>Revenue by Day</h6>
                <div style={{ height: 280 }}>
                  {barData && <Bar data={barData} options={chartOpts} />}
                </div>
              </div>
            </div>
            <div className="col-lg-4">
              <div className="chart-container">
                <h6 style={{ marginBottom: 16 }}>Payment Methods</h6>
                <div style={{ height: 280, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  {pieData && data.payment_breakdown.length > 0 ? (
                    <Pie data={pieData} options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#8b95a5' } } } }} />
                  ) : (
                    <div className="empty-state"><p>No payment data</p></div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {data.category_data.length > 0 && (
            <div className="card-dark mb-4">
              <h6 style={{ marginBottom: 16 }}>Top Products</h6>
              <div className="table-wrapper" style={{ border: 'none' }}>
                <table className="table-dark-custom">
                  <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                  <tbody>
                    {data.category_data.map((d, i) => (
                      <tr key={i}>
                        <td>{d.product_name}</td>
                        <td><span className="mono">{d.total_qty}</span></td>
                        <td><span className="mono">${parseFloat(d.total_revenue).toFixed(2)}</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}

      <div className="card-dark">
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
          <h6 style={{ margin: 0 }}>Daily Report Snapshots</h6>
          <button className="btn-outline-custom" style={{ padding: '5px 12px', fontSize: 12 }} onClick={() => loadDaily(dailyPage)} disabled={dailyLoading}>
            <i className={`fas ${dailyLoading ? 'fa-spinner fa-spin' : 'fa-sync'}`} /> Refresh
          </button>
        </div>
        {dailyReports.length > 0 ? (
          <>
            <div className="table-wrapper" style={{ border: 'none' }}>
              <table className="table-dark-custom">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Revenue</th>
                    <th>Expenses</th>
                    <th>Net Profit</th>
                    <th>Tax</th>
                    <th>Transactions</th>
                    <th style={{ width: 110 }}>Export</th>
                  </tr>
                </thead>
                <tbody>
                  {dailyReports.map(r => {
                    const profitPositive = parseFloat(r.net_profit) >= 0
                    return (
                      <tr key={r.id}>
                        <td style={{ fontWeight: 500 }}>{fmtDate(r.report_date)}</td>
                        <td><span className="mono" style={{ color: 'var(--success)' }}>${parseFloat(r.gross_revenue).toFixed(2)}</span></td>
                        <td><span className="mono" style={{ color: 'var(--danger)' }}>${parseFloat(r.total_expenses).toFixed(2)}</span></td>
                        <td><span className="mono" style={{ color: profitPositive ? 'var(--success)' : 'var(--danger)', fontWeight: 600 }}>${parseFloat(r.net_profit).toFixed(2)}</span></td>
                        <td><span className="mono">${parseFloat(r.total_tax_collected).toFixed(2)}</span></td>
                        <td>
                          <span className="mono">{r.total_transactions}</span>
                          <span style={{ fontSize: 11, color: 'var(--text-muted)', marginLeft: 6 }}>
                            ({r.cash_count}c / {r.card_count}d)
                          </span>
                        </td>
                        <td>
                          <div style={{ position: 'relative' }} ref={exportOpen === r.id ? dropdownRef : undefined}>
                            <button
                              className="btn-outline-custom"
                              style={{ padding: '4px 10px', fontSize: 12, width: '100%' }}
                              onClick={() => setExportOpen(exportOpen === r.id ? null : r.id)}
                            >
                              <i className="fas fa-download" style={{ marginRight: 4 }} /> Export <i className="fas fa-caret-down" style={{ marginLeft: 4 }} />
                            </button>
                            {exportOpen === r.id && (
                              <div className="export-dropdown">
                                <button onClick={() => handleExport(r.id, 'csv')}>
                                  <i className="fas fa-file-csv" style={{ color: 'var(--success)', width: 18 }} /> Export as CSV
                                </button>
                                <button onClick={() => handleExport(r.id, 'pdf')}>
                                  <i className="fas fa-file-pdf" style={{ color: 'var(--danger)', width: 18 }} /> Export as PDF
                                </button>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            {dailyMeta && dailyMeta.last_page > 1 && (
              <div className="pagination-dark" style={{ justifyContent: 'center', marginTop: 16 }}>
                <button disabled={dailyPage <= 1} onClick={() => loadDaily(dailyPage - 1)}>
                  <i className="fas fa-chevron-left" />
                </button>
                <span style={{ padding: '6px 12px', fontSize: 13, color: 'var(--text-secondary)' }}>
                  Page {dailyPage} of {dailyMeta.last_page}
                </span>
                <button disabled={dailyPage >= dailyMeta.last_page} onClick={() => loadDaily(dailyPage + 1)}>
                  <i className="fas fa-chevron-right" />
                </button>
              </div>
            )}
          </>
        ) : (
          <div className="empty-state">
            <i className="fas fa-calendar-check" />
            <p>No daily reports generated yet.</p>
            <small style={{ color: 'var(--text-muted)' }}>Reports are auto-generated daily at 10:00 PM</small>
          </div>
        )}
      </div>
    </div>
  )
}
