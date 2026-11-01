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
  const info = document.getElementById('limit-info');
  try {
    const [r, o] = await Promise.all([apiGet('/me/listings'), apiGet('/listings/options')]);
    const active = r.items.filter(i => i.status === 'aktif').length;
    const limit = u.role === 'yonetici' ? null : (u.is_verified == 1 ? o.data.limits.verified : o.data.limits.unverified);
    info.innerHTML = limit === null ? '' :
      `${active} / ${limit} aktif ilan` + (u.is_verified == 1 ? '' : ` · <span title="Okul e-postasıyla kayıt olan ya da yönetici tarafından doğrulanan hesaplar ${o.data.limits.verified} ilana kadar açabilir">Doğrulanınca ${o.data.limits.verified}</span>`);
    grid.innerHTML = r.items.map(i => listingCard(i, { showStatus: true })).join('')
      || '<p class="muted">Henüz ilanınız yok. <a href="ilan-ver.html">İlk ilanınızı verin →</a></p>';
  } catch (e) {
    grid.innerHTML = `<p class="error-box">${esc(e.message)}</p>`;
  }
});
