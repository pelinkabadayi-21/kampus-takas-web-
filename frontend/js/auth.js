// Oturum yardımcıları ve giriş durumuna göre değişen menü.
// NOT: Token JavaScript'te tutulmaz; tarayıcı HttpOnly çerezi kendisi gönderir.
// Buradaki rol kontrolleri sadece GÖRÜNÜM içindir; asıl kontrol sunucuda yapılır.

const ROOT = location.pathname.includes('/pages/') ? '../' : '';
const PAGES = ROOT ? '' : 'pages/';
let _me; // undefined = henüz sorulmadı, null = giriş yok

async function currentUser() {
  if (_me !== undefined) return _me;
  try { _me = (await apiGet('/auth/me')).user; }
  catch (e) { _me = null; }
  return _me;
}

/** Giriş yoksa giriş sayfasına yönlendirir, varsa kullanıcıyı döndürür. */
async function requireLogin() {
  const u = await currentUser();
  if (!u) {
    location.href = `${ROOT}pages/giris.html?next=${encodeURIComponent(location.pathname + location.search)}`;
    throw new Error('redirect');
  }
  return u;
}

async function logout() {
  try { await apiPost('/auth/logout'); } catch (e) {}
  _me = null;
  location.href = ROOT + 'index.html';
}

async function renderNav() {
  const nav = document.getElementById('main-nav');
  if (!nav) return;
  const u = await currentUser();
  const links = [
    [`${ROOT}index.html`, 'Ana Sayfa'],
    [`${PAGES}ilanlar.html`, 'İlanlar'],
  ];
  if (u) {
    links.push([`${PAGES}ilan-ver.html`, '＋ İlan Ver']);
    links.push([`${PAGES}mesajlar.html`, '💬 Mesajlar', 'messages']);
    links.push([`${PAGES}teklifler.html`, '🔄 Teklifler', 'pending_offers']);
    links.push([`${PAGES}bildirimler.html`, '🔔', 'notifications', 'Bildirimler']);
    links.push([`${PAGES}hesabim.html`, '👤 ' + u.full_name.split(' ')[0]]);
    if (u.role === 'yonetici') links.push([`${PAGES}yonetim.html`, '🛡️ Yönetim']);
  } else {
    links.push([`${PAGES}giris.html`, 'Giriş'], [`${PAGES}kayit.html`, 'Kayıt Ol']);
  }
  nav.innerHTML = links.map(([href, text, counter, title]) =>
      `<a href="${href}"${title ? ` title="${title}" aria-label="${title}"` : ''}>${esc(text)}${counter ? `<span class="nav-count" data-count="${counter}" hidden></span>` : ''}</a>`).join('')
    + (u ? '<a href="#" id="logout-link">Çıkış</a>' : '');
  document.getElementById('logout-link')?.addEventListener('click', e => { e.preventDefault(); logout(); });
  if (u) { refreshCounts(); setInterval(refreshCounts, 30000); }
}

/** Menüdeki okunmamış mesaj / bekleyen teklif / bildirim sayaçları (30 sn'de bir) */
async function refreshCounts() {
  if (document.hidden) return;
  try {
    const { data } = await apiGet('/notifications/counts');
    document.querySelectorAll('.nav-count').forEach(el => {
      const n = data[el.dataset.count] || 0;
      el.textContent = n > 99 ? '99+' : n;
      el.hidden = n === 0;
    });
  } catch (e) { /* oturum düşmüş olabilir; sessiz geç */ }
}

/** Form alanlarının altına sunucudan gelen hata mesajlarını yazar. */
function showFieldErrors(form, errors = {}) {
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
  form.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
  for (const [name, msg] of Object.entries(errors)) {
    const input = form.elements[name];
    if (!input) continue;
    input.classList.add('invalid');
    const box = input.closest('.field')?.querySelector('.field-error');
    if (box) box.textContent = msg;
  }
}

function safeNext(fallback) {
  const n = new URLSearchParams(location.search).get('next');
  // Sadece site içi adrese yönlendir (açık yönlendirme saldırısını önler)
  return n && n.startsWith('/') && !n.startsWith('//') ? n : fallback;
}

document.addEventListener('DOMContentLoaded', renderNav);

// Kullanıcı hatalı alanı düzeltmeye başlayınca o alanın hata mesajını temizle
document.addEventListener('input', e => {
  const el = e.target;
  if (!el.classList?.contains('invalid')) return;
  el.classList.remove('invalid');
  const box = el.closest('.field')?.querySelector('.field-error');
  if (box) box.textContent = '';
});
