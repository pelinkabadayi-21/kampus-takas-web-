// İlanlar sayfası: arama, kategori/tür/fiyat filtresi, sıralama ve sayfalama
const PAGE_SIZE = 12;
let offset = 0;

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('filters');
  const params = new URLSearchParams(location.search);
  for (const [k, v] of params) if (form.elements[k]) form.elements[k].value = v;

  try {
    const cats = await apiGet('/categories');
    const sel = form.elements.category_id;
    sel.innerHTML = '<option value="">Tüm kategoriler</option>' +
      cats.data.map(c => `<option value="${c.id}">${esc(c.name)} (${c.listing_count})</option>`).join('');
    if (params.get('category_id')) sel.value = params.get('category_id');
  } catch (e) { /* kategori yüklenemezse liste yine de denensin */ }

  form.addEventListener('submit', e => { e.preventDefault(); offset = 0; load(); });
  form.addEventListener('change', () => { offset = 0; load(); });
  document.getElementById('more').addEventListener('click', () => { offset += PAGE_SIZE; load(true); });
  load();
});

async function load(append = false) {
  const form = document.getElementById('filters');
  const grid = document.getElementById('grid');
  const info = document.getElementById('result-info');
  const filters = Object.fromEntries(new FormData(form));

  history.replaceState(null, '', '?' + new URLSearchParams(Object.entries(filters).filter(([, v]) => v)));
  if (!append) grid.innerHTML = '<p class="muted">Yükleniyor…</p>';

  try {
    const r = await apiGet('/listings', { ...filters, limit: PAGE_SIZE, offset });
    const html = r.items.map(card).join('');
    grid.innerHTML = append ? grid.innerHTML + html : (html || '<p class="muted">Bu filtrelere uygun ilan bulunamadı.</p>');
    info.textContent = `${r.total} ilan bulundu`;
    document.getElementById('more').hidden = offset + PAGE_SIZE >= r.total;
  } catch (e) {
    grid.innerHTML = `<p class="error-box">İlanlar yüklenemedi: ${esc(e.message)}</p>`;
  }
}

function card(i) {
  return `
    <a class="listing-card" href="ilan.html?id=${i.id}">
      <div class="cover">${i.cover_image ? `<img src="${esc(i.cover_image)}" alt="">` : '📦'}</div>
      <div class="body">
        <span class="badge badge-${i.listing_type}">${TYPE_LABEL[i.listing_type]}</span>
        <h3>${esc(i.title)}</h3>
        <div class="price">${formatPrice(i)}</div>
        <small class="muted">${esc(i.category_name)} · ${COND_LABEL[i.item_condition]}</small>
      </div>
    </a>`;
}
