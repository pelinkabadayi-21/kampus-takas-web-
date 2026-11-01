document.addEventListener('DOMContentLoaded', async () => {
  let me;
  try { me = await requireLogin(); } catch (e) { return; }
  const box = document.getElementById('offers');
  const notice = document.getElementById('notice');
  let tab = new URLSearchParams(location.search).get('sekme') === 'giden' ? 'giden' : 'gelen';
  let data;

  const EMPTY = {
    gelen: 'İlanlarınıza henüz teklif gelmedi.',
    giden: 'Henüz teklif vermediniz. <a href="ilanlar.html">İlanlara göz atın →</a>',
  };

  function draw() {
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    const list = tab === 'gelen' ? data.received : data.sent;
    // Önce aksiyon bekleyenler: beklemede ve devam eden takaslar
    const order = { beklemede: 0, kabul: 1, tamamlandi: 2, red: 3, iptal: 4 };
    list.sort((a, b) => order[a.stage] - order[b.stage] || b.id - a.id);
    box.innerHTML = list.map(o => offerCard(o, me)).join('') || `<p class="muted">${EMPTY[tab]}</p>`;
  }

  async function load() {
    try {
      data = (await apiGet('/offers')).data;
      const active = l => l.filter(o => o.stage === 'beklemede' || o.stage === 'kabul').length;
      document.getElementById('count-gelen').textContent = active(data.received) || '';
      document.getElementById('count-giden').textContent = active(data.sent) || '';
      draw();
      refreshCounts();
    } catch (e) { box.innerHTML = `<p class="error-box">${esc(e.message)}</p>`; }
  }

  document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
    tab = t.dataset.tab; history.replaceState(null, '', '?sekme=' + tab); draw();
  }));
  bindOfferActions(box, () => { notice.innerHTML = '<p class="success-box">✔ İşlem tamamlandı</p>'; load(); },
                        err => { notice.innerHTML = `<p class="error-box">${esc(err.message)}</p>`; });
  load();
});
