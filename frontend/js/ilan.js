// İlan detay sayfası: galeri, bilgiler, sahibi/yönetici için düzenleme butonları
document.addEventListener('DOMContentLoaded', async () => {
  const box = document.getElementById('detail');
  const params = new URLSearchParams(location.search);
  const id = params.get('id');
  if (!id) { box.innerHTML = '<p class="error-box">İlan seçilmedi.</p>'; return; }

  const notice = document.getElementById('notice');
  if (params.get('yeni')) notice.innerHTML = '<p class="success-box">🎉 İlanınız yayında!</p>';
  if (params.get('guncellendi')) notice.innerHTML = '<p class="success-box">✔ İlan güncellendi.</p>';
  const flash = sessionStorage.getItem('flash');
  if (flash) { notice.innerHTML += `<p class="error-box">${esc(flash)}</p>`; sessionStorage.removeItem('flash'); }

  let l;
  try { l = (await apiGet('/listings/' + encodeURIComponent(id))).data; }
  catch (e) { box.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; return; }

  document.title = l.title + ' – Kampüs Takas';
  const imgs = l.images;
  const me = await currentUser();
  const isOwner = me && me.id == l.user_id;

  box.innerHTML = `
    <div class="gallery">
      <div class="detail-cover" id="main-photo">${imgs.length ? `<img src="${esc(imgUrl(imgs[0].image_url))}" alt="${esc(l.title)}">` : '📦'}</div>
      ${imgs.length > 1 ? `<div class="thumbs">${imgs.map((img, i) =>
        `<button type="button" class="thumb ${i === 0 ? 'active' : ''}" data-src="${esc(imgUrl(img.image_url))}"><img src="${esc(imgUrl(img.image_url))}" alt=""></button>`).join('')}</div>` : ''}
    </div>
    <div>
      <span class="badge badge-${l.listing_type}">${TYPE_LABEL[l.listing_type]}</span>
      ${l.status !== 'aktif' ? `<span class="badge badge-status-${l.status}">${STATUS_LABEL[l.status]}</span>` : ''}
      <h1>${esc(l.title)}</h1>
      <div class="price big">${formatPrice(l)}</div>
      ${l.ai_suggested_price && l.listing_type !== 'bagis'
        ? `<p class="muted">🤖 Önerilen piyasa fiyatı: ${Number(l.ai_suggested_price).toLocaleString('tr-TR')} ₺</p>` : ''}
      <table class="facts">
        <tr><th>Kategori</th><td><a href="ilanlar.html?category_id=${l.category_id}">${esc(l.category_name)}</a></td></tr>
        <tr><th>Ürün durumu</th><td>${COND_LABEL[l.item_condition]}</td></tr>
        <tr><th>Buluşma noktası</th><td>📍 ${esc(l.meeting_point || 'Belirtilmedi')}</td></tr>
        <tr><th>İlan sahibi</th><td>${esc(l.owner_name)} ${l.owner_verified == 1 ? '<span title="Doğrulanmış öğrenci">✅</span>' : ''}</td></tr>
        <tr><th>Tarih</th><td>${new Date(l.created_at.replace(' ', 'T')).toLocaleDateString('tr-TR')}</td></tr>
      </table>
      <p class="description">${esc(l.description || '')}</p>
      <div class="actions" id="actions"></div>
    </div>`;

  // Galeri: küçük resme tıklayınca büyük resmi değiştir
  box.querySelectorAll('.thumb').forEach(t => t.addEventListener('click', () => {
    document.querySelector('#main-photo img').src = t.dataset.src;
    box.querySelectorAll('.thumb').forEach(x => x.classList.toggle('active', x === t));
  }));

  const actions = document.getElementById('actions');
  if (l.can_edit) {
    // Butonlar görünüm içindir; yetki kontrolü sunucuda tekrar yapılır
    const canToggle = l.status === 'aktif' || l.status === 'kaldirildi';
    actions.innerHTML = `
      <a class="btn" href="ilan-ver.html?id=${l.id}">✏️ Düzenle</a>
      ${canToggle ? `<button class="btn btn-outline" id="toggle">${l.status === 'aktif' ? '⏸ Yayından kaldır' : '▶ Tekrar yayınla'}</button>` : ''}
      <button class="btn btn-danger" id="delete">🗑 Sil</button>
      ${!isOwner ? '<small class="muted" style="width:100%">🛡️ Yönetici olarak görüntülüyorsunuz</small>' : ''}`;
    document.getElementById('toggle')?.addEventListener('click', async () => {
      try { await apiPut('/listings/' + l.id, { status: l.status === 'aktif' ? 'kaldirildi' : 'aktif' }); location.reload(); }
      catch (e) { notice.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; }
    });
    document.getElementById('delete').addEventListener('click', async () => {
      if (!confirm('İlan ve fotoğrafları kalıcı olarak silinecek. Emin misiniz?')) return;
      try { await apiDelete('/listings/' + l.id); location.href = 'hesabim.html'; }
      catch (e) { notice.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; }
    });
  } else {
    actions.innerHTML = `
      <button class="btn" disabled title="Hafta 7'de">💬 Mesaj gönder</button>
      <button class="btn btn-outline" disabled title="Hafta 7'de">🔄 Takas teklif et</button>`;
  }
});
