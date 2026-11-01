// Ana sayfa: backend ve veritabanı bağlantı durumunu gösterir (Hafta 3 iskelet testi)
document.addEventListener('DOMContentLoaded', async () => {
  const apiEl = document.getElementById('api-status');
  const dbEl  = document.getElementById('db-status');
  const catEl = document.getElementById('category-list');

  try {
    const h = await apiGet('/health');
    setStatus(apiEl, true, 'API çalışıyor');
    setStatus(dbEl, h.database.ok, h.database.ok ? 'Veritabanı bağlı' : 'Veritabanı: ' + h.database.message);

    if (h.database.ok) {
      const c = await apiGet('/categories');
      catEl.innerHTML = c.data.map(k => `<li class="chip">${k.name}</li>`).join('');
    }
  } catch (e) {
    setStatus(apiEl, false, 'API\'ye ulaşılamadı (XAMPP Apache açık mı?)');
    setStatus(dbEl, false, 'Kontrol edilemedi');
  }
});

function setStatus(el, ok, text) {
  el.textContent = text;
  el.className = 'status ' + (ok ? 'ok' : 'fail');
}
