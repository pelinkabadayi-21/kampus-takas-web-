// Backend API adresi, sayfanın açıldığı adresten hesaplanır; böylece site hem XAMPP'te
// (http://localhost/kampus-takas-web-/frontend/) hem canlı sunucuda (https://…onrender.com/frontend/)
// hiçbir ayar değiştirmeden çalışır.  …/frontend/… -> …/backend/public/api
const API_BASE = location.origin + location.pathname.split('/frontend/')[0] + '/backend/public/api';
