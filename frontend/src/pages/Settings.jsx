import { useState, useRef, useEffect } from 'react'
import api from '../api/api'
import { useStore } from '../contexts/StoreContext'

export default function Settings() {
  const { storeName, storeLogo, updateSettings } = useStore()
  const [storeNameInput, setStoreNameInput] = useState(storeName)
  const [logoFile, setLogoFile] = useState(null)
  const [logoPreview, setLogoPreview] = useState(storeLogo)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const fileInputRef = useRef(null)

  useEffect(() => {
    setStoreNameInput(storeName)
    setLogoPreview(storeLogo)
  }, [storeName, storeLogo])

  const handleLogoSelect = (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    if (file.size > 2048 * 1024) {
      window.__posToast?.error?.('Logo must be under 2048 KB.')
      return
    }

    const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml']
    if (!validTypes.includes(file.type)) {
      window.__posToast?.error?.('Only PNG, JPEG, and SVG files are allowed.')
      return
    }

    setLogoFile(file)
    setLogoPreview(URL.createObjectURL(file))
  }

  const handleRemoveLogo = () => {
    setLogoFile(null)
    setLogoPreview(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setSaved(false)

    try {
      const formData = new FormData()
      formData.append('store_name', storeNameInput)
      if (logoFile) {
        formData.append('store_logo', logoFile)
      }

      const res = await api.post('/settings/store', formData)
      updateSettings(res.data.store_name, res.data.store_logo)
      setSaved(true)
      setTimeout(() => setSaved(false), 3000)
    } catch (err) {
      // Error handled by interceptor
    } finally {
      setSaving(false)
    }
  }

  return (
    <div>
      <div className="page-header">
        <h4>Store Settings</h4>
      </div>

      <form onSubmit={handleSubmit} style={{ maxWidth: 560 }}>
        <div className="card-dark" style={{ marginBottom: 20 }}>
          <h6 style={{ marginBottom: 20, fontSize: 15 }}>
            <i className="fas fa-store" style={{ marginRight: 8, color: 'var(--primary)' }} />
            General
          </h6>

          <div style={{ marginBottom: 20 }}>
            <label className="form-label-dark">Store Name</label>
            <input
              type="text"
              className="form-control-dark"
              value={storeNameInput}
              onChange={(e) => setStoreNameInput(e.target.value)}
              placeholder="Enter store name"
              required
              maxLength={255}
            />
          </div>
        </div>

        <div className="card-dark" style={{ marginBottom: 20 }}>
          <h6 style={{ marginBottom: 20, fontSize: 15 }}>
            <i className="fas fa-image" style={{ marginRight: 8, color: 'var(--primary)' }} />
            Company Logo
          </h6>

          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 20, flexWrap: 'wrap' }}>
            <div
              style={{
                width: 100,
                height: 100,
                borderRadius: 'var(--radius-lg)',
                border: '1px solid var(--border-color)',
                background: 'var(--bg-body)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
                flexShrink: 0,
              }}
            >
              {logoPreview ? (
                <img
                  src={logoPreview}
                  alt="Store Logo"
                  style={{ width: '100%', height: '100%', objectFit: 'contain' }}
                />
              ) : (
                <i className="fas fa-store" style={{ fontSize: 28, color: 'var(--text-muted)', opacity: 0.4 }} />
              )}
            </div>

            <div style={{ flex: 1, minWidth: 200 }}>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                onChange={handleLogoSelect}
                style={{ display: 'none' }}
                id="store-logo-input"
              />

              <div style={{ display: 'flex', gap: 8, marginBottom: 10, flexWrap: 'wrap' }}>
                <button
                  type="button"
                  className="btn-outline-custom"
                  onClick={() => fileInputRef.current?.click()}
                >
                  <i className="fas fa-upload" />
                  Choose File
                </button>

                {logoPreview && (
                  <button
                    type="button"
                    className="btn-danger-custom"
                    onClick={handleRemoveLogo}
                  >
                    <i className="fas fa-trash" />
                    Remove
                  </button>
                )}
              </div>

              <p style={{ fontSize: 12, color: 'var(--text-muted)', margin: 0 }}>
                PNG, JPEG, or SVG. Max 2048 KB.
              </p>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <button
            type="submit"
            className="btn-accent"
            disabled={saving || !storeNameInput.trim()}
          >
            {saving ? (
              <>
                <i className="fas fa-spinner fa-spin" />
                Saving...
              </>
            ) : (
              <>
                <i className="fas fa-check" />
                Save Settings
              </>
            )}
          </button>

          {saved && (
            <span style={{ fontSize: 13, color: 'var(--success)' }}>
              <i className="fas fa-check-circle" style={{ marginRight: 4 }} />
              Saved successfully
            </span>
          )}
        </div>
      </form>
    </div>
  )
}
