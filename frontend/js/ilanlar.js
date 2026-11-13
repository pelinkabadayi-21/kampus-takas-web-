// İlanlar sayfası: arama, kategori/tür/fiyat filtresi, sıralama ve sayfalama
const PAGE_SIZE = 12;
let offset = 0;

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('filters');
  const params = new URLSearchParams(location.search);
  // Adresteki filtreleri forma doldur (link paylaşılınca aynı sonuç çıksın)
  for (const [k, v] of params) if (form.elements[k]) form.elements[k].value = v;

  try {
    // Kategori seçim kutusunu doldur
    const cats = await apiGet('/categories');
    const sel = form.elements.category_id;
    sel.innerHTML = '<option value="">Tüm kategoriler</option>' +
      cats.data.map(c => `<option value="${c.id}">${esc(c.name)} (${c.listing_count})</option>`).join('');
    if (params.get('category_id')) sel.value = params.get('category_id');
  } catch (e) { /* kategori yüklenemezse liste yine de denensin */ }

  // Filtre değişince baştan yükle
  form.addEventListener('submit', e => { e.preventDefault(); offset = 0; load(); });
  form.addEventListener('change', () => { offset = 0; load(); });
  // "Daha fazla" butonu: sonraki 12 ilanı ekle
  document.getElementById('more').addEventListener('click', () => { offset += PAGE_SIZE; load(true); });
  load();
});

// Filtreleri okuyup ilanları sunucudan getirir ve kart olarak listeler.
async function load(append = false) {
  const form = document.getElementById('filters');
  const grid = document.getElementById('grid');
  const info = document.getElementById('result-info');
  // Formdaki filtreleri al
  const filters = Object.fromEntries(new FormData(form));

  // Filtreleri adres çubuğuna yaz (sayfa yenilenmeden)
  history.replaceState(null, '', '?' + new URLSearchParams(Object.entries(filters).filter(([, v]) => v)));
  if (!append) grid.innerHTML = '<p class="muted">Yükleniyor…</p>';

  try {
    // Filtreli ilanları getir
    const r = await apiGet('/listings', { ...filters, limit: PAGE_SIZE, offset });
    const html = r.items.map(i => listingCard(i)).join('');
    // Daha fazla ise ekle, değilse yeniden yaz
    grid.innerHTML = append ? grid.innerHTML + html : (html || '<p class="muted">Bu filtrelere uygun ilan bulunamadı.</p>');
    info.textContent = `${r.total} ilan bulundu`;
    // Hepsi gösterildiyse "Daha fazla" butonunu gizle
    document.getElementById('more').hidden = offset + PAGE_SIZE >= r.total;
  } catch (e) {
    grid.innerHTML = `<p class="error-box">İlanlar yüklenemedi: ${esc(e.message)}</p>`;
  }
}

