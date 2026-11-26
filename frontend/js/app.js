// Ana sayfa: sistem durumu, kategoriler ve son ilanlar
document.addEventListener('DOMContentLoaded', async () => {
  const apiEl = document.getElementById('api-status');
  const dbEl  = document.getElementById('db-status');
  const catEl = document.getElementById('category-list');
  const latestEl = document.getElementById('latest');

  try {
    // Backend ve veritabanı çalışıyor mu kontrol et
    const h = await apiGet('/health');
    setStatus(apiEl, true, 'API çalışıyor');
    setStatus(dbEl, h.database.ok, h.database.ok ? 'Veritabanı bağlı' : 'Veritabanı: ' + h.database.message);
    // Hafta 9: harici servisler. Anahtar girilmemişse hata değildir, o özellik kapalı çalışır (gri rozet).
    if (h.integrations) {
      const intEl = document.getElementById('int-status');
      const cloud = h.integrations.image_storage === 'cloudinary', mail = h.integrations.email !== 'kapalı';
      intEl.textContent = `Fotoğraflar: ${cloud ? 'Cloudinary ☁️' : 'sunucu diski'} · E-posta bildirimi: ${mail ? 'açık ✉️' : 'kapalı'}`;
      intEl.className = 'status' + (cloud && mail ? ' ok' : '');
      intEl.hidden = false;
    }
    if (!h.database.ok) { latestEl.innerHTML = '<p class="muted">Veritabanı bağlı değil.</p>'; return; }

    // Kategorileri ve son 4 ilanı aynı anda getir
    const [c, l] = await Promise.all([apiGet('/categories'), apiGet('/listings', { limit: 4 })]);
    // Kategorileri tıklanabilir etiket olarak göster
    catEl.innerHTML = c.data.map(k =>
      `<li><a class="chip" href="pages/ilanlar.html?category_id=${k.id}">${esc(k.name)} · ${k.listing_count}</a></li>`).join('');
    // Son ilanları kart olarak göster
    latestEl.innerHTML = l.items.map(i => listingCard(i)).join('') || '<p class="muted">Henüz ilan yok. migrate.php --seed çalıştırın.</p>';
  // Backend'e hiç ulaşılamadıysa
  } catch (e) {
    setStatus(apiEl, false, 'API\'ye ulaşılamadı (XAMPP Apache açık mı?)');
    setStatus(dbEl, false, 'Kontrol edilemedi');
    latestEl.innerHTML = '';
  }
});

// Durum rozetini yeşil (çalışıyor) veya kırmızı (hata) yapar.
function setStatus(el, ok, text) {
  el.textContent = text;
  el.className = 'status ' + (ok ? 'ok' : 'fail');
}
