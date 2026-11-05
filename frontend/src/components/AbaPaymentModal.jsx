import { useState, useEffect, useRef, useCallback } from 'react'
import api from '../api/api'

const PAYWAY_PLUGIN_URL = 'https://checkout.payway.com.kh/plugins/checkout2-0.js'
const POLL_INTERVAL_MS  = 3000
const POLL_TIMEOUT_MS   = 300000  // 5 minutes

const ERROR_MESSAGES = {
  1:  'Payment failed: wrong hash (server configuration error).',
  4:  'This transaction ID was already used. Please try again.',
  6:  'Domain not whitelisted by PayWay. Contact your administrator.',
  12: 'Currency USD is not enabled on this merchant profile.',
  21: 'PayWay API key has expired. Please renew it.',
  23: 'Selected payment option is not enabled on this profile.',
  81: 'return_url domain is not whitelisted by PayWay.',
}

/**
 * Load the ABA PayWay plugin script once, return a stable promise.
 */
let pluginLoadPromise = null
function loadPaywayPlugin() {
  if (pluginLoadPromise) return pluginLoadPromise
  pluginLoadPromise = new Promise((resolve) => {
    if (window.AbaPayway) { resolve(true); return }
    const script = document.createElement('script')
    script.src   = PAYWAY_PLUGIN_URL
    script.onload  = () => resolve(true)
    script.onerror = () => resolve(false)  // graceful fallback
    document.head.appendChild(script)
  })
  return pluginLoadPromise
}

/**
 * AbaPaymentModal
 *
 * Props:
 *   orderId    — integer sale ID (amount comes from server, never from client)
 *   amount     — display only (USD)
 *   onSuccess  — callback(tranId) when payment confirmed PAID
 *   onCancel   — callback() when user cancels or times out
 */
export default function AbaPaymentModal({ orderId, amount, onSuccess, onCancel }) {
  const [phase, setPhase]     = useState('idle')   // idle | loading | launched | polling | done | error
  const [message, setMessage] = useState('')
  const [tranId, setTranId]   = useState(null)

  const formRef      = useRef(null)
  const pollTimer    = useRef(null)
  const pollStart    = useRef(null)
  const cleanupDone  = useRef(false)

  // Inject hidden inputs into the PayWay form.
  const injectFields = useCallback((fields) => {
    const form = formRef.current
    if (!form) return
    form.replaceChildren()
    Object.entries(fields).forEach(([name, value]) => {
      const input   = document.createElement('input')
      input.type    = 'hidden'
      input.name    = name
      input.value   = String(value)
      form.appendChild(input)
    })
  }, [])

  // Poll check-transaction every POLL_INTERVAL_MS.
  const startPolling = useCallback((tid) => {
    setPhase('polling')
    pollStart.current = Date.now()

    const poll = async () => {
      if (cleanupDone.current) return

      if (Date.now() - pollStart.current > POLL_TIMEOUT_MS) {
        setPhase('error')
        setMessage('Payment timed out. Please check with your cashier.')
        return
      }

      try {
        const res  = await api.post('/aba/check-transaction', { tran_id: tid })
        const { status } = res.data

        if (status === 'PAID') {
          setPhase('done')
          setMessage('Payment confirmed!')
          onSuccess(tid)
          return
        }
        if (status === 'FAILED') {
          setPhase('error')
          setMessage('Payment was declined. Please try another method.')
          return
        }
      } catch {
        // Network blip — keep polling
      }

      pollTimer.current = setTimeout(poll, POLL_INTERVAL_MS)
    }

    poll()
  }, [onSuccess])

  // Main pay flow.
  const handlePay = useCallback(async () => {
    setPhase('loading')
    setMessage('')
    cleanupDone.current = false

    try {
      // 1. Get signed fields from server (amount from DB, never from client).
      const res    = await api.post('/aba/create-payment', { order_id: orderId })
      const fields = res.data.fields

      injectFields(fields)
      setTranId(fields.tran_id)

      // 2. Load PayWay plugin (non-blocking — falls back to form.submit()).
      setPhase('launched')
      const pluginLoaded = await loadPaywayPlugin()

      if (pluginLoaded && window.AbaPayway) {
        window.AbaPayway.checkout()
      } else {
        // Plugin unavailable — open hosted checkout.
        formRef.current?.submit()
      }

      // 3. Begin polling for confirmation.
      startPolling(fields.tran_id)

    } catch (err) {
      const errData = err.response?.data
      const code    = errData?.raw?.status?.code
      const userMsg = (code && ERROR_MESSAGES[code])
        || errData?.message
        || 'Failed to start payment. Please try again.'

      setPhase('error')
      setMessage(userMsg)
    }
  }, [orderId, injectFields, startPolling])

  // Cleanup on unmount or cancel.
  useEffect(() => {
    return () => {
      cleanupDone.current = true
      if (pollTimer.current) clearTimeout(pollTimer.current)
    }
  }, [])

  return (
    <div className="receipt-overlay" onClick={phase === 'idle' || phase === 'error' ? onCancel : undefined}>
      <div className="receipt-card" style={{ maxWidth: 420 }} onClick={e => e.stopPropagation()}>

        {/* ABA PayWay form — hidden inputs injected dynamically */}
        <form
          ref={formRef}
          method="POST"
          target="aba_webservice"
          id="aba_merchant_request"
          action={import.meta.env.VITE_PAYWAY_PURCHASE_URL || 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase'}
          style={{ display: 'none' }}
        />

        <div className="receipt-header" style={{ textAlign: 'center', marginBottom: 16 }}>
          <h4 style={{ margin: 0 }}>Pay with ABA PayWay</h4>
          <small style={{ color: 'var(--text-secondary)' }}>
            Total: <strong>${Number(amount || 0).toFixed(2)}</strong>
          </small>
        </div>

        <hr className="receipt-divider" />

        <div style={{ textAlign: 'center', padding: '12px 0' }}>
          {phase === 'idle' && (
            <>
              <p style={{ color: 'var(--text-secondary)', fontSize: 13, marginBottom: 16 }}>
                You will be redirected to ABA PayWay to complete the payment.
              </p>
              <button
                className="charge-btn"
                style={{ maxWidth: 280, margin: '0 auto', display: 'block' }}
                onClick={handlePay}
              >
                <i className="fas fa-credit-card" /> Pay ${Number(amount || 0).toFixed(2)} via ABA
              </button>
            </>
          )}

          {phase === 'loading' && (
            <div style={{ padding: 24 }}>
              <i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--primary)' }} />
              <p style={{ marginTop: 12, color: 'var(--text-secondary)' }}>Preparing payment…</p>
            </div>
          )}

          {phase === 'launched' && (
            <div style={{ padding: 24 }}>
              <i className="fas fa-external-link-alt fa-2x" style={{ color: 'var(--primary)' }} />
              <p style={{ marginTop: 12 }}>PayWay checkout launched.</p>
              {tranId && <p style={{ fontSize: 12, color: 'var(--text-secondary)' }}>Ref: {tranId}</p>}
            </div>
          )}

          {phase === 'polling' && (
            <div style={{ padding: 24 }}>
              <i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--amber)' }} />
              <p style={{ marginTop: 12 }}>Waiting for payment confirmation…</p>
              {tranId && <p style={{ fontSize: 12, color: 'var(--text-secondary)' }}>Ref: {tranId}</p>}
            </div>
          )}

          {phase === 'done' && (
            <div style={{ padding: 24 }}>
              <i className="fas fa-check-circle fa-2x" style={{ color: 'var(--success)' }} />
              <p style={{ marginTop: 12, color: 'var(--success)', fontWeight: 600 }}>{message}</p>
            </div>
          )}

          {phase === 'error' && (
            <div style={{ padding: 24 }}>
              <i className="fas fa-times-circle fa-2x" style={{ color: 'var(--danger)' }} />
              <p style={{ marginTop: 12, color: 'var(--danger)' }}>{message}</p>
              <button
                className="charge-btn"
                style={{ maxWidth: 240, margin: '12px auto 0', display: 'block', background: 'var(--danger)' }}
                onClick={handlePay}
              >
                <i className="fas fa-redo" /> Try Again
              </button>
            </div>
          )}
        </div>

        <hr className="receipt-divider" />

        <div className="receipt-actions">
          {(phase === 'idle' || phase === 'error') && (
            <button className="btn-close-receipt" onClick={onCancel}>Cancel</button>
          )}
          {phase === 'polling' && (
            <button
              className="btn-close-receipt"
              onClick={() => {
                cleanupDone.current = true
                if (pollTimer.current) clearTimeout(pollTimer.current)
                onCancel()
              }}
            >
              Cancel
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
