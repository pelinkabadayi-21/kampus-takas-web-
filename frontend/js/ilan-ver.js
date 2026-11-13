// İlan oluşturma ve düzenleme (ilan-ver.html?id=5 -> düzenleme modu)
document.addEventListener('DOMContentLoaded', async () => {
  try { await requireLogin(); } catch (e) { return; }

  const form = document.getElementById('listing-form');
  const msg = document.getElementById('form-msg');
  // Adreste id varsa düzenleme, yoksa yeni ilan
  const editId = new URLSearchParams(location.search).get('id');
  let opts, existing = [];       // düzenlemede sunucudaki fotoğraflar
  let pending = [];              // henüz yüklenmemiş yeni dosyalar: {file, url}

  // 1) Seçenekleri sunucudan al (kurallar tek yerde: ListingService::options)
  try {
    // Form seçeneklerini ve kategorileri getirip seçim kutularını doldur
    const [o, c] = await Promise.all([apiGet('/listings/options'), apiGet('/categories')]);
    opts = o.data;
    form.elements.category_id.innerHTML += c.data.map(k => `<option value="${k.id}">${esc(k.name)}</option>`).join('');
    form.elements.item_condition.innerHTML = Object.entries(opts.conditions).map(([v, t]) => `<option value="${v}" ${v === 'iyi' ? 'selected' : ''}>${t}</option>`).join('');
    document.getElementById('meeting-points').innerHTML = opts.meeting_points.map(p => `<option value="${esc(p)}">`).join('');
    form.elements.price.max = opts.max_price;
  } catch (e) {
    msg.innerHTML = `<p class="error-box">Form yüklenemedi: ${esc(e.message)}</p>`; return;
  }

  // 2) Düzenleme modu: ilanı doldur
  // Düzenleme modunda mevcut ilanın bilgilerini forma doldur
  if (editId) {
    try {
      const { data: l } = await apiGet('/listings/' + encodeURIComponent(editId));
      // Başkasının ilanını düzenlemeye izin verme
      if (!l.can_edit) { msg.innerHTML = '<p class="error-box">⛔ Bu ilanı sadece sahibi veya yönetici düzenleyebilir.</p>'; form.querySelector('.actions').hidden = true; return; }
      document.getElementById('form-title').textContent = 'İlanı Düzenle';
      document.getElementById('submit-btn').textContent = 'Değişiklikleri Kaydet';
      document.title = 'İlanı Düzenle – Kampüs Takas';
      // Alanları ilanın mevcut değerleriyle doldur
      for (const k of ['title', 'category_id', 'item_condition', 'description', 'meeting_point']) form.elements[k].value = l[k] ?? '';
      form.elements.price.value = l.price ?? '';
      form.querySelector(`input[name=listing_type][value=${l.listing_type}]`).checked = true;
      const st = document.getElementById('status-field');
      // Rezerve veya tamamlanmış ilanın durumu buradan değiştirilemez
      if (l.status === 'rezerve') {
        st.innerHTML = '<p class="muted">🔒 Bu ilan bir takas teklifi kabul edildiği için <b>rezerve</b>.</p>';
      } else if (l.status === 'tamamlandi') {
        st.innerHTML = '<p class="muted">✅ Bu ilan tamamlandı; tekrar yayına alınamaz.</p>';
      } else {
        form.elements.status.value = l.status;
      }
      st.hidden = false;
      existing = l.images;
    } catch (e) {
      msg.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; return;
    }
  }

  // 3) Türe göre fiyat alanı
  const priceField = document.getElementById('price-field');
  const priceHint = document.getElementById('price-hint');
  // İlan türüne göre fiyat alanını gösterir/gizler (bağışta fiyat yok).
  // Tür değişince fiyat alanını göster/gizle (bağışta fiyat yok)
  const syncType = () => {
    const t = form.elements.listing_type.value;
    priceField.hidden = t === 'bagis';
    priceHint.textContent = t === 'takas' ? '(isteğe bağlı: tahmini değer)' : '';
  };
  form.querySelectorAll('input[name=listing_type]').forEach(r => r.addEventListener('change', syncType));
  syncType();

  // 4) Açıklama sayacı
  const desc = form.elements.description, counter = document.getElementById('desc-count');
  // Açıklama karakter sayacını günceller.
  const count = () => counter.textContent = `${desc.value.length} / 2000`;
  desc.addEventListener('input', count); count();

  // 5) Fotoğraflar
  const grid = document.getElementById('photos');
  const photoErr = document.getElementById('photo-error');
  // Toplam fotoğraf sayısı (kayıtlı + yeni eklenen).
  // Toplam fotoğraf: sunucudakiler + yeni seçilenler
  const total = () => existing.length + pending.length;
  // Fotoğraf önizlemelerini çizer (kapak, sil, kapak yap butonlarıyla).
  const drawPhotos = () => {
    grid.innerHTML =
      existing.map((img, i) => `
        <div class="photo">
          <img src="${esc(imgUrl(img.image_url))}" alt="">
          ${i === 0 ? '<span class="photo-cover">Kapak</span>' : `<button type="button" class="photo-btn left" data-cover="${img.id}" title="Kapak yap">★</button>`}
          <button type="button" class="photo-btn" data-del="${img.id}" title="Sil">✕</button>
        </div>`).join('') +
      pending.map((p, i) => `
        <div class="photo pending">
          <img src="${p.url}" alt="">
          ${existing.length === 0 && i === 0 ? '<span class="photo-cover">Kapak</span>' : ''}
          <span class="photo-new">Yeni</span>
          <button type="button" class="photo-btn" data-remove="${i}" title="Kaldır">✕</button>
        </div>`).join('');
    // 5 fotoğrafa ulaşınca ekleme butonunu gizle
    document.getElementById('photo-add').hidden = total() >= opts.max_images;
    document.getElementById('photo-hint').textContent = `(${total()} / ${opts.max_images})`;
  };
  drawPhotos();

  document.getElementById('photo-input').addEventListener('change', e => {
    photoErr.textContent = '';
    // Seçilen her dosyayı kontrol et: sayı, tür ve boyut
    for (const f of e.target.files) {
      if (total() >= opts.max_images) { photoErr.textContent = `En fazla ${opts.max_images} fotoğraf ekleyebilirsiniz.`; break; }
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { photoErr.textContent = `"${f.name}" desteklenmiyor (JPG, PNG, WEBP).`; continue; }
      if (f.size > opts.max_image_mb * 1024 * 1024) { photoErr.textContent = `"${f.name}" ${opts.max_image_mb} MB'tan büyük.`; continue; }
      // Uygunsa önizleme için listeye ekle (henüz yüklenmedi)
      pending.push({ file: f, url: URL.createObjectURL(f) });
    }
    e.target.value = '';
    drawPhotos();
  });

  grid.addEventListener('click', async e => {
    const b = e.target.closest('button'); if (!b) return;
    // Henüz yüklenmemiş fotoğrafı listeden çıkar
    if (b.dataset.remove !== undefined) { URL.revokeObjectURL(pending[b.dataset.remove].url); pending.splice(b.dataset.remove, 1); drawPhotos(); return; }
    try {
      if (b.dataset.del) {
        // Sunucudaki fotoğrafı onay alıp sil
        if (!confirm('Bu fotoğraf silinsin mi?')) return;
        await apiDelete(`/listings/${editId}/images/${b.dataset.del}`);
        existing = existing.filter(i => i.id != b.dataset.del);
      }
      // Seçilen fotoğrafı kapak yap
      if (b.dataset.cover) existing = (await apiPut(`/listings/${editId}/images/${b.dataset.cover}/cover`)).data;
      drawPhotos();
    } catch (err) { photoErr.textContent = err.message; }
  });

  // 6) Gönder
  form.addEventListener('submit', async e => {
    e.preventDefault();
    msg.innerHTML = ''; showFieldErrors(form, {});
    // Form verilerini al; fotoğraf alanını ve gereksiz alanları çıkar
    const data = Object.fromEntries(new FormData(form));
    delete data.photo;
    if (data.listing_type === 'bagis') data.price = '';
    if (document.getElementById('status-field').hidden || !form.elements.status) delete data.status;

    const btn = document.getElementById('submit-btn');
    btn.disabled = true; const label = btn.textContent; btn.textContent = 'Kaydediliyor…';
    try {
      // Önce ilanı kaydet (düzenlemede PUT, yeni ilanda POST)
      const saved = editId ? await apiPut('/listings/' + editId, data) : await apiPost('/listings', data);
      const id = saved.data.id;

      // Fotoğrafları sırayla yükle; biri başarısız olsa da ilan kaydedilmiş olur
      const failed = [];
      // Sonra fotoğrafları tek tek yükle; yüklenemeyenleri not et
      for (const [i, p] of pending.entries()) {
        btn.textContent = `Fotoğraf yükleniyor ${i + 1}/${pending.length}…`;
        const fd = new FormData(); fd.append('image', p.file);
        try { await apiUpload(`/listings/${id}/images`, fd); } catch (err) { failed.push(`${p.file.name}: ${err.message}`); }
      }
      if (failed.length) sessionStorage.setItem('flash', 'Bazı fotoğraflar yüklenemedi — ' + failed.join(' · '));
      // İlan sayfasına git
      location.href = `ilan.html?id=${id}&${editId ? 'guncellendi' : 'yeni'}=1`;
    } catch (err) {
      showFieldErrors(form, err.errors);
      msg.innerHTML = `<p class="error-box">${esc(err.message)}</p>`;
      // Oturum düşmüşse giriş sayfasına gönder
      if (err.status === 401) location.href = 'giris.html?next=' + encodeURIComponent(location.pathname + location.search);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      btn.disabled = false; btn.textContent = label;
    }
  });
});
