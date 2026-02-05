// public/js/header.js
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const burgerBtn  = document.getElementById('burgerBtn');
    const burgerMenu = document.getElementById('burgerMenu');

    if (!burgerBtn || !burgerMenu) return;

    const openMenu = () => burgerMenu.classList.remove('hidden');
    const closeMenu = () => burgerMenu.classList.add('hidden');
    const toggleMenu = () => burgerMenu.classList.toggle('hidden');

    // toggle by button
    burgerBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleMenu();
    });

    // click outside closes
    document.addEventListener('click', (e) => {
      if (burgerMenu.classList.contains('hidden')) return;

      const insideMenu = burgerMenu.contains(e.target);
      const onButton = burgerBtn.contains(e.target);

      if (!insideMenu && !onButton) closeMenu();
    });

    // Esc closes
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeMenu();
    });
  });
})();
