// Korumalı sayfa: giriş yapmayan kullanıcı giriş sayfasına yönlendirilir.
document.addEventListener('DOMContentLoaded', async () => {
  let u;
  // Giriş yoksa giriş sayfasına yönlendirilir
  try { u = await requireLogin(); } catch (e) { return; }

  // Profil bilgilerini göster
  document.getElementById('profile').innerHTML = `
    <h2>${esc(u.full_name)} ${u.is_verified == 1 ? '<span class="badge badge-bagis">✅ Doğrulanmış öğrenci</span>' : '<span class="badge">Doğrulanmamış</span>'}</h2>
    <table class="facts">
      <tr><th>E-posta</th><td>${esc(u.email)}</td></tr>
      <tr><th>Bölüm</th><td>${esc(u.department || '—')}</td></tr>
      <tr><th>Rol</th><td>${u.role === 'yonetici' ? '🛡️ Yönetici' : '🎓 Öğrenci'}</td></tr>
    </table>
    <button class="btn btn-outline" id="logout-btn">Çıkış yap</button>`;
  document.getElementById('logout-btn').addEventListener('click', logout);

  // Aldığım değerlendirmeler
  try {
    // Aldığım değerlendirmeleri getir ve listele
    const { data } = await apiGet(`/users/${u.id}/reviews`);
    document.getElementById('reviews').innerHTML =
      `<p>${starsText(data.summary.average, data.summary.total)}</p>` +
      (data.items.map(r => `
        <div class="review-item"><span class="stars">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</span>
          <b>${esc(r.reviewer_name)}</b> <small class="muted">${timeAgo(r.created_at)}</small>
          ${r.comment ? `<div>${esc(r.comment)}</div>` : ''}</div>`).join('') || '<p class="muted">Tamamlanan takaslardan sonra karşı taraf sizi değerlendirebilir.</p>');
  } catch (e) { document.getElementById('reviews').innerHTML = ''; }

  const grid = document.getElementById('my-listings');
  const info = document.getElementById('limit-info');
  try {
    // Kendi ilanlarımı ve ilan sınırlarını aynı anda getir
    const [r, o] = await Promise.all([apiGet('/me/listings'), apiGet('/listings/options')]);
    // Kaç aktif ilanım var, sınırım kaç (doğrulanmışsa daha fazla)
    const active = r.items.filter(i => i.status === 'aktif').length;
    const limit = u.role === 'yonetici' ? null : (u.is_verified == 1 ? o.data.limits.verified : o.data.limits.unverified);
    info.innerHTML = limit === null ? '' :
      `${active} / ${limit} aktif ilan` + (u.is_verified == 1 ? '' : ` · <span title="Okul e-postasıyla kayıt olan ya da yönetici tarafından doğrulanan hesaplar ${o.data.limits.verified} ilana kadar açabilir">Doğrulanınca ${o.data.limits.verified}</span>`);
    // İlanları durumlarıyla birlikte kart olarak göster
    grid.innerHTML = r.items.map(i => listingCard(i, { showStatus: true })).join('')
      || '<p class="muted">Henüz ilanınız yok. <a href="ilan-ver.html">İlk ilanınızı verin →</a></p>';
  } catch (e) {
    grid.innerHTML = `<p class="error-box">${esc(e.message)}</p>`;
  }
});
