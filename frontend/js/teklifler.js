// Teklifler sayfası: gelen ve gönderdiğim teklifleri sekmelerde listeler; kabul/red/geri çek/tamamla/değerlendir butonlarını bağlar.
document.addEventListener('DOMContentLoaded', async () => {
  let me;
  try { me = await requireLogin(); } catch (e) { return; }
  const box = document.getElementById('offers');
  const notice = document.getElementById('notice');
  // Hangi sekme açık: gelen veya giden teklifler
  let tab = new URLSearchParams(location.search).get('sekme') === 'giden' ? 'giden' : 'gelen';
  let data;

  const EMPTY = {
    gelen: 'İlanlarınıza henüz teklif gelmedi.',
    giden: 'Henüz teklif vermediniz. <a href="ilanlar.html">İlanlara göz atın →</a>',
  };

  // Seçili sekmedeki (gelen / gönderdiğim) teklifleri sıralayıp çizer.
  function draw() {
    // Aktif sekmeyi işaretle, o sekmenin listesini al
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    const list = tab === 'gelen' ? data.received : data.sent;
    // Önce aksiyon bekleyenler: beklemede ve devam eden takaslar
    // Önce bekleyenler, sonra kabul edilenler... şeklinde sırala
    const order = { beklemede: 0, kabul: 1, tamamlandi: 2, red: 3, iptal: 4 };
    list.sort((a, b) => order[a.stage] - order[b.stage] || b.id - a.id);
    // Teklif kartlarını çiz
    box.innerHTML = list.map(o => offerCard(o, me)).join('') || `<p class="muted">${EMPTY[tab]}</p>`;
  }

  // Teklifleri sunucudan alır ve sekme sayaçlarını günceller.
  async function load() {
    try {
      // Teklifleri sunucudan getir
      data = (await apiGet('/offers')).data;
      // Sekme başlıklarındaki sayılar: bekleyen + kabul edilen teklifler
      const active = l => l.filter(o => o.stage === 'beklemede' || o.stage === 'kabul').length;
      document.getElementById('count-gelen').textContent = active(data.received) || '';
      document.getElementById('count-giden').textContent = active(data.sent) || '';
      draw();
      // Menüdeki sayaçları da güncelle
      refreshCounts();
    } catch (e) { box.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; }
  }

  document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
    // Sekmeye tıklanınca listeyi değiştir
    tab = t.dataset.tab; history.replaceState(null, '', '?sekme=' + tab); draw();
  }));
  // Değerlendirme ve teklif butonlarını bağla; işlem bitince listeyi yenile
  bindReviewActions(box, () => { notice.innerHTML = '<p class="success-box">⭐ Değerlendirmeniz kaydedildi, teşekkürler!</p>'; load(); },
                         err => { notice.innerHTML = `<p class="error-box">${esc(err.message)}</p>`; });
  bindOfferActions(box, () => { notice.innerHTML = '<p class="success-box">✔ İşlem tamamlandı</p>'; load(); },
                        err => { notice.innerHTML = `<p class="error-box">${esc(err.message)}</p>`; });
  load();
});
