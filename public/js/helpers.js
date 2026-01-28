function fmt(n) {
  return Number(n || 0).toLocaleString('uk-UA');
}

function isToday(dateStr) {
  const today = new Date().toISOString().slice(0, 10);
  return dateStr === today;
}

function checkOnline() {
  if (navigator.onLine) return true;
  alert('❌ Немає інтернету');
  return false;
}

const CURRENCY_SYMBOLS = { UAH:'₴', USD:'$', EUR:'€' };
