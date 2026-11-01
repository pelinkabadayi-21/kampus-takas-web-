// Backend ile konuşan tek yer. Sayfalar doğrudan fetch yazmaz, bu fonksiyonları kullanır.
async function apiGet(path) {
  const res = await fetch(API_BASE + path);
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'İstek başarısız');
  return data;
}
