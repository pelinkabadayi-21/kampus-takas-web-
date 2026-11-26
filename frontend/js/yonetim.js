// Yönetim sayfası. Sadece "yonetici" rolü veri alabilir: sunucu diğerlerine 403 döner.
document.addEventListener('DOMContentLoaded', async () => {
  // Giriş zorunlu (yetki kontrolünü sunucu yapar)
  try { await requireLogin(); } catch (e) { return; }
  const box = document.getElementById('admin');
  const me = await currentUser();

  // Kullanıcı listesini sunucudan alıp tablo olarak çizer; yetki yoksa uyarı gösterir.
  async function load() {
    try {
      // Tüm kullanıcıları getir ve tablo olarak göster
      const { data } = await apiGet('/admin/users');
      box.innerHTML = `
        <p class="muted">${data.length} kullanıcı</p>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Ad Soyad</th><th>E-posta</th><th>İlan</th><th>Doğrulama</th><th>Rol</th></tr></thead>
          <tbody>${data.map(u => `
            <tr>
              <td>${esc(u.full_name)}</td>
              <td>${esc(u.email)}</td>
              <td>${u.listing_count}</td>
              <td><label><input type="checkbox" data-verify="${u.id}" ${u.is_verified == 1 ? 'checked' : ''}> Doğrulanmış</label></td>
              <td>${u.id == me.id ? '🛡️ Yönetici (siz)' : `
                <select data-role="${u.id}">
                  <option value="ogrenci" ${u.role === 'ogrenci' ? 'selected' : ''}>Öğrenci</option>
                  <option value="yonetici" ${u.role === 'yonetici' ? 'selected' : ''}>Yönetici</option>
                </select>`}</td>
            </tr>`).join('')}</tbody>
        </table></div>`;
    } catch (e) {
      // Sunucu 403 döndürdüyse "yetkiniz yok" mesajı
      box.innerHTML = e.status === 403
        ? '<p class="error-box">⛔ Bu sayfaya erişim yetkiniz yok. Yönetim paneli sadece yöneticilere açıktır.</p>'
        : `<p class="error-box">${esc(e.message)}</p>`;
    }
  }

  box.addEventListener('change', async e => {
    const el = e.target;
    try {
      // Rol veya doğrulama kutusu değişince hemen sunucuya kaydet
      if (el.dataset.role)   await apiPut(`/admin/users/${el.dataset.role}/role`, { role: el.value });
      if (el.dataset.verify) await apiPut(`/admin/users/${el.dataset.verify}/verify`, { is_verified: el.checked });
      flash('Kaydedildi ✔');
    } catch (err) {
      // Hata olursa mesaj göster ve tabloyu eski haline getir
      flash(err.message, true);
      load();
    }
  });

  // Ekranın köşesinde kısa bir başarı/hata mesajı gösterir.
  function flash(text, isError) {
    const f = document.getElementById('flash');
    f.className = isError ? 'error-box' : 'success-box';
    f.textContent = text; f.hidden = false;
    // Mesajı 2,5 saniye sonra gizle
    clearTimeout(flash.t); flash.t = setTimeout(() => f.hidden = true, 2500);
  }
  load();

  // ------------------------------------------------------------ Hafta 9: Harici servisler
  const intBox = document.getElementById('integrations');
  const SERVICE_LABEL = { cloudinary: '☁️ Cloudinary', brevo: '✉️ Brevo', yerel: '💾 Sunucu diski' };

  // Servislerin durumunu, son 24 saatin özetini ve son çağrıları gösterir.
  async function loadIntegrations() {
    let d;
    try { d = (await apiGet('/admin/integrations')).data; } catch (e) { return; }   // yönetici değilse kart gizli kalır
    const st = d.status;
    intBox.hidden = false;
    intBox.innerHTML = `
      <div class="card-head"><h2>🌐 Harici servisler</h2>
        <span><button class="btn btn-outline" data-test="image" ${st.image_storage === 'cloudinary' ? '' : 'disabled'}>Görsel servisini dene</button>
        <button class="btn btn-outline" data-test="email" ${st.email === 'brevo' ? '' : 'disabled'}>Bana test e-postası gönder</button></span></div>
      <table class="facts">
        <tr><th>Fotoğraf depolama</th><td>${st.image_storage === 'cloudinary' ? '<span class="status ok">Cloudinary açık</span>'
          : '<span class="status">Sunucu diski</span> <small class="muted">CLOUDINARY_URL tanımlı değil</small>'}</td></tr>
        <tr><th>E-posta bildirimi</th><td>${st.email === 'brevo' ? '<span class="status ok">Brevo açık</span>'
          : '<span class="status">Kapalı</span> <small class="muted">BREVO_API_KEY ve MAIL_FROM tanımlı değil</small>'}</td></tr>
      </table>
      <h3>Son 24 saat</h3>
      ${d.summary.length ? `<div class="table-wrap"><table class="data-table">
        <thead><tr><th>Servis</th><th>Çağrı</th><th>Başarılı</th><th>Başarısız</th><th>Ort. süre</th></tr></thead>
        <tbody>${d.summary.map(s => `<tr><td>${SERVICE_LABEL[s.service] || esc(s.service)}</td><td>${s.total}</td>
          <td>${s.ok}</td><td>${s.failed > 0 ? `<b style="color:var(--fail)">${s.failed}</b>` : 0}</td><td>${s.avg_ms} ms</td></tr>`).join('')}</tbody>
      </table></div>` : '<p class="muted">Son 24 saatte harici servise istek yapılmadı.</p>'}
      <h3>Son çağrılar</h3>
      ${d.recent.length ? `<div class="table-wrap"><table class="data-table">
        <thead><tr><th>Zaman</th><th>Servis</th><th>İşlem</th><th>Sonuç</th><th>Deneme</th><th>Süre</th><th>Hata</th></tr></thead>
        <tbody>${d.recent.map(r => `<tr><td>${timeAgo(r.created_at)}</td><td>${SERVICE_LABEL[r.service] || esc(r.service)}</td>
          <td>${esc(r.action)}</td><td>${r.success == 1 ? '✅ ' + (r.status_code || '') : '❌ ' + (r.status_code || '—')}</td>
          <td>${r.attempts}</td><td>${r.duration_ms} ms</td><td style="white-space:normal">${esc(r.error || '')}</td></tr>`).join('')}</tbody>
      </table></div>` : '<p class="muted">Henüz kayıt yok.</p>'}`;
  }

  // Test butonları: anahtarların doğru girildiğini canlı sitede dener
  intBox.addEventListener('click', async e => {
    const btn = e.target.closest('[data-test]');
    if (!btn) return;
    const old = btn.textContent;
    btn.disabled = true; btn.textContent = 'Deneniyor…';
    try {
      const r = await apiPost('/admin/integrations/test', { service: btn.dataset.test });
      flash(`${r.message} (${r.duration_ms} ms)`);
    } catch (err) {
      flash(err.message, true);
    }
    btn.disabled = false; btn.textContent = old;
    loadIntegrations();
  });
  loadIntegrations();
});
