// Backend ile konuşan tek yer. Sayfalar doğrudan fetch yazmaz, bu fonksiyonları kullanır.
async function api(path, options = {}) {
  const res = await fetch(API_BASE + path, {
    credentials: 'include',            // oturum çerezini (HttpOnly) isteğe ekle
    headers: { 'Content-Type': 'application/json' },
    ...options,
    // İsteği sunucuya gönder; gövde varsa JSON'a çevir
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  // Cevabı JSON olarak oku
  const data = await res.json();
  if (!res.ok) {
    // Sunucu hata döndürdüyse (400, 401, 403...) hata mesajını ve alan hatalarını taşıyan bir hata fırlat
    const err = new Error(data.error || 'İstek başarısız');
    err.errors = data.errors || {};
    err.status = res.status;
    throw err;
  }
  return data;
}

// GET isteği: parametreleri adrese ekler (?q=...&category_id=...).
const apiGet = (path, params) => {
  // Boş olmayan parametreleri ?q=...&sort=... şeklinde adrese ekle
  const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)) : '';
  return api(path + qs);
};
// POST isteği: yeni kayıt gönderir.
const apiPost   = (path, body) => api(path, { method: 'POST', body });
// PUT isteği: güncelleme gönderir.
const apiPut    = (path, body) => api(path, { method: 'PUT', body });
// Silme (DELETE) isteği.
const apiDelete = (path)       => api(path, { method: 'DELETE' });

/** Dosya yükleme (multipart/form-data). Content-Type'ı tarayıcı kendisi ayarlar. */
async function apiUpload(path, formData) {
  // Fotoğraf FormData olarak gönderilir (JSON değil)
  const res = await fetch(API_BASE + path, { method: 'POST', credentials: 'include', body: formData });
  let data = {};
  // Dosya çok büyükse sunucu JSON döndüremeyebilir
  try { data = await res.json(); } catch (e) { data = { error: 'Sunucu yanıtı okunamadı (dosya çok büyük olabilir)' }; }
  if (!res.ok) { const err = new Error(data.error || 'Yükleme başarısız'); err.status = res.status; throw err; }
  return data;
}

// Yüklenen fotoğrafların adresi: API adresinden "/api" kısmı atılır (…/backend/public/uploads/…)
const UPLOAD_BASE = API_BASE.replace(/\/api\/?$/, '') + '/';
const imgUrl = path => path ? UPLOAD_BASE + path : '';

// Ortak yardımcılar
const TYPE_LABEL = { satis: 'Satılık', takas: 'Takas', bagis: 'Bağış' };
const COND_LABEL = { yeni: 'Yeni', cok_iyi: 'Çok iyi', iyi: 'İyi', orta: 'Orta', yipranmis: 'Yıpranmış' };
const STATUS_LABEL = { aktif: 'Yayında', rezerve: 'Rezerve', tamamlandi: 'Tamamlandı', kaldirildi: 'Yayından kaldırıldı' };

/** İlan kartı (liste, ana sayfa, hesabım ortak kullanır) */
function listingCard(i, { showStatus = false } = {}) {
  return `
    <a class="listing-card" href="${PAGES_PREFIX()}ilan.html?id=${i.id}">
      <div class="cover">${i.cover_image ? `<img src="${esc(imgUrl(i.cover_image))}" alt="" loading="lazy">` : '📦'}
        ${i.image_count > 1 ? `<span class="img-count">📷 ${i.image_count}</span>` : ''}</div>
      <div class="body">
        <span class="badge badge-${i.listing_type}">${TYPE_LABEL[i.listing_type]}</span>
        ${showStatus && i.status !== 'aktif' ? `<span class="badge badge-status-${i.status}">${STATUS_LABEL[i.status]}</span>` : ''}
        <h3>${esc(i.title)}</h3>
        <div class="price">${formatPrice(i)}</div>
        ${i.category_name ? `<small class="muted">${esc(i.category_name)}${i.item_condition ? ' · ' + COND_LABEL[i.item_condition] : ''}</small>` : ''}
      </div>
    </a>`;
}
// Sayfa ana dizindeyse "pages/" ekler, pages içindeyse eklemez (bağlantılar doğru çalışsın diye).
const PAGES_PREFIX = () => location.pathname.includes('/pages/') ? '' : 'pages/';

// Fiyatı gösterir: bağışsa "Ücretsiz", fiyatsız takassa "Takaslık", değilse "120 ₺".
function formatPrice(item) {
  // Bağışsa "Ücretsiz", fiyat yoksa "Takaslık", varsa TL olarak göster
  if (item.listing_type === 'bagis') return 'Ücretsiz';
  if (item.price == null) return 'Takaslık';
  return Number(item.price).toLocaleString('tr-TR') + ' ₺';
}

/** "5 dk önce", "dün 14:20" gibi kısa zaman */
function timeAgo(str) {
  const d = new Date(String(str).replace(' ', 'T'));
  // Kaç saniye önce olduğunu hesapla, ona göre "dk önce", "dün" gibi yaz
  const s = (Date.now() - d) / 1000;
  if (s < 60) return 'şimdi';
  if (s < 3600) return Math.floor(s / 60) + ' dk önce';
  if (s < 86400 && d.getDate() === new Date().getDate()) return d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
  if (s < 172800) return 'dün ' + d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
  return d.toLocaleDateString('tr-TR');
}

const money = n => Number(n).toLocaleString('tr-TR') + ' ₺';

// Kullanıcıdan gelen metni HTML'e güvenle yazmak için (XSS koruması)
function esc(s) {
  // XSS koruması: HTML özel karakterlerini zararsız hale getir
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
