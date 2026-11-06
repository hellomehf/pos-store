import { useEffect, useState } from 'react';
import './KhqrPaymentModal.css';

/**
 * Scan-to-pay modal for ABA KHQR.
 *
 * Presentation only: it does not call PayWay, poll, or change an order.
 * The existing KhqrModal container keeps doing that and passes state in.
 *
 * Props
 *  status       'waiting' | 'checking' | 'paid' | 'expired' | 'failed'
 *  amountUsd    number   amount due (from your DB, not the client)
 *  amountKhr    number   display-only riel amount (optional)
 *  tranRef      string   transaction reference, e.g. "K43877529771"
 *  qrImage      string   PayWay qrImage (data URI or URL)
 *  qrFallback   node     rendered if qrImage is missing (e.g. a <canvas> QR from qrString)
 *  merchantName string
 *  deeplink     string   "Open in ABA Mobile" URL (optional)
 *  expiresAt    number   epoch ms when the QR expires (optional)
 *  errorMessage string   shown when status is 'failed'
 *  onCheck      () => void
 *  onCancel     () => void
 *  onDone       () => void   called from the paid screen (optional)
 */
export default function KhqrPaymentModal({
  status = 'waiting',
  amountUsd,
  amountKhr,
  tranRef,
  qrImage,
  qrFallback = null,
  merchantName = 'POS Store',
  deeplink,
  expiresAt,
  errorMessage,
  onCheck,
  onCancel,
  onDone,
}) {
  const secondsLeft = useCountdown(expiresAt);
  const isPaid = status === 'paid';
  const isExpired =
    status === 'expired' ||
    (Boolean(expiresAt) && secondsLeft === 0 && !isPaid);
  const isFailed = status === 'failed';
  const isChecking = status === 'checking';
  const finished = isPaid || isExpired || isFailed;

  const pill = isPaid
    ? { cls: 'is-paid', text: 'Payment received' }
    : isExpired
      ? { cls: 'is-warn', text: 'QR expired' }
      : isFailed
        ? { cls: 'is-bad', text: 'Payment failed' }
        : { cls: 'is-waiting', text: isChecking ? 'Checking…' : 'Waiting for payment' };

  const overlay = isPaid
    ? {
        tone: 'is-paid',
        icon: '✓',
        title: 'Paid',
        note: `$${format(amountUsd)} received`,
      }
    : isExpired
      ? {
          tone: 'is-warn',
          icon: '!',
          title: 'QR expired',
          note: 'Cancel and create a new payment.',
        }
      : isFailed
        ? {
            tone: 'is-bad',
            icon: '×',
            title: 'Payment failed',
            note: errorMessage || 'The bank did not approve it.',
          }
        : null;

  return (
    <div className="khqr-backdrop" role="presentation">
      <section className="khqr" role="dialog" aria-modal="true" aria-labelledby="khqr-title">
        <header className="khqr-head">
          <div>
            <h2 id="khqr-title" className="khqr-title">Scan to pay</h2>
            <p className="khqr-merchant">{merchantName}</p>
          </div>
          <span className={`khqr-pill ${pill.cls}`} role="status" aria-live="polite">
            <span className="khqr-dot" aria-hidden="true" />
            {pill.text}
          </span>
        </header>

        {/* PayWay renders this image; keep its natural aspect ratio. */}
        <div className={`khqr-panel ${finished ? 'is-dim' : ''}`}>
          {qrImage ? (
            <img src={qrImage} alt={`ABA KHQR code for $${format(amountUsd)}`} />
          ) : (
            qrFallback || (
              <div className="khqr-missing">
                QR code unavailable. Press Cancel and try again.
              </div>
            )
          )}

          {overlay && (
            <div className={`khqr-overlay ${overlay.tone}`}>
              <span className="khqr-icon" aria-hidden="true">{overlay.icon}</span>
              <strong>{overlay.title}</strong>
              <span>{overlay.note}</span>
            </div>
          )}
        </div>

        <dl className="khqr-summary">
          <div className="khqr-summary-amount">
            <dt>Amount due</dt>
            <dd>
              <span className="khqr-amount-usd">${format(amountUsd)}</span>
              {amountKhr != null && (
                <span className="khqr-amount-khr">
                  ≈ {Math.round(amountKhr).toLocaleString()} ៛
                </span>
              )}
            </dd>
          </div>

          <div>
            <dt>Reference</dt>
            <dd>
              <span className="khqr-reference">{tranRef || '—'}</span>
              {tranRef && <CopyButton value={tranRef} />}
            </dd>
          </div>

          {expiresAt && !finished && (
            <div>
              <dt>QR expires in</dt>
              <dd className="khqr-expiry">{formatClock(secondsLeft)}</dd>
            </div>
          )}
        </dl>

        {deeplink && !finished && (
          <a className="khqr-link" href={deeplink}>
            Customer is on this device? Open in ABA Mobile
          </a>
        )}

        <div className="khqr-actions">
          {isPaid ? (
            <button
              type="button"
              className="khqr-btn is-primary"
              style={{ gridColumn: '1 / -1' }}
              onClick={onDone || onCancel}
            >
              Done
            </button>
          ) : (
            <>
              <button type="button" className="khqr-btn" onClick={onCancel}>
                Cancel
              </button>
              <button
                type="button"
                className="khqr-btn is-primary"
                onClick={onCheck}
                disabled={isChecking || isExpired || isFailed}
              >
                {isChecking ? 'Checking…' : 'Check payment'}
              </button>
            </>
          )}
        </div>
      </section>
    </div>
  );
}

function CopyButton({ value }) {
  const [copied, setCopied] = useState(false);

  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* clipboard blocked: ignore */
    }
  }

  return (
    <button type="button" className="khqr-copy" onClick={copy}>
      {copied ? 'Copied' : 'Copy'}
    </button>
  );
}

function useCountdown(expiresAt) {
  const calc = () =>
    expiresAt ? Math.max(0, Math.round((expiresAt - Date.now()) / 1000)) : null;
  const [left, setLeft] = useState(calc);

  useEffect(() => {
    if (!expiresAt) return undefined;
    setLeft(calc());
    const id = setInterval(() => setLeft(calc()), 1000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expiresAt]);

  return left;
}

function format(n) {
  return Number(n || 0).toFixed(2);
}

function formatClock(total) {
  const m = Math.floor(total / 60);
  const s = String(total % 60).padStart(2, '0');
  return `${m}:${s}`;
}
