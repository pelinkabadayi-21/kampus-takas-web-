// Kayıt sayfası: parola kurallarını canlı gösterir, formu kontrol edip sunucuya gönderir, hataları alanların altına yazar.
document.addEventListener('DOMContentLoaded', async () => {
  // Giriş yapmışsa kayıt sayfasına gerek yok
  if (await currentUser()) { location.href = 'hesabim.html'; return; }

  const form = document.getElementById('register-form');
  const pw = form.elements.password;
  const meter = document.getElementById('pw-rules');

  // Parola kurallarını yazarken göster (aynı kurallar sunucuda da kontrol edilir)
  // Parola kuralları: her biri bir kontrol fonksiyonu ve açıklama
  const rules = [
    [v => v.length >= 8, 'En az 8 karakter'],
    [v => /\p{L}/u.test(v), 'En az bir harf'],
    [v => /\d/.test(v), 'En az bir rakam'],
  ];
  // Parola kurallarını (8 karakter, harf, rakam) yazdıkça ✔ veya ○ olarak gösterir.
  // Parola yazıldıkça sağlanan kuralları ✔ ile işaretle
  const draw = () => meter.innerHTML = rules.map(([ok, t]) =>
    `<li class="${ok(pw.value) ? 'ok' : ''}">${ok(pw.value) ? '✔' : '○'} ${t}</li>`).join('');
  pw.addEventListener('input', draw); draw();

  form.addEventListener('submit', async e => {
    e.preventDefault();
    // Formdaki tüm alanları bir nesneye çevir
    const data = Object.fromEntries(new FormData(form));
    const local = {};
    // Sunucuya gitmeden önce tarayıcıda basit kontroller
    if (data.password !== data.password_confirm) local.password_confirm = 'Parolalar eşleşmiyor';
    if (!rules.every(([ok]) => ok(data.password))) local.password = 'Parola kurallarını sağlamıyor';
    showFieldErrors(form, local);
    // Hata varsa gönderme
    if (Object.keys(local).length) return;

    const btn = form.querySelector('button');
    btn.disabled = true;
    try {
      // Kaydı gönder; başarılıysa giriş sayfasına git
      await apiPost('/auth/register', data);
      location.href = 'giris.html?kayit=1';
    } catch (err) {
      // Sunucudan gelen alan hatalarını formda göster
      showFieldErrors(form, err.errors);
      document.getElementById('form-msg').innerHTML = `<p class="error-box">${esc(err.message)}</p>`;
    } finally {
      btn.disabled = false;
    }
  });
});
