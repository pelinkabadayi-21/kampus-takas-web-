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
          <img src="${esc(imgUrl(img.image_url, 240))}" alt="">
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
  // ------------------------------------------------------------ Hafta 10: Yapay zekâ önerisi
  // Kullanıcı öneriyi uygularsa önerinin NUMARASI ilanla birlikte gönderilir (fiyatın kendisi değil).
  // Sunucu önerilen fiyatı kendi kaydından alır; kullanıcı bu alanı değiştiremez (K7).
  let aiSuggestionId = null;
  const aiBtn = document.getElementById('ai-btn');
  const aiResult = document.getElementById('ai-result');
  const tl = v => v == null ? '—' : Number(v).toLocaleString('tr-TR') + ' ₺';

  aiBtn.addEventListener('click', async () => {
    const title = form.elements.title.value.trim();
    if (title.length < 3) { aiResult.innerHTML = '<p class="error-box">Önce en az 3 karakterlik bir başlık yazın.</p>'; form.elements.title.focus(); return; }
    // Formdaki bilgiler + varsa ilk yeni fotoğraf
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', form.elements.description.value);
    fd.append('item_condition', form.elements.item_condition.value);
    fd.append('listing_type', form.querySelector('input[name=listing_type]:checked').value);
    if (pending[0]) fd.append('image', pending[0].file);

    aiBtn.disabled = true; aiBtn.textContent = 'Düşünüyor…';
    aiResult.innerHTML = '<p class="muted">Öneri hazırlanıyor, birkaç saniye sürebilir…</p>';
    try {
      const { data: s } = await apiUpload('/ai/suggest', fd);
      drawSuggestion(s);
    } catch (err) {
      aiResult.innerHTML = `<p class="error-box">${esc(err.message)}</p>`;
      if (err.status === 401) location.href = 'giris.html?next=' + encodeURIComponent(location.pathname + location.search);
    }
    aiBtn.disabled = false; aiBtn.textContent = '✨ Yeniden öner';
  });

  // Öneriyi kaynağı, gerekçesi, güveni ve yapılan düzeltmelerle birlikte gösterir (şeffaflık, A7)
  function drawSuggestion(s) {
    const pct = Math.round((s.confidence ?? 0) * 100);
    const isAi = s.source === 'ai';
    aiResult.innerHTML = `
      <div class="ai-card">
        <span class="ai-source ${isAi ? 'ai' : 'rule'}">${isAi ? '🤖 Yapay zekâ önerisi' : '📏 Site verisine göre (kural tabanlı)'}</span>
        <table class="facts">
          <tr><th>Kategori</th><td><b>${esc(s.category.name)}</b></td></tr>
          <tr><th>Fiyat</th><td>${s.price == null ? '<span class="muted">Fiyat önerisi yok</span>'
            : `<b>${tl(s.price)}</b> <small class="muted">(makul aralık ${tl(s.price_min)} – ${tl(s.price_max)})</small>`}</td></tr>
          <tr><th>Güven</th><td><span class="ai-bar"><span style="width:${pct}%"></span></span> %${pct}</td></tr>
          <tr><th>Gerekçe</th><td>${esc(s.reason)}</td></tr>
          <tr><th>Site verisi</th><td>${s.market.count ? `Bu kategoride ${s.market.count} satılık ilan, medyan ${tl(s.market.median)}` : 'Bu kategoride henüz satılık ilan yok'}
            ${s.market.similar.length ? '<br><small class="muted">Benzer: ' + s.market.similar.map(m => `${esc(m.title)} (${tl(m.price)})`).join(' · ') + '</small>' : ''}</td></tr>
        </table>
        ${s.warnings.length ? `<ul class="ai-warn">${s.warnings.map(w => `<li>⚠ ${esc(w)}</li>`).join('')}</ul>` : ''}
        <div class="ai-actions">
          <button type="button" class="btn" data-apply="both">İkisini de uygula</button>
          <button type="button" class="btn btn-outline" data-apply="cat">Sadece kategori</button>
          ${s.price != null ? '<button type="button" class="btn btn-outline" data-apply="price">Sadece fiyat</button>' : ''}
        </div>
        <small class="muted">Öneriler otomatik üretilir ve hatalı olabilir; kategoriyi ve fiyatı dilediğiniz gibi değiştirebilirsiniz.</small>
      </div>`;
    aiResult.querySelectorAll('[data-apply]').forEach(b => b.addEventListener('click', () => {
      const what = b.dataset.apply;
      if (what !== 'price') form.elements.category_id.value = s.category.id;
      if (what !== 'cat' && s.price != null) form.elements.price.value = Math.round(s.price);
      aiSuggestionId = s.suggestion_id;      // ilan kaydedilirken gönderilecek
      b.textContent = 'Uygulandı ✔';
    }));
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    msg.innerHTML = ''; showFieldErrors(form, {});
    // Form verilerini al; fotoğraf alanını ve gereksiz alanları çıkar
    const data = Object.fromEntries(new FormData(form));
    delete data.photo;
    if (data.listing_type === 'bagis') data.price = '';
    if (document.getElementById('status-field').hidden || !form.elements.status) delete data.status;
    // Hafta 10: yeni ilanda öneri kullanıldıysa önerinin numarası (kabul edildi mi, sunucu günlüğe yazar)
    if (!editId && aiSuggestionId) data.ai_suggestion_id = aiSuggestionId;

    const btn = document.getElementById('submit-btn');
    btn.disabled = true; const label = btn.textContent; btn.textContent = 'Kaydediliyor…';
    try {
      // Önce ilanı kaydet (düzenlemede PUT, yeni ilanda POST)
      const saved = editId ? await apiPut('/listings/' + editId, data) : await apiPost('/listings', data);
      const id = saved.data.id;

      // Fotoğrafları sırayla yükle; biri başarısız olsa da ilan kaydedilmiş olur
      const failed = [];
      // Sonra fotoğrafları tek tek yükle; yüklenemeyenleri not et
      let backup = 0;
      for (const [i, p] of pending.entries()) {
        btn.textContent = `Fotoğraf yükleniyor ${i + 1}/${pending.length}…`;
        const fd = new FormData(); fd.append('image', p.file);
        try {
          const up = await apiUpload(`/listings/${id}/images`, fd);
          // Hafta 9: Cloudinary'ye ulaşılamadıysa fotoğraf sunucuya yedeklenir; kullanıcıya bilgi verilir
          if (up.storage === 'yerel (yedek)') backup++;
        } catch (err) { failed.push(`${p.file.name}: ${err.message}`); }
      }
      if (failed.length) sessionStorage.setItem('flash', 'Bazı fotoğraflar yüklenemedi — ' + failed.join(' · '));
      else if (backup) sessionStorage.setItem('flash', `Görsel servisine şu an ulaşılamadı; ${backup} fotoğraf geçici olarak sunucuya kaydedildi.`);
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
