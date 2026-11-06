import { useState, useEffect, useRef, useCallback } from 'react'
import api from '../api/api'
import KhqrPaymentModal from './ABA/KhqrPaymentModal'

const POLL_INTERVAL_MS = 3000

/**
 * Displays the real ABA PayWay KHQR returned by the backend and polls
 * /aba/qr-status/{sessionId} until ABA reports the transaction as PAID.
 * The visual presentation lives in KhqrPaymentModal.
 */
export default function KhqrModal({
  amount,
  currency = 'USD',
  exchangeRate,
  cartSnapshot,
  sessionId,
  tranId,
  qrImage,
  deeplink,
  expiresAt,
  storeName,
  onSuccess,
  onCancel,
}) {
  const [phase, setPhase] = useState('waiting') // waiting | success | error | expired
  const [checking, setChecking] = useState(false)
  const [error, setError] = useState('')

  const completedRef = useRef(false)
  const successTimerRef = useRef(null)
  const onSuccessRef = useRef(onSuccess)
  onSuccessRef.current = onSuccess

  const finishSuccess = useCallback(() => {
    if (completedRef.current) return
    completedRef.current = true
    setPhase('success')

    // Give the cashier a brief confirmation before creating the sale.
    successTimerRef.current = setTimeout(() => {
      successTimerRef.current = null
      onSuccessRef.current?.(cartSnapshot)
    }, 1000)
  }, [cartSnapshot])

  useEffect(() => () => {
    if (successTimerRef.current) clearTimeout(successTimerRef.current)
  }, [])

  const applyStatus = useCallback((status) => {
    if (status === 'PAID') {
      finishSuccess()
      return true
    }
    if (status === 'FAILED') {
      setPhase('error')
      setError('Payment was declined or cancelled.')
      return true
    }
    if (status === 'EXPIRED') {
      setPhase('expired')
      return true
    }
    return false
  }, [finishSuccess])

  // Poll PayWay through the backend while the modal is open.
  useEffect(() => {
    if (!sessionId) return

    let active = true
    let timer = null

    const poll = async () => {
      try {
        const res = await api.get(`/aba/qr-status/${sessionId}`)
        if (!active) return
        if (applyStatus(res.data?.status)) return
      } catch {
        // Network blip — keep polling.
      }
      if (active) timer = setTimeout(poll, POLL_INTERVAL_MS)
    }

    poll()

    return () => {
      active = false
      if (timer) clearTimeout(timer)
    }
  }, [sessionId, applyStatus])

  const checkNow = async () => {
    if (!sessionId || checking) return
    setChecking(true)
    try {
      const res = await api.get(`/aba/qr-status/${sessionId}`)
      applyStatus(res.data?.status)
    } catch {
      setError('Could not reach the payment gateway. Please try again.')
    } finally {
      setChecking(false)
    }
  }

  const handleCancel = () => {
    if (successTimerRef.current) {
      clearTimeout(successTimerRef.current)
      successTimerRef.current = null
    }
    completedRef.current = true
    onCancel?.()
  }

  const handleDone = () => {
    if (successTimerRef.current) {
      clearTimeout(successTimerRef.current)
      successTimerRef.current = null
    }
    onSuccessRef.current?.(cartSnapshot)
  }

  const status = phase === 'success'
    ? 'paid'
    : phase === 'error'
      ? 'failed'
      : phase === 'expired'
        ? 'expired'
        : checking
          ? 'checking'
          : 'waiting'

  const expiresAtMs = (() => {
    if (!expiresAt) return null
    if (typeof expiresAt === 'number') return expiresAt
    if (typeof expiresAt === 'string' && /^\d+$/.test(expiresAt)) return Number(expiresAt)
    const parsed = new Date(expiresAt).getTime()
    return Number.isFinite(parsed) ? parsed : null
  })()

  const numericAmount = Number(amount) || 0
  const rate = Number(exchangeRate) || 0
  const amountUsd = currency === 'KHR' && rate > 0 ? numericAmount / rate : numericAmount
  const amountKhr = currency === 'KHR'
    ? numericAmount
    : rate > 0
      ? numericAmount * rate
      : undefined

  return (
    <KhqrPaymentModal
      status={status}
      amountUsd={amountUsd}
      amountKhr={amountKhr}
      tranRef={tranId}
      qrImage={qrImage}
      merchantName={storeName || 'POS Store'}
      deeplink={deeplink}
      expiresAt={expiresAtMs}
      errorMessage={error}
      onCheck={checkNow}
      onCancel={handleCancel}
      onDone={handleDone}
    />
  )
}
