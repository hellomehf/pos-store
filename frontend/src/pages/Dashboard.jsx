import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Line } from 'react-chartjs-2'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler } from 'chart.js'
import api from '../api/api'
import { useToast } from '../contexts/ToastContext'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler)

export default function Dashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const toast = useToast()
  const navigate = useNavigate()

  useEffect(() => {
    api.get('/dashboard')
      .then(res => setData(res.data))
      .catch(() => toast.error('Failed to load dashboard'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <div className="d-flex justify-content-center py-5"><i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} /></div>
  if (!data) return null

  // Safely cast numerical values
  const todaySalesFormatted = Number(data?.today_sales || 0).toFixed(2)

  const statCards = [
    { label: "Today's Sales", value: `$${todaySalesFormatted}`, icon: 'fa-dollar-sign', color: 'var(--primary)' },
    { label: 'Total Products', value: data?.total_products ?? 0, icon: 'fa-boxes-stacked', color: 'var(--primary)' },
    { label: 'Low Stock Items', value: data?.low_stock_products ?? 0, icon: 'fa-exclamation-triangle', type: 'warning', color: 'var(--amber)' },
    { label: "Today's Transactions", value: data?.today_transactions ?? 0, icon: 'fa-receipt', color: 'var(--primary)' },
  ]

  const salesChartList = data?.sales_chart || []
  const salesChartData = {
    labels: salesChartList.map(d => d?.date || ''),
    datasets: [{
      label: 'Revenue',
      data: salesChartList.map(d => Number(d?.total || 0)),
      borderColor: '#3271a8',
      backgroundColor: 'rgba(50, 113, 168, 0.1)',
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      pointBackgroundColor: '#3271a8',
    }]
  }

  const salesChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(46,55,66,0.5)' }, ticks: { color: '#8b95a5', font: { size: 11 } } },
      y: { grid: { color: 'rgba(46,55,66,0.5)' }, ticks: { color: '#8b95a5', font: { family: 'IBM Plex Mono', size: 11 } } },
    },
  }

  const recentTxList = data?.recent_transactions || []

  return (
    <div>
      <div className="row g-3 mb-4">
        {statCards.map((card, i) => (
          <div className="col-sm-6 col-xl-3" key={i}>
            <div className={`stat-card ${card.type || ''}`}>
              <div className="stat-card-icon" style={{ background: `${card.color}22`, color: card.color }}>
                <i className={`fas ${card.icon}`} />
              </div>
              <div>
                <div className="stat-card-value">{card.value}</div>
                <div className="stat-card-label">{card.label}</div>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="row g-3">
        <div className="col-lg-8">
          <div className="chart-container">
            <h6 style={{ marginBottom: 16 }}>Sales Overview (Last 30 Days)</h6>
            <div style={{ height: 280 }}>
              {salesChartList.length > 0 ? (
                <Line data={salesChartData} options={salesChartOptions} />
              ) : (
                <div className="empty-state"><i className="fas fa-chart-line" /><p>No sales data yet</p></div>
              )}
            </div>
          </div>
        </div>
        <div className="col-lg-4">
          <div className="card-dark">
            <h6 style={{ marginBottom: 16 }}>Recent Transactions</h6>
            {recentTxList.length > 0 ? (
              <div style={{ maxHeight: 300, overflowY: 'auto' }}>
                {recentTxList.map(tx => (
                  <div key={tx.id} style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '8px 0',
                    borderBottom: '1px solid var(--border-color)',
                    cursor: 'pointer',
                  }} onClick={() => navigate('/sales')}>
                    <div>
                      <div style={{ fontSize: 13, fontWeight: 500 }}>{tx.invoice_number}</div>
                      <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>{tx.cashier} &middot; {tx.payment_method}</div>
                    </div>
                    <span className="mono" style={{ fontWeight: 600, color: 'var(--success)' }}>${Number(tx?.total || 0).toFixed(2)}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="empty-state"><i className="fas fa-receipt" /><p>No transactions today</p></div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
