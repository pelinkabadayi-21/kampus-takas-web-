// Teklif kartı ve işlemleri: hem ilan sayfası hem teklifler sayfası kullanır.

const STAGE_LABEL = {
  beklemede:  ['⏳ Yanıt bekliyor', 'badge-status-rezerve'],
  kabul:      ['🤝 Kabul edildi · buluşma bekleniyor', 'badge-takas'],
  tamamlandi: ['✅ Tamamlandı', 'badge-status-tamamlandi'],
  red:        ['✖ Reddedildi', 'badge-status-kaldirildi'],
  iptal:      ['↩ İptal edildi', 'badge-status-kaldirildi'],
};

function offerSummary(o) {
  const parts = [];
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
  const iAmOwner = me.id == o.listing_owner_id;
  const other = iAmOwner ? { id: o.offerer_id, name: o.offerer_name } : { id: o.listing_owner_id, name: o.owner_name };
  const [label, cls] = STAGE_LABEL[o.stage] || [o.stage, ''];
  const btn = (action, text, style = 'btn-outline') => `<button class="btn btn-sm ${style}" data-offer="${o.id}" data-action="${action}">${text}</button>`;

  let actions = '';
  if (o.stage === 'beklemede' && iAmOwner) actions = btn('accept', '✔ Kabul et', '') + btn('reject', '✖ Reddet', 'btn-danger');
  if (o.stage === 'beklemede' && !iAmOwner) actions = btn('cancel', '↩ Geri çek', 'btn-danger');
  if (o.stage === 'kabul') actions = btn('complete', '✅ Takas tamamlandı', '') + btn('withdraw', 'Vazgeç', 'btn-danger');
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
    const b = e.target.closest('button[data-action]');
    if (!b || !confirm(CONFIRM[b.dataset.action])) return;
    b.disabled = true;
    try { await apiPut(`/offers/${b.dataset.offer}/${b.dataset.action}`); onDone(); }
    catch (err) { b.disabled = false; onError(err); }
  });
}
