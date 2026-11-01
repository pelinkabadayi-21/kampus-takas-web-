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
  } else if (!me) {
    actions.innerHTML = `<a class="btn" href="giris.html?next=${encodeURIComponent(location.pathname + location.search)}">Mesaj ve teklif için giriş yapın</a>`;
  } else {
    actions.innerHTML = `
      <a class="btn" href="mesajlar.html?ilan=${l.id}&kisi=${l.user_id}">💬 Mesaj gönder</a>
      ${l.status === 'aktif' ? `<button class="btn btn-outline" id="offer-open">${l.listing_type === 'bagis' ? '🎁 Talep et' : l.listing_type === 'satis' ? '💰 Fiyat teklif et' : '🔄 Takas teklif et'}</button>` : ''}`;
    document.getElementById('offer-open')?.addEventListener('click', () => openOfferForm(l));
  }

  // Teklifler: ilan sahibi gelen tüm teklifleri, diğerleri kendi tekliflerini görür
  if (me) loadOffers(l, me);
});

async function loadOffers(l, me) {
  const box = document.getElementById('offers-box');
  try {
    const { data } = await apiGet(`/listings/${l.id}/offers`);
    if (!data.length) { box.hidden = true; return; }
    box.hidden = false;
    box.innerHTML = `<h2>${l.can_edit ? `Gelen teklifler (${data.length})` : 'Bu ilana verdiğiniz teklifler'}</h2>`
      + data.map(o => offerCard(o, me, { showListing: false })).join('');
    if (!box.dataset.bound) {
      box.dataset.bound = 1;
      bindOfferActions(box, () => location.reload(),
        err => document.getElementById('notice').innerHTML = `<p class="error-box">${esc(err.message)}</p>`);
    }
    // Bekleyen teklifi varsa tekrar teklif butonunu gizle (iş kuralı T2'nin arayüz tarafı)
    if (!l.can_edit && data.some(o => o.stage === 'beklemede')) document.getElementById('offer-open')?.remove();
  } catch (e) { box.hidden = true; }
}

async function openOfferForm(l) {
  const panel = document.getElementById('offer-form');
  const type = l.listing_type;
  let mine = [];
  if (type === 'takas') {
    try { mine = (await apiGet('/me/listings')).items.filter(i => i.status === 'aktif'); } catch (e) {}
  }
  panel.hidden = false;
  panel.innerHTML = `
    <form id="offer" novalidate>
      <h2>${type === 'bagis' ? '🎁 Bağış talebi' : type === 'satis' ? '💰 Fiyat teklifi' : '🔄 Takas teklifi'}</h2>
      ${type === 'takas' ? `
        <div class="field">
          <label for="offered_listing_id">Karşılığında vereceğiniz ilan</label>
          <select id="offered_listing_id" name="offered_listing_id">
            <option value="">— İlan seçmeden sadece para teklif et —</option>
            ${mine.map(i => `<option value="${i.id}">${esc(i.title)}</option>`).join('')}
          </select>
          ${mine.length ? '' : '<small class="muted">Yayında ilanınız yok. <a href="ilan-ver.html">Takas için ilan verin →</a></small>'}
          <small class="field-error"></small>
        </div>` : ''}
      ${type !== 'bagis' ? `
        <div class="field">
          <label for="offered_amount">${type === 'satis' ? 'Teklif ettiğiniz tutar (₺)' : 'Üstüne para (₺) <span class="muted">(isteğe bağlı)</span>'}</label>
          <input type="number" id="offered_amount" name="offered_amount" min="0" step="1" ${type === 'satis' ? `value="${l.price ?? ''}"` : ''}>
          ${type === 'satis' ? `<small class="muted">İlan fiyatı: ${formatPrice(l)}</small>` : ''}
          <small class="field-error"></small>
        </div>` : '<p class="muted">Bağış ilanında ücret yoktur; ilan sahibine talebinizi iletirsiniz. Birden fazla talep varsa ilan sahibi birini seçer.</p>'}
      <div class="field">
        <label for="note">Not <span class="muted">(isteğe bağlı)</span></label>
        <input id="note" name="note" maxlength="255" placeholder="ör. Yarın öğlen kütüphane önünde buluşabiliriz">
        <small class="field-error"></small>
      </div>
      <div class="actions">
        <button class="btn" type="submit">Teklifi gönder</button>
        <button class="btn btn-outline" type="button" id="offer-close">Vazgeç</button>
      </div>
    </form>`;
  panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  document.getElementById('offer-close').addEventListener('click', () => panel.hidden = true);
  const form = document.getElementById('offer');
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    data.listing_id = l.id;
    form.querySelector('button[type=submit]').disabled = true;
    try {
      await apiPost('/offers', data);
      panel.innerHTML = '<p class="success-box">✔ Teklifiniz gönderildi. İlan sahibi yanıt verince bildirim alacaksınız.</p>';
      loadOffers(l, await currentUser());
      document.getElementById('offer-open')?.remove();
    } catch (err) {
      showFieldErrors(form, err.errors);
      if (!Object.keys(err.errors || {}).length) form.insertAdjacentHTML('afterbegin', `<p class="error-box">${esc(err.message)}</p>`);
      form.querySelector('button[type=submit]').disabled = false;
    }
  });
}
