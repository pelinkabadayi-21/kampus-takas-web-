// Ana sayfa: sistem durumu, kategoriler ve son ilanlar
document.addEventListener('DOMContentLoaded', async () => {
  const apiEl = document.getElementById('api-status');
  const dbEl  = document.getElementById('db-status');
  const catEl = document.getElementById('category-list');
  const latestEl = document.getElementById('latest');

  try {
    const h = await apiGet('/health');
    setStatus(apiEl, true, 'API çalışıyor');
    setStatus(dbEl, h.database.ok, h.database.ok ? 'Veritabanı bağlı' : 'Veritabanı: ' + h.database.message);
    if (!h.database.ok) { latestEl.innerHTML = '<p class="muted">Veritabanı bağlı değil.</p>'; return; }

    const [c, l] = await Promise.all([apiGet('/categories'), apiGet('/listings', { limit: 4 })]);
    catEl.innerHTML = c.data.map(k =>
      `<li><a class="chip" href="pages/ilanlar.html?category_id=${k.id}">${esc(k.name)} · ${k.listing_count}</a></li>`).join('');
    latestEl.innerHTML = l.items.map(i => listingCard(i)).join('') || '<p class="muted">Henüz ilan yok. migrate.php --seed çalıştırın.</p>';
  } catch (e) {
    setStatus(apiEl, false, 'API\'ye ulaşılamadı (XAMPP Apache açık mı?)');
    setStatus(dbEl, false, 'Kontrol edilemedi');
    latestEl.innerHTML = '';
  }
});

function setStatus(el, ok, text) {
  el.textContent = text;
  el.className = 'status ' + (ok ? 'ok' : 'fail');
}
