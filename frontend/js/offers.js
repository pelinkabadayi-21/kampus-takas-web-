// Teklif kartı ve işlemleri: hem ilan sayfası hem teklifler sayfası kullanır.

// Teklif aşamalarının ekranda görünen adı ve rengi
const STAGE_LABEL = {
  beklemede:  ['⏳ Yanıt bekliyor', 'badge-status-rezerve'],
  kabul:      ['🤝 Kabul edildi · buluşma bekleniyor', 'badge-takas'],
  tamamlandi: ['✅ Tamamlandı', 'badge-status-tamamlandi'],
  red:        ['✖ Reddedildi', 'badge-status-kaldirildi'],
  iptal:      ['↩ İptal edildi', 'badge-status-kaldirildi'],
};

// Teklifin içeriğini yazar: "🔄 kitap + 💰 50 ₺" gibi.
function offerSummary(o) {
  const parts = [];
  // Teklifte ne var: önerilen ilan, tutar veya bağış talebi
  if (o.offered_listing_id) parts.push(`🔄 <a href="ilan.html?id=${o.offered_listing_id}">${esc(o.offered_listing_title)}</a>`);
  if (o.offered_amount != null) parts.push(`💰 ${money(o.offered_amount)}`);
  if (!parts.length) parts.push(o.listing_type === 'bagis' ? '🎁 Bağış talebi' : '—');
  return parts.join(' + ');
}

/**
 * @param o  teklif
 * @param me oturumdaki kullanıcı
 * @param showListing ilan başlığını da göster (teklifler sayfasında)
 */
function offerCard(o, me, { showListing = true } = {}) {
  // Ben ilan sahibi miyim; karşı taraf kim
  const iAmOwner = me.id == o.listing_owner_id;
  const other = iAmOwner ? { id: o.offerer_id, name: o.offerer_name } : { id: o.listing_owner_id, name: o.owner_name };
  const [label, cls] = STAGE_LABEL[o.stage] || [o.stage, ''];
  // Teklif işlemi için buton HTML'i üretir.
  const btn = (action, text, style = 'btn-outline') => `<button class="btn btn-sm ${style}" data-offer="${o.id}" data-action="${action}">${text}</button>`;

  // Aşamaya ve role göre hangi butonlar görünecek
  let actions = '';
  if (o.stage === 'beklemede' && iAmOwner) actions = btn('accept', '✔ Kabul et', '') + btn('reject', '✖ Reddet', 'btn-danger');
  if (o.stage === 'beklemede' && !iAmOwner) actions = btn('cancel', '↩ Geri çek', 'btn-danger');
  if (o.stage === 'kabul') actions = btn('complete', '✅ Takas tamamlandı', '') + btn('withdraw', 'Vazgeç', 'btn-danger');
  // Tamamlanınca: değerlendirmediysem "Değerlendir" butonu
  if (o.stage === 'tamamlandi') actions += o.i_reviewed
    ? '<span class="muted">✔ Değerlendirdiniz</span>'
    : `<button class="btn btn-sm" data-review="${o.id}" data-name="${esc(other.name)}">⭐ Değerlendir</button>`;
  actions += `<a class="btn btn-sm btn-outline" href="mesajlar.html?ilan=${o.listing_id}&kisi=${other.id}" title="${esc(other.name)} ile mesajlaş">💬 Mesaj</a>`;

  return `
    <div class="offer-card" id="offer-${o.id}">
      ${showListing ? `<a class="offer-thumb" href="ilan.html?id=${o.listing_id}">${o.cover_image ? `<img src="${esc(imgUrl(o.cover_image))}" alt="">` : '📦'}</a>` : ''}
      <div class="offer-body">
        ${showListing ? `<a class="offer-title" href="ilan.html?id=${o.listing_id}">${esc(o.listing_title)}</a>` : ''}
        <div class="offer-line"><b>${iAmOwner ? esc(o.offerer_name) : 'Teklifiniz'}</b>: ${offerSummary(o)}</div>
        ${o.note ? `<div class="offer-note">“${esc(o.note)}”</div>` : ''}
        <div class="offer-meta"><span class="badge ${cls}">${label}</span> <small class="muted">${timeAgo(o.created_at)}</small>
          ${o.stage === 'kabul' && o.meeting_point ? `<small class="muted">· 📍 ${esc(o.meeting_point)}</small>` : ''}</div>
        <div class="offer-actions">${actions}</div>
      </div>
    </div>`;
}

// Her işlemden önce sorulacak onay mesajları
const CONFIRM = {
  accept:   'Teklifi kabul ederseniz ilan(lar) rezerve olur ve bu ilana gelen diğer bekleyen teklifler kapanır. Onaylıyor musunuz?',
  reject:   'Teklif reddedilsin mi?',
  cancel:   'Teklifinizi geri çekmek istiyor musunuz?',
  complete: 'Takas/teslim gerçekleşti mi? İlan(lar) "tamamlandı" olarak kapanacak.',
  withdraw: 'Takastan vazgeçilsin mi? İlan(lar) tekrar yayına alınır.',
};

/** Kapsayıcıdaki teklif butonlarını bağlar; işlem sonrası onDone çağrılır. */
function bindOfferActions(container, onDone, onError) {
  container.addEventListener('click', async e => {
    // Teklif butonuna basılınca onay al, sunucuya gönder
    const b = e.target.closest('button[data-action]');
    if (!b || !confirm(CONFIRM[b.dataset.action])) return;
    b.disabled = true;
    // Örn: PUT /offers/5/accept
    try { await apiPut(`/offers/${b.dataset.offer}/${b.dataset.action}`); onDone(); }
    catch (err) { b.disabled = false; onError(err); }
  });
}

/** Tamamlanan takaslar için yıldızlı değerlendirme formu (Hafta 8: değerlendirme arayüzü) */
function bindReviewActions(container, onDone, onError) {
  container.addEventListener('click', e => {
    const b = e.target.closest('button[data-review]');
    if (!b) return;
    const box = b.closest('.offer-actions');
    // Form zaten açıksa tekrar açma
    if (box.querySelector('.review-form')) return;
    box.insertAdjacentHTML('afterend', `
      <form class="review-form">
        <div><b>${b.dataset.name}</b> kişisini değerlendirin:</div>
        <div class="star-input" role="radiogroup" aria-label="Puan">
          ${[1, 2, 3, 4, 5].map(i => `<button type="button" data-v="${i}" aria-label="${i} yıldız">★</button>`).join('')}
        </div>
        <input name="comment" maxlength="500" placeholder="Yorumunuz (isteğe bağlı)">
        <div class="offer-actions"><button class="btn btn-sm" type="submit">Gönder</button></div>
      </form>`);
    const form = box.nextElementSibling;
    let rating = 0;
    const stars = form.querySelectorAll('.star-input button');
    // Yıldızları seçilen puana kadar boya
    const paint = v => stars.forEach(s => s.classList.toggle('on', +s.dataset.v <= v));
    stars.forEach(s => {
      s.addEventListener('click', () => { rating = +s.dataset.v; paint(rating); });
      s.addEventListener('mouseenter', () => paint(+s.dataset.v));
    });
    form.querySelector('.star-input').addEventListener('mouseleave', () => paint(rating));
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      // Yıldız seçilmeden gönderilemez
      if (!rating) { onError(new Error('Önce yıldız seçin')); return; }
      // Değerlendirmeyi gönder
      try { await apiPost('/reviews', { trade_offer_id: +b.dataset.review, rating, comment: form.elements.comment.value }); onDone(); }
      catch (err) { onError(err); }
    });
  });
}

// Puanı yıldızlarla gösterir: ★★★★☆ 4.2 (5)
function starsText(avg, count) {
  if (avg == null) return '<span class="muted">Henüz değerlendirme yok</span>';
  // Ortalama puanı yuvarlayıp dolu/boş yıldız olarak göster
  const full = Math.round(avg);
  return `<span class="stars">${'★'.repeat(full)}${'☆'.repeat(5 - full)}</span> <b>${avg}</b> <small class="muted">(${count})</small>`;
}
