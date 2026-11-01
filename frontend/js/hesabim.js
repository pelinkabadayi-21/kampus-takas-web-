// Korumalı sayfa: giriş yapmayan kullanıcı giriş sayfasına yönlendirilir.
document.addEventListener('DOMContentLoaded', async () => {
  let u;
  try { u = await requireLogin(); } catch (e) { return; }

  document.getElementById('profile').innerHTML = `
    <h2>${esc(u.full_name)} ${u.is_verified == 1 ? '<span class="badge badge-bagis">✅ Doğrulanmış öğrenci</span>' : '<span class="badge">Doğrulanmamış</span>'}</h2>
    <table class="facts">
      <tr><th>E-posta</th><td>${esc(u.email)}</td></tr>
      <tr><th>Bölüm</th><td>${esc(u.department || '—')}</td></tr>
      <tr><th>Rol</th><td>${u.role === 'yonetici' ? '🛡️ Yönetici' : '🎓 Öğrenci'}</td></tr>
    </table>
    <button class="btn btn-outline" id="logout-btn">Çıkış yap</button>`;
  document.getElementById('logout-btn').addEventListener('click', logout);

  const grid = document.getElementById('my-listings');
  try {
    const r = await apiGet('/listings', { user_id: u.id, limit: 50 });
    grid.innerHTML = r.items.map(i => `
      <a class="listing-card" href="ilan.html?id=${i.id}">
        <div class="cover">📦</div>
        <div class="body">
          <span class="badge badge-${i.listing_type}">${TYPE_LABEL[i.listing_type]}</span>
          <h3>${esc(i.title)}</h3>
          <div class="price">${formatPrice(i)}</div>
        </div>
      </a>`).join('') || '<p class="muted">Henüz aktif ilanınız yok. İlan verme özelliği bir sonraki haftada eklenecek.</p>';
  } catch (e) {
    grid.innerHTML = `<p class="error-box">${esc(e.message)}</p>`;
  }
});
