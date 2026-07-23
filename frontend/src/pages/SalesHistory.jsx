import { useState, useEffect } from 'react'
import api from '../api/api'
import { useToast } from '../contexts/ToastContext'
import { useStore } from '../contexts/StoreContext'

function formatPaymentMethod(method) {
  if (method === 'khqr') return 'KHQR'
  return method?.charAt(0).toUpperCase() + method?.slice(1)
}

function ReceiptModal({ sale, onClose }) {
  const { storeName: shStoreName } = useStore()
  const rateUsed = sale.exchange_rate || 4100
  const totalKhr = sale.total_khr || Math.round(sale.total * rateUsed)
  const tenderedKhr = sale.tendered_khr || Math.round((sale.tendered_amount || 0) * rateUsed)
  const changeKhr = sale.change_khr || Math.round((sale.change_amount || 0) * rateUsed)

  return (
    <div className="receipt-overlay" onClick={onClose}>
      <div className="receipt-card" onClick={e => e.stopPropagation()}>
        <div className="receipt-header">
          <h4>{shStoreName}</h4>
          <small>Register #1 &middot; {sale.user?.name}</small><br />
          <small>{new Date(sale.created_at).toLocaleString()}</small><br />
          <small><strong>{sale.invoice_number}</strong></small>
        </div>

        <hr className="receipt-divider" />

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 50px 80px', gap: 2, marginBottom: 6, fontSize: 11, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px', fontFamily: 'var(--font-mono)' }}>
          <span style={{ textAlign: 'left' }}>Item</span>
          <span style={{ textAlign: 'center' }}>Qty</span>
          <span style={{ textAlign: 'right' }}>Price</span>
        </div>
        <div style={{ borderTop: '1px dashed var(--border-color)', marginBottom: 8 }} />

        <div className="receipt-items">
          {sale.items?.map((item, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 50px 80px', gap: 2, alignItems: 'center', padding: '3px 0', fontFamily: 'var(--font-mono)', fontSize: 13 }}>
              <span style={{ textAlign: 'left', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.product_name}</span>
              <span style={{ textAlign: 'center' }}>{item.quantity}</span>
              <span style={{ textAlign: 'right' }}>${parseFloat(item.line_total).toFixed(2)}</span>
            </div>
          ))}
        </div>

        <hr className="receipt-divider" />

        <div className="receipt-totals" style={{ fontFamily: 'var(--font-mono)' }}>
          <div className="receipt-total-row"><span>Subtotal</span><span>${parseFloat(sale.subtotal).toFixed(2)}</span></div>
          <div className="receipt-total-row"><span>Tax ({parseFloat(sale.tax_rate).toFixed(0)}%)</span><span>${parseFloat(sale.tax_amount).toFixed(2)}</span></div>
          <div className="receipt-total-row grand"><span>Total</span><span>${parseFloat(sale.total).toFixed(2)} / {totalKhr.toLocaleString()}៛</span></div>
          <div className="receipt-total-row"><span>Payment</span><span>{formatPaymentMethod(sale.payment_method)}</span></div>
          {sale.payment_method === 'cash' && (
            <>
              <div className="receipt-total-row"><span>Tendered</span><span>${parseFloat(sale.tendered_amount).toFixed(2)} / {tenderedKhr.toLocaleString()}៛</span></div>
              <div className="receipt-total-row"><span>Change</span><span>${parseFloat(sale.change_amount).toFixed(2)} / {changeKhr.toLocaleString()}៛</span></div>
            </>
          )}
          <div className="receipt-total-row"><span>Rate</span><span>$1.00 = {rateUsed.toLocaleString()}៛</span></div>
        </div>

        <hr className="receipt-divider" />
        <div className="receipt-footer">Thank you for shopping with us!</div>
        <div className="receipt-actions">
          <button className="btn-close-receipt" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  )
}

export default function SalesHistory() {
  const [sales, setSales] = useState([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [userId, setUserId] = useState('')
  const [users, setUsers] = useState([])
  const [viewSale, setViewSale] = useState(null)
  const toast = useToast()

  const load = (p = 1) => {
    let params = `?page=${p}`
    if (dateFrom) params += `&date_from=${dateFrom}`
    if (dateTo) params += `&date_to=${dateTo}`
    if (userId) params += `&user_id=${userId}`

    setLoading(true)
    api.get(`/sales${params}`).then(res => {
      setSales(res.data.data)
      setPage(res.data.current_page)
      setLastPage(res.data.last_page)
    }).catch(() => toast.error('Failed to load sales'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    api.get('/users').then(res => setUsers(res.data)).catch(() => {})
  }, [])

  useEffect(() => { load() }, [dateFrom, dateTo, userId])

  const viewSaleDetail = async (id) => {
    try {
      const res = await api.get(`/sales/${id}`)
      setViewSale(res.data)
    } catch { toast.error('Failed to load sale details') }
  }

  return (
    <div>
      <div className="page-header">
        <h4>Sales History</h4>
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
        <div>
          <label className="form-label-dark" style={{ marginBottom: 2 }}>Cashier</label>
          <select className="form-control-dark" value={userId} onChange={e => setUserId(e.target.value)} style={{ width: 160 }}>
            <option value="">All Cashiers</option>
            {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        </div>
      </div>

      <div className="table-wrapper">
        <table className="table-dark-custom">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Date</th>
              <th>Cashier</th>
              <th>Payment</th>
              <th>Total</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan="6" className="text-center py-4"><i className="fas fa-spinner fa-spin" /></td></tr>
            ) : sales.length === 0 ? (
              <tr><td colSpan="6" className="text-center py-4" style={{ color: 'var(--text-muted)' }}>No sales found</td></tr>
            ) : (
              sales.map(s => (
                <tr key={s.id}>
                  <td><span className="mono" style={{ fontSize: 12 }}>{s.invoice_number}</span></td>
                  <td style={{ fontSize: 13 }}>{new Date(s.created_at).toLocaleString()}</td>
                  <td>{s.user?.name}</td>
                  <td>{formatPaymentMethod(s.payment_method)}</td>
                  <td><span className="mono" style={{ fontWeight: 600 }}>${parseFloat(s.total).toFixed(2)}</span></td>
                  <td>
                    <button className="btn-outline-custom" style={{ padding: '4px 10px', fontSize: 12 }} onClick={() => viewSaleDetail(s.id)}>
                      <i className="fas fa-eye" /> View
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {lastPage > 1 && (
        <div className="pagination-dark">
          <button disabled={page <= 1} onClick={() => load(page - 1)}>Prev</button>
          <span style={{ padding: '6px 12px', fontSize: 13, color: 'var(--text-secondary)' }}>Page {page} of {lastPage}</span>
          <button disabled={page >= lastPage} onClick={() => load(page + 1)}>Next</button>
        </div>
      )}

      {viewSale && <ReceiptModal sale={viewSale} onClose={() => setViewSale(null)} />}
    </div>
  )
}
