// İlan detay sayfası
document.addEventListener('DOMContentLoaded', async () => {
  const box = document.getElementById('detail');
  const id = new URLSearchParams(location.search).get('id');
  if (!id) { box.innerHTML = '<p class="error-box">İlan seçilmedi.</p>'; return; }

  try {
    const { data: l } = await apiGet('/listings/' + encodeURIComponent(id));
    document.title = l.title + ' – Kampüs Takas';
    box.innerHTML = `
      <div class="detail-cover">${l.images.length ? `<img src="${esc(l.images[0].image_url)}" alt="">` : '📦'}</div>
      <div>
        <span class="badge badge-${l.listing_type}">${TYPE_LABEL[l.listing_type]}</span>
        ${l.status !== 'aktif' ? `<span class="badge">${esc(l.status)}</span>` : ''}
        <h1>${esc(l.title)}</h1>
        <div class="price big">${formatPrice(l)}</div>
        ${l.ai_suggested_price && l.listing_type !== 'bagis'
          ? `<p class="muted">🤖 Önerilen piyasa fiyatı: ${Number(l.ai_suggested_price).toLocaleString('tr-TR')} ₺</p>` : ''}
        <table class="facts">
          <tr><th>Kategori</th><td>${esc(l.category_name)}</td></tr>
          <tr><th>Durum</th><td>${COND_LABEL[l.item_condition]}</td></tr>
          <tr><th>Buluşma noktası</th><td>📍 ${esc(l.meeting_point || 'Belirtilmedi')}</td></tr>
          <tr><th>İlan sahibi</th><td>${esc(l.owner_name)} ${l.owner_verified == 1 ? '<span title="Doğrulanmış öğrenci">✅</span>' : ''}</td></tr>
          <tr><th>Tarih</th><td>${new Date(l.created_at.replace(' ', 'T')).toLocaleDateString('tr-TR')}</td></tr>
        </table>
        <p>${esc(l.description || '')}</p>
        <div class="actions">
          <button class="btn" disabled title="İleriki haftalarda">💬 Mesaj gönder</button>
          <button class="btn btn-outline" disabled title="İleriki haftalarda">🔄 Takas teklif et</button>
        </div>
      </div>`;
  } catch (e) {
    box.innerHTML = `<p class="error-box">${esc(e.message)}</p>`;
  }
});
