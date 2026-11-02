import { useEffect, useRef, useCallback } from 'react'

/**
 * Hook for handling USB HID barcode scanner input
 * Scanners act as keyboards and send keys rapidly followed by Enter
 * @param {Object} options
 * @param {Array} options.products - List of products to search through
 * @param {Function} options.onProductFound - Callback when product is found (product) => void
 * @param {Function} options.onProductNotFound - Callback when product not found (code) => void
 * @param {number} options.threshold - Max ms between keystrokes to be considered scanner input (default: 50)
 * @param {number} options.flushTimeout - Timeout to clear buffer if Enter not received (default: 300)
 * @param {boolean} options.enabled - Whether scanner is enabled (default: true)
 */
export function useBarcodeScanner({
  products = [],
  onProductFound,
  onProductNotFound,
  threshold = 50,
  flushTimeout = 300,
  enabled = true,
} = {}) {
  const bufferRef = useRef('')
  const lastKeyTimeRef = useRef(0)
  const timerRef = useRef(null)
  const productsRef = useRef(products)

  // Keep products ref updated
  useEffect(() => {
    productsRef.current = products
  }, [products])

  const clearBuffer = useCallback(() => {
    bufferRef.current = ''
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
  }, [])

  const searchProduct = useCallback((code) => {
    const searchCode = code.trim().toLowerCase()
    if (!searchCode) return null

    return productsRef.current.find(
      (p) =>
        (p.barcode && p.barcode.toString().toLowerCase() === searchCode) ||
        (p.sku && p.sku.toString().toLowerCase() === searchCode)
    )
  }, [])

  useEffect(() => {
    if (!enabled) return

    const handleKeyDown = (e) => {
      // Skip if any input element is focused
      const activeElement = document.activeElement
      if (!activeElement) return

      const tagName = activeElement.tagName
      if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
        return
      }

      // Skip if contenteditable
      if (activeElement.isContentEditable) {
        return
      }

      const now = Date.now()
      const elapsed = now - lastKeyTimeRef.current
      lastKeyTimeRef.current = now

      // Clear any pending timer
      if (timerRef.current) {
        clearTimeout(timerRef.current)
        timerRef.current = null
      }

      // If too much time passed between keystrokes, start fresh buffer
      if (elapsed > threshold && bufferRef.current.length > 0) {
        bufferRef.current = ''
      }

      // Handle Enter key - process scanned code
      if (e.key === 'Enter') {
        const code = bufferRef.current
        clearBuffer()

        if (!code) return

        const product = searchProduct(code)

        if (product) {
          onProductFound?.(product, code)
        } else {
          onProductNotFound?.(code)
        }
        return
      }

      // Only accumulate printable characters
      if (e.key.length === 1) {
        // Prevent default for scanner input to avoid unwanted behavior
        bufferRef.current += e.key

        // Safety timeout to clear buffer if Enter never comes
        timerRef.current = setTimeout(() => {
          clearBuffer()
        }, flushTimeout)
      }
    }

    window.addEventListener('keydown', handleKeyDown)

    return () => {
      window.removeEventListener('keydown', handleKeyDown)
      clearBuffer()
    }
  }, [enabled, threshold, flushTimeout, searchProduct, onProductFound, onProductNotFound, clearBuffer])

  return { clearBuffer }
}

export default useBarcodeScanner
