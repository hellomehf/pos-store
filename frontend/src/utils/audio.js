let audioContext = null

export function playBeep(frequency = 1200, duration = 0.12, volume = 0.25) {
  try {
    if (!audioContext) {
      audioContext = new (window.AudioContext || window.webkitAudioContext)()
    }
    
    // Resume audio context if suspended (required by some browsers)
    if (audioContext.state === 'suspended') {
      audioContext.resume()
    }
    
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()
    
    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)
    
    oscillator.type = 'sine'
    oscillator.frequency.value = frequency
    
    gainNode.gain.setValueAtTime(volume, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + duration)
    
    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + duration)
  } catch (error) {
    // Silently fail if Web Audio API is not available
    console.warn('Audio beep failed:', error)
  }
}

export function playSuccessBeep() {
  playBeep(1200, 0.12, 0.25)
}

export function playErrorBeep() {
  playBeep(400, 0.2, 0.25)
}

export default { playBeep, playSuccessBeep, playErrorBeep }
