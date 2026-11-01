document.addEventListener('DOMContentLoaded', async () => {
  if (await currentUser()) { location.href = safeNext('hesabim.html'); return; }
  if (new URLSearchParams(location.search).get('kayit') === '1') {
    document.getElementById('form-msg').innerHTML = '<p class="success-box">Kayıt başarılı! Şimdi giriş yapabilirsiniz.</p>';
  }

  const form = document.getElementById('login-form');
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = form.querySelector('button');
    const msg = document.getElementById('form-msg');
    btn.disabled = true; btn.textContent = 'Giriş yapılıyor…'; msg.innerHTML = '';
    try {
      await apiPost('/auth/login', Object.fromEntries(new FormData(form)));
      location.href = safeNext('hesabim.html');
    } catch (err) {
      msg.innerHTML = `<p class="error-box">${esc(err.message)}</p>`;
      form.elements.password.value = '';
      form.elements.password.focus();
    } finally {
      btn.disabled = false; btn.textContent = 'Giriş Yap';
    }
  });
});
