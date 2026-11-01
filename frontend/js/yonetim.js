// Yönetim sayfası. Sadece "yonetici" rolü veri alabilir: sunucu diğerlerine 403 döner.
document.addEventListener('DOMContentLoaded', async () => {
  try { await requireLogin(); } catch (e) { return; }
  const box = document.getElementById('admin');
  const me = await currentUser();

  async function load() {
    try {
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
      box.innerHTML = e.status === 403
        ? '<p class="error-box">⛔ Bu sayfaya erişim yetkiniz yok. Yönetim paneli sadece yöneticilere açıktır.</p>'
        : `<p class="error-box">${esc(e.message)}</p>`;
    }
  }

  box.addEventListener('change', async e => {
    const el = e.target;
    try {
      if (el.dataset.role)   await apiPut(`/admin/users/${el.dataset.role}/role`, { role: el.value });
      if (el.dataset.verify) await apiPut(`/admin/users/${el.dataset.verify}/verify`, { is_verified: el.checked });
      flash('Kaydedildi ✔');
    } catch (err) {
      flash(err.message, true);
      load();
    }
  });

  function flash(text, isError) {
    const f = document.getElementById('flash');
    f.className = isError ? 'error-box' : 'success-box';
    f.textContent = text; f.hidden = false;
    clearTimeout(flash.t); flash.t = setTimeout(() => f.hidden = true, 2500);
  }
  load();
});
