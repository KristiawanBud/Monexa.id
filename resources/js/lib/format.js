export function formatRupiah(n) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

export function formatShort(n) {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000) return (n / 1_000).toFixed(0) + 'rb'
  return String(n)
}
