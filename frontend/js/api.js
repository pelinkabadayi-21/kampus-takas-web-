// Backend ile konuşan tek yer. Sayfalar doğrudan fetch yazmaz, bu fonksiyonları kullanır.
async function api(path, options = {}) {
  const res = await fetch(API_BASE + path, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const data = await res.json();
  if (!res.ok) {
    const err = new Error(data.error || 'İstek başarısız');
    err.errors = data.errors || {};
    throw err;
  }
  return data;
}

const apiGet = (path, params) => {
  const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)) : '';
  return api(path + qs);
};
const apiPost   = (path, body) => api(path, { method: 'POST', body });
const apiPut    = (path, body) => api(path, { method: 'PUT', body });
const apiDelete = (path)       => api(path, { method: 'DELETE' });

// Ortak yardımcılar
const TYPE_LABEL = { satis: 'Satılık', takas: 'Takas', bagis: 'Bağış' };
const COND_LABEL = { yeni: 'Yeni', cok_iyi: 'Çok iyi', iyi: 'İyi', orta: 'Orta', yipranmis: 'Yıpranmış' };

function formatPrice(item) {
  if (item.listing_type === 'bagis') return 'Ücretsiz';
  if (item.price == null) return 'Takaslık';
  return Number(item.price).toLocaleString('tr-TR') + ' ₺';
}

// Kullanıcıdan gelen metni HTML'e güvenle yazmak için (XSS koruması)
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
