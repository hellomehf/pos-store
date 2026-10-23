import { useState, useEffect, useCallback } from 'react'
import api from '../api/api'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'

const today = () => new Date().toISOString().split('T')[0]
const yesterday = () => {
  const d = new Date(); d.setDate(d.getDate() - 1); return d.toISOString().split('T')[0]
}
const daysAgo = (n) => {
  const d = new Date(); d.setDate(d.getDate() - n); return d.toISOString().split('T')[0]
}

const QUICK_OPTIONS = [
  { label: 'Today',        value: 'today'   },
  { label: 'Yesterday',    value: 'yesterday' },
  { label: 'Last 7 Days',  value: 'last7'   },
  { label: 'Custom Range', value: 'custom'  },
]

export default function StockReports() {
  const { user } = useAuth()
  const toast = useToast()

  const [tab, setTab]               = useState('restock_history')  // 'restock_history' | 'low_stock_check'
  const [quickPick, setQuickPick]   = useState('today')
  const [startDate, setStartDate]   = useState(today())
  const [endDate, setEndDate]       = useState(today())

  const [logs, setLogs]             = useState([])
  const [lowStock, setLowStock]     = useState([])
  const [summary, setSummary]       = useState({ total_items_restocked: 0, total_qty_added: 0 })
  const [loading, setLoading]       = useState(false)
  const [pdfLoading, setPdfLoading] = useState(false)

  // ── Resolve date range from quickPick ─────────────────────
  const resolvedDates = useCallback(() => {
    if (quickPick === 'today')     return { start: today(),     end: today()     }
    if (quickPick === 'yesterday') return { start: yesterday(), end: yesterday() }
    if (quickPick === 'last7')     return { start: daysAgo(6),  end: today()     }
    return { start: startDate, end: endDate }
  }, [quickPick, startDate, endDate])

  // ── Fetch restock logs ─────────────────────────────────────
  const fetchLogs = useCallback(() => {
    const { start, end } = resolvedDates()
    setLoading(true)
    api.get('/reports/restock', { params: { start_date: start, end_date: end } })
      .then(res => {
        setLogs(res.data.data)
        setSummary(res.data.summary)
      })
      .catch(() => toast.error('Failed to load restock logs'))
      .finally(() => setLoading(false))
  }, [resolvedDates, toast])

  // ── Fetch low-stock list ───────────────────────────────────
  const fetchLowStock = useCallback(() => {
    setLoading(true)
    api.get('/inventory/low-stock')
      .then(res => setLowStock(res.data))
      .catch(() => toast.error('Failed to load low stock list'))
      .finally(() => setLoading(false))
  }, [toast])

  useEffect(() => {
    if (tab === 'restock_history') fetchLogs()
    else fetchLowStock()
  }, [tab, quickPick, startDate, endDate])

  // ── PDF Download ───────────────────────────────────────────
  const handleDownloadPDF = async () => {
    setPdfLoading(true)
    const { start, end } = resolvedDates()
    try {
      const res = await api.get('/reports/restock/pdf', {
        params: { start_date: start, end_date: end, type: tab },
        responseType: 'blob',
      })
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `${tab === 'low_stock_check' ? 'low_stock' : 'restock_report'}_${start}.pdf`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      toast.success('PDF downloaded!')
    } catch {
      toast.error('Failed to generate PDF')
    } finally {
      setPdfLoading(false)
    }
  }

  const { start: resolvedStart, end: resolvedEnd } = resolvedDates()

  return (
    <div>
      {/* ── Page Header ── */}
      <div className="page-header">
        <h4><i className="fas fa-warehouse" style={{ marginRight: 10, color: 'var(--primary)' }} />Stock Reports</h4>
        <button
          className="btn-primary-custom"
          onClick={handleDownloadPDF}
          disabled={pdfLoading}
          id="btn-download-pdf"
          style={{ display: 'flex', alignItems: 'center', gap: 7 }}
        >
          {pdfLoading
            ? <><i className="fas fa-spinner fa-spin" /> Generating...</>
            : <><i className="fas fa-file-pdf" /> Download PDF</>
          }
        </button>
      </div>

      {/* ── Tab switcher ── */}
      <div style={{ display: 'flex', gap: 4, marginBottom: 16, background: 'var(--bg-card)', border: '1px solid var(--border-color)', borderRadius: 'var(--radius)', padding: 4, width: 'fit-content' }}>
        {[
          { id: 'restock_history',  icon: 'fa-history',    label: 'Restock Logs'       },
          { id: 'low_stock_check',  icon: 'fa-exclamation-triangle', label: 'Low Stock Checklist' },
        ].map(t => (
          <button
            key={t.id}
            id={`tab-${t.id}`}
            onClick={() => setTab(t.id)}
            style={{
              padding: '7px 16px', fontSize: 13, border: 'none', cursor: 'pointer',
              borderRadius: 'calc(var(--radius) - 2px)',
              background: tab === t.id ? 'var(--primary)' : 'transparent',
              color: tab === t.id ? '#fff' : 'var(--text-secondary)',
              fontWeight: tab === t.id ? 600 : 400,
              transition: 'all 0.15s',
              display: 'flex', alignItems: 'center', gap: 7,
            }}
          >
            <i className={`fas ${t.icon}`} style={{ fontSize: 11 }} />{t.label}
          </button>
        ))}
      </div>

      {/* ── Filter bar ── */}
      {tab === 'restock_history' && (
        <div className="filter-bar" style={{ flexWrap: 'wrap', gap: 10, marginBottom: 16 }}>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            {QUICK_OPTIONS.map(opt => (
              <button
                key={opt.value}
                id={`filter-${opt.value}`}
                onClick={() => setQuickPick(opt.value)}
                style={{
                  padding: '5px 12px', fontSize: 12, border: '1px solid',
                  borderRadius: 'var(--radius)', cursor: 'pointer',
                  borderColor: quickPick === opt.value ? 'var(--primary)' : 'var(--border-color)',
                  background: quickPick === opt.value ? 'rgba(59,130,246,0.12)' : 'transparent',
                  color: quickPick === opt.value ? 'var(--primary)' : 'var(--text-secondary)',
                  fontWeight: quickPick === opt.value ? 600 : 400,
                  transition: 'all 0.15s',
                }}
              >
                {opt.label}
              </button>
            ))}
          </div>

          {quickPick === 'custom' && (
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <input
                type="date"
                id="input-start-date"
                className="form-control-dark"
                style={{ width: 150, fontSize: 12 }}
                value={startDate}
                onChange={e => setStartDate(e.target.value)}
              />
              <span style={{ color: 'var(--text-muted)', fontSize: 12 }}>to</span>
              <input
                type="date"
                id="input-end-date"
                className="form-control-dark"
                style={{ width: 150, fontSize: 12 }}
                value={endDate}
                min={startDate}
                onChange={e => setEndDate(e.target.value)}
              />
            </div>
          )}
        </div>
      )}

      {/* ── Summary cards (restock tab only) ── */}
      {tab === 'restock_history' && (
        <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
          {[
            {
              icon: 'fa-boxes-stacked',
              label: 'Total Restock Events',
              value: summary.total_items_restocked,
              color: 'var(--primary)',
              bg: 'rgba(59,130,246,0.08)',
            },
            {
              icon: 'fa-arrow-trend-up',
              label: 'Total Qty Added',
              value: `+${(summary.total_qty_added || 0).toLocaleString()}`,
              color: 'var(--success, #10b981)',
              bg: 'rgba(16,185,129,0.08)',
            },
            {
              icon: 'fa-calendar',
              label: 'Date Range',
              value: resolvedStart === resolvedEnd ? resolvedStart : `${resolvedStart} → ${resolvedEnd}`,
              color: 'var(--text-secondary)',
              bg: 'var(--bg-card)',
              small: true,
            },
          ].map((card, i) => (
            <div key={i} style={{
              flex: '1 1 160px', minWidth: 160,
              background: card.bg,
              border: '1px solid var(--border-color)',
              borderRadius: 'var(--radius-lg)',
              padding: '14px 18px',
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                <i className={`fas ${card.icon}`} style={{ color: card.color, fontSize: 14 }} />
                <span style={{ fontSize: 11, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.4px' }}>{card.label}</span>
              </div>
              <div style={{
                fontSize: card.small ? 13 : 26,
                fontWeight: 700,
                color: card.color,
                fontFamily: card.small ? 'inherit' : 'var(--font-mono, monospace)',
              }}>
                {card.value}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* ── Restock Logs Table ── */}
      {tab === 'restock_history' && (
        <div className="table-wrapper">
          {loading ? (
            <div className="d-flex justify-content-center py-5">
              <i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} />
            </div>
          ) : logs.length === 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '48px 24px', textAlign: 'center', color: 'var(--text-muted)' }}>
              <i className="fas fa-inbox" style={{ fontSize: 32, marginBottom: 12, opacity: 0.6 }} />
              <p style={{ fontSize: 14 }}>No restock activity for this period.</p>
            </div>
          ) : (
            <table className="table-dark-custom" id="table-restock-logs">
              <thead>
                <tr>
                  <th style={{ width: 36 }}>#</th>
                  <th>Time</th>
                  <th>Product Name</th>
                  <th>SKU</th>
                  <th>Category</th>
                  <th style={{ textAlign: 'center' }}>Prev</th>
                  <th style={{ textAlign: 'center' }}>Added</th>
                  <th style={{ textAlign: 'center' }}>New Stock</th>
                  <th>Restocked By</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log, i) => (
                  <tr key={log.id}>
                    <td style={{ color: 'var(--text-muted)', fontSize: 12 }}>{i + 1}</td>
                    <td>
                      <span className="mono" style={{ fontSize: 12 }}>
                        {new Date(log.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                      </span>
                      <br />
                      <span style={{ fontSize: 10, color: 'var(--text-muted)' }}>
                        {new Date(log.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                      </span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{log.product?.name ?? '—'}</td>
                    <td><span className="mono" style={{ fontSize: 11, color: 'var(--text-secondary)' }}>{log.product?.sku ?? '—'}</span></td>
                    <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{log.product?.category?.name ?? '—'}</td>
                    <td style={{ textAlign: 'center' }}>
                      <span className="mono" style={{ fontSize: 12, color: 'var(--text-muted)' }}>{log.previous_stock}</span>
                    </td>
                    <td style={{ textAlign: 'center' }}>
                      <span className="mono" style={{ fontSize: 13, fontWeight: 700, color: 'var(--primary)' }}>+{log.quantity_added}</span>
                    </td>
                    <td style={{ textAlign: 'center' }}>
                      <span className="mono" style={{ fontSize: 12, color: 'var(--success, #10b981)', fontWeight: 600 }}>{log.new_stock}</span>
                    </td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        <div style={{ width: 22, height: 22, borderRadius: '50%', background: 'var(--bg-hover, #2a2f3e)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 9, fontWeight: 700, color: 'var(--text-secondary)', flexShrink: 0 }}>
                          {log.user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                        </div>
                        <span style={{ fontSize: 12 }}>{log.user?.name ?? '—'}</span>
                      </div>
                    </td>
                    <td style={{ fontSize: 12, color: 'var(--text-muted)', fontStyle: log.note ? 'normal' : 'italic' }}>
                      {log.note || '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* ── Low Stock Checklist Table ── */}
      {tab === 'low_stock_check' && (
        <div className="table-wrapper">
          {loading ? (
            <div className="d-flex justify-content-center py-5">
              <i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} />
            </div>
          ) : lowStock.length === 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '48px 24px', textAlign: 'center' }}>
              <i className="fas fa-check-circle" style={{ fontSize: 36, color: '#10b981', marginBottom: 12 }} />
              <p style={{ fontSize: 14, color: 'var(--text-secondary)' }}>All products have sufficient stock. No low-stock items found!</p>
            </div>
          ) : (
            <table className="table-dark-custom" id="table-low-stock">
              <thead>
                <tr>
                  <th style={{ width: 36 }}>#</th>
                  <th>Product Name</th>
                  <th>SKU</th>
                  <th>Category</th>
                  <th style={{ textAlign: 'center' }}>Current Stock</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {lowStock.map((p, i) => (
                  <tr key={p.id}>
                    <td style={{ color: 'var(--text-muted)', fontSize: 12 }}>{i + 1}</td>
                    <td style={{ fontWeight: 600 }}>{p.name}</td>
                    <td><span className="mono" style={{ fontSize: 11, color: 'var(--text-secondary)' }}>{p.sku}</span></td>
                    <td style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{p.category?.name ?? '—'}</td>
                    <td style={{ textAlign: 'center' }}>
                      <span className="mono" style={{
                        fontSize: 14, fontWeight: 700,
                        color: p.stock_qty <= 0 ? 'var(--danger)' : 'var(--amber, #f59e0b)',
                      }}>
                        {p.stock_qty}
                      </span>
                    </td>
                    <td>
                      {p.stock_qty <= 0
                        ? <span className="badge-low-stock" style={{ background: 'rgba(220,53,69,0.15)', color: 'var(--danger)' }}>Out of Stock</span>
                        : <span className="badge-low-stock">Low Stock</span>
                      }
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  )
}
