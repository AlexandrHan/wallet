document.addEventListener('DOMContentLoaded', () => {
  // burger menu
  const burgerBtn = document.getElementById('burgerBtn');
  const burgerMenu = document.getElementById('burgerMenu');

  burgerBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    burgerMenu?.classList.toggle('hidden');
  });

  document.addEventListener('click', () => {
    if (burgerMenu && !burgerMenu.classList.contains('hidden')) {
      burgerMenu.classList.add('hidden');
    }
  });

  // optional: segmented UI (тільки підсвітка)
  const btnH = document.getElementById('view-h');
  const btnK = document.getElementById('view-k');

  const setOwnerUI = (owner) => {
    btnH?.classList.toggle('active', owner === 'hlushchenko');
    btnK?.classList.toggle('active', owner === 'kolisnyk');
  };

  btnH?.addEventListener('click', () => setOwnerUI('hlushchenko'));
  btnK?.addEventListener('click', () => setOwnerUI('kolisnyk'));

  // default
  const actor = window.AUTH_USER?.actor;
  setOwnerUI(actor === 'kolisnyk' ? 'kolisnyk' : 'hlushchenko');
});
