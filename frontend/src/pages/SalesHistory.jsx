import { useState, useEffect, useRef } from 'react'
import api from '../api/api'
import { useToast } from '../contexts/ToastContext'
import { useStore } from '../contexts/StoreContext'

function formatPaymentMethod(method) {
  if (!method) return ''
  if (method === 'khqr') return 'KHQR'
  return method.charAt(0).toUpperCase() + method.slice(1)
}

function ReceiptModal({ sale, onClose }) {
  const { storeName: shStoreName } = useStore()
  const receiptRef = useRef(null)

  const rateUsed = sale?.exchange_rate || 4100
  const totalVal = Number(sale?.total || 0)
  const totalKhr = sale?.total_khr || Math.round(totalVal * rateUsed)
  const tenderedKhr = sale?.tendered_khr || Math.round(Number(sale?.tendered_amount || 0) * rateUsed)
  const changeKhr = sale?.change_khr || Math.round(Number(sale?.change_amount || 0) * rateUsed)

  const handlePrint = () => {
    const content = receiptRef.current
    if (!content) return
    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = 'none'
    document.body.appendChild(iframe)
    const doc = iframe.contentWindow.document
    doc.open()
    doc.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>
      @page { margin: 8mm; size: 80mm auto; }
      body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; margin: 0; padding: 0; }
      .receipt-header { text-align: center; margin-bottom: 8px; }
      .receipt-header h4 { margin: 0; font-size: 16px; }
      .receipt-header p { margin: 2px 0; font-size: 11px; }
      hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
      .col-header { display: grid; grid-template-columns: 1fr 50px 80px; gap: 2px; margin-bottom: 4px; font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
      .col-header span:nth-child(2) { text-align: center; }
      .col-header span:nth-child(3) { text-align: right; }
      .item-row { display: grid; grid-template-columns: 1fr 50px 80px; gap: 2px; margin: 3px 0; font-family: 'Courier New', monospace; }
      .item-row span:nth-child(1) { text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .item-row span:nth-child(2) { text-align: center; }
      .item-row span:nth-child(3) { text-align: right; }
      .summary-row { display: flex; justify-content: space-between; margin: 2px 0; font-family: 'Courier New', monospace; }
      .summary-row.bold { font-weight: bold; font-size: 13px; border-top: 1px dashed #000; padding-top: 6px; margin-top: 6px; }
      .footer { text-align: center; margin-top: 8px; font-size: 11px; }
    </style></head><body>
      <div class="receipt-header">
        <h4>${shStoreName || ''}</h4>
        <p>Register #1 &middot; ${sale?.user?.name || 'Unknown Staff'}</p>
        <p>${sale?.created_at ? new Date(sale.created_at).toLocaleString() : ''}</p>
        <p><strong>${sale?.invoice_number || ''}</strong></p>
      </div>
      <hr />
      <div class="col-header"><span>ITEM</span><span>QTY</span><span>PRICE</span></div>
      <hr style="margin: 2px 0 6px" />
      ${(sale?.items || []).map(item => `<div class="item-row"><span>${item.product_name || 'Deleted Product'}</span><span>${item.quantity || 0}</span><span>$${Number(item.line_total || 0).toFixed(2)}</span></div>`).join('')}
      <hr />
      <div class="summary-row"><span>Subtotal</span><span>$${Number(sale?.subtotal || 0).toFixed(2)}</span></div>
      <div class="summary-row"><span>Tax (${Number(sale?.tax_rate || 0).toFixed(0)}%)</span><span>$${Number(sale?.tax_amount || 0).toFixed(2)}</span></div>
      <div class="summary-row bold"><span>TOTAL</span><span>$${totalVal.toFixed(2)} / ${totalKhr.toLocaleString()}៛</span></div>
      <div class="summary-row"><span>Payment</span><span>${formatPaymentMethod(sale?.payment_method)}</span></div>
      ${sale?.payment_method === 'cash' ? `<div class="summary-row"><span>Tendered</span><span>$${Number(sale?.tendered_amount || 0).toFixed(2)} / ${tenderedKhr.toLocaleString()}៛</span></div><div class="summary-row"><span>Change</span><span>$${Number(sale?.change_amount || 0).toFixed(2)} / ${changeKhr.toLocaleString()}៛</span></div>` : ''}
      <div class="summary-row"><span>Rate</span><span>$1.00 = ${rateUsed.toLocaleString()}៛</span></div>
      <hr />
      <div class="footer">Thank you for shopping with us!</div>
    </body></html>`)
    doc.close()
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
    setTimeout(() => document.body.removeChild(iframe), 1000)
  }

  return (
    <div className="receipt-overlay" onClick={onClose}>
      <div className="receipt-card" onClick={e => e.stopPropagation()}>
        <div ref={receiptRef}>
          <div className="receipt-header">
            <h4>{shStoreName}</h4>
            <small>Register #1 &middot; {sale?.user?.name || 'Unknown Staff'}</small><br />
            <small>{sale?.created_at ? new Date(sale.created_at).toLocaleString() : 'N/A'}</small><br />
            <small><strong>{sale?.invoice_number || ''}</strong></small>
          </div>

          <hr className="receipt-divider" />

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 50px 80px', gap: 2, marginBottom: 6, fontSize: 11, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px', fontFamily: 'var(--font-mono)' }}>
            <span style={{ textAlign: 'left' }}>Item</span>
            <span style={{ textAlign: 'center' }}>Qty</span>
            <span style={{ textAlign: 'right' }}>Price</span>
          </div>
          <div style={{ borderTop: '1px dashed var(--border-color)', marginBottom: 8 }} />

          <div className="receipt-items">
            {(sale?.items || []).map((item, i) => (
              <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 50px 80px', gap: 2, alignItems: 'center', padding: '3px 0', fontFamily: 'var(--font-mono)', fontSize: 13 }}>
                <span style={{ textAlign: 'left', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.product_name || 'Deleted Product'}</span>
                <span style={{ textAlign: 'center' }}>{item.quantity || 0}</span>
                <span style={{ textAlign: 'right' }}>${Number(item.line_total || 0).toFixed(2)}</span>
              </div>
            ))}
          </div>

          <hr className="receipt-divider" />

          <div className="receipt-totals" style={{ fontFamily: 'var(--font-mono)' }}>
            <div className="receipt-total-row"><span>Subtotal</span><span>${Number(sale?.subtotal || 0).toFixed(2)}</span></div>
            <div className="receipt-total-row"><span>Tax ({Number(sale?.tax_rate || 0).toFixed(0)}%)</span><span>${Number(sale?.tax_amount || 0).toFixed(2)}</span></div>
            <div className="receipt-total-row grand"><span>Total</span><span>${totalVal.toFixed(2)} / {totalKhr.toLocaleString()}៛</span></div>
            <div className="receipt-total-row"><span>Payment</span><span>{formatPaymentMethod(sale?.payment_method)}</span></div>
            {sale?.payment_method === 'cash' && (
              <>
                <div className="receipt-total-row"><span>Tendered</span><span>${Number(sale?.tendered_amount || 0).toFixed(2)} / {tenderedKhr.toLocaleString()}៛</span></div>
                <div className="receipt-total-row"><span>Change</span><span>${Number(sale?.change_amount || 0).toFixed(2)} / {changeKhr.toLocaleString()}៛</span></div>
              </>
            )}
            <div className="receipt-total-row"><span>Rate</span><span>$1.00 = {rateUsed.toLocaleString()}៛</span></div>
          </div>

          <hr className="receipt-divider" />
          <div className="receipt-footer">Thank you for shopping with us!</div>
        </div>

        <div className="receipt-actions">
          <button className="btn-close-receipt" onClick={onClose}>Close</button>
          <button className="btn-new-sale" onClick={handlePrint}><i className="fas fa-print" /> Print</button>
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
      setSales(res?.data?.data || [])
      setPage(res?.data?.current_page || 1)
      setLastPage(res?.data?.last_page || 1)
    }).catch(() => toast.error('Failed to load sales'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    api.get('/users').then(res => setUsers(res?.data || [])).catch(() => {})
  }, [])

  useEffect(() => { load() }, [dateFrom, dateTo, userId])

  const viewSaleDetail = async (id) => {
    try {
      const res = await api.get(`/sales/${id}`)
      setViewSale(res?.data || null)
    } catch { 
      toast.error('Failed to load sale details') 
    }
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
            {(users || []).map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
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
            ) : (sales || []).length === 0 ? (
              <tr><td colSpan="6" className="text-center py-4" style={{ color: 'var(--text-muted)' }}>No sales found</td></tr>
            ) : (
              (sales || []).map(s => (
                <tr key={s.id}>
                  <td><span className="mono" style={{ fontSize: 12 }}>{s.invoice_number || 'N/A'}</span></td>
                  <td style={{ fontSize: 13 }}>{s.created_at ? new Date(s.created_at).toLocaleString() : 'N/A'}</td>
                  <td>{s.user?.name || 'Unknown Staff'}</td>
                  <td>{formatPaymentMethod(s.payment_method)}</td>
                  <td><span className="mono" style={{ fontWeight: 600 }}>${Number(s.total || 0).toFixed(2)}</span></td>
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
