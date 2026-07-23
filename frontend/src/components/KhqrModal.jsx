import { useState, useRef } from 'react'

export default function KhqrModal({ amount, currency, exchangeRate, cartSnapshot, onSuccess, onCancel }) {
  const [imgLoaded, setImgLoaded] = useState(false)
  const [imgFailed, setImgFailed] = useState(false)
  const [phase, setPhase] = useState('idle')
  const completedRef = useRef(false)
  const onSuccessRef = useRef(onSuccess)
  onSuccessRef.current = onSuccess

  const handleConfirm = async () => {
    if (phase !== 'idle') return
    completedRef.current = false
    setPhase('verifying')

    await new Promise(r => setTimeout(r, 1500))

    if (completedRef.current) return
    completedRef.current = true
    setPhase('success')

    setTimeout(() => {
      if (!completedRef.current) return
      onSuccessRef.current?.(cartSnapshot)
    }, 1000)
  }

  const handleCancel = () => {
    completedRef.current = true
    onCancel?.()
  }

  const formatAmount = (val) => {
    if (currency === 'KHR') return `${Math.round(val).toLocaleString()}៛`
    return `$${val.toFixed(2)}`
  }

  return (
    <div className="receipt-overlay" onClick={handleCancel}>
      <div
        className="card-dark"
        onClick={e => e.stopPropagation()}
        style={{
          textAlign: 'center',
          padding: 32,
          width: 400,
          maxWidth: '94%',
          borderRadius: 'var(--radius-lg)',
        }}
      >
        {phase !== 'success' && (
          <div style={{ marginBottom: 16 }}>
            <div style={{
              width: 48, height: 48, borderRadius: '50%',
              background: phase === 'verifying' ? 'rgba(232,163,61,0.15)' : 'rgba(50,113,168,0.15)',
              display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 12px',
            }}>
              <i
                className={`fas ${phase === 'verifying' ? 'fa-spinner fa-spin' : 'fa-qrcode'}`}
                style={{ fontSize: 22, color: phase === 'verifying' ? 'var(--amber)' : 'var(--primary)' }}
              />
            </div>
            <h5 style={{ margin: 0 }}>
              {phase === 'verifying' ? 'Verifying Payment...' : 'Scan to Pay'}
            </h5>
            <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '4px 0 0' }}>
              {phase === 'verifying' ? 'Please wait...' : 'Show this QR to the customer'}
            </p>
            {phase === 'idle' && (
              <p style={{ fontSize: 12, color: 'var(--text-muted)', margin: '4px 0 0' }}>
                Merchant: VONG DAVID
              </p>
            )}
          </div>
        )}

        {phase === 'success' && (
          <div style={{ marginBottom: 16 }}>
            <div style={{
              width: 64, height: 64, borderRadius: '50%',
              background: 'rgba(60, 184, 120, 0.15)',
              display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 12px',
            }}>
              <i className="fas fa-check" style={{ fontSize: 28, color: '#3bb878' }} />
            </div>
            <h5 style={{ margin: '0 0 4px', color: '#ffffff' }}>Payment Successful!</h5>
            <p style={{ fontSize: 13, color: 'rgba(255,255,255,0.7)', margin: 0 }}>
              Completing sale...
            </p>
          </div>
        )}

        {phase !== 'success' && (
          <div style={{
            width: 280, height: 280, margin: '0 auto',
            borderRadius: 'var(--radius-lg)',
            border: '1px solid var(--border-color)',
            background: '#fff',
            display: 'flex', justifyContent: 'center', alignItems: 'center',
            overflow: 'hidden',
          }}>
            {imgFailed ? (
              <div style={{ textAlign: 'center', color: 'var(--danger)', padding: 20 }}>
                <i className="fas fa-exclamation-circle" style={{ fontSize: 28, marginBottom: 8, display: 'block' }} />
                <p style={{ fontSize: 13, margin: 0 }}>Failed to load QR image.</p>
              </div>
            ) : phase === 'verifying' ? (
              <div style={{ textAlign: 'center', color: 'var(--amber)', padding: 20 }}>
                <i className="fas fa-spinner fa-spin" style={{ fontSize: 48, marginBottom: 12, display: 'block' }} />
                <p style={{ fontSize: 14, margin: 0, color: 'var(--text-primary)' }}>Verifying...</p>
              </div>
            ) : (
              <>
                {!imgLoaded && (
                  <i className="fas fa-spinner fa-spin" style={{ fontSize: 28, color: 'var(--text-muted)', position: 'absolute' }} />
                )}
                <img
                  src="/QCode.jpg"
                  alt="Static KHQR"
                  onLoad={() => setImgLoaded(true)}
                  onError={() => setImgFailed(true)}
                  style={{ width: '100%', height: '100%', objectFit: 'contain', display: imgLoaded ? 'block' : 'none' }}
                />
              </>
            )}
          </div>
        )}

        {phase !== 'success' && (
          <div style={{ marginTop: 12, marginBottom: 16 }}>
            <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>Amount Due</div>
            <div style={{ fontFamily: 'var(--font-mono)', fontSize: 24, fontWeight: 700, color: 'var(--secondary)' }}>
              {formatAmount(amount)}
            </div>
            {currency === 'KHR' && (
              <div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-muted)' }}>
                ≈ ${(amount / exchangeRate).toFixed(2)} USD
              </div>
            )}
            {currency === 'USD' && exchangeRate > 0 && (
              <div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-muted)' }}>
                ≈ {Math.round(amount * exchangeRate).toLocaleString()}៛
              </div>
            )}
          </div>
        )}

        {phase !== 'success' && (
          <div style={{ display: 'flex', gap: 10 }}>
            <button
              className="btn-outline-custom"
              onClick={handleCancel}
              disabled={phase === 'verifying'}
              style={{ flex: 1, justifyContent: 'center' }}
            >
              Cancel
            </button>
            <button
              className="btn-accent"
              onClick={handleConfirm}
              disabled={phase === 'verifying'}
              style={{ flex: 1, justifyContent: 'center' }}
            >
              {phase === 'verifying' ? (
                <><i className="fas fa-spinner fa-spin" /> Verifying Payment...</>
              ) : (
                <><i className="fas fa-hand-pointer" /> Payment Confirmed</>
              )}
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
