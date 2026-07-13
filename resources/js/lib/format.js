export function formatRupiah(n) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

export function formatCurrency(n, currency = 'IDR') {
  if (!currency || currency === 'IDR') return formatRupiah(n)
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency }).format(Number(n || 0))
}

export function formatShort(n) {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000) return (n / 1_000).toFixed(0) + 'rb'
  return String(n)
}
