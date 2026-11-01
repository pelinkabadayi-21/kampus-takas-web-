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
    links.push([`${PAGES}hesabim.html`, '👤 ' + u.full_name.split(' ')[0]]);
    if (u.role === 'yonetici') links.push([`${PAGES}yonetim.html`, '🛡️ Yönetim']);
  } else {
    links.push([`${PAGES}giris.html`, 'Giriş'], [`${PAGES}kayit.html`, 'Kayıt Ol']);
  }
  nav.innerHTML = links.map(([href, text]) => `<a href="${href}">${esc(text)}</a>`).join('')
    + (u ? '<a href="#" id="logout-link">Çıkış</a>' : '');
  document.getElementById('logout-link')?.addEventListener('click', e => { e.preventDefault(); logout(); });
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
