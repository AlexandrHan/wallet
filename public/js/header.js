(function () {
  'use strict';

  function onReady(fn){
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  onReady(() => {
    const burgerBtn  = document.getElementById('burgerBtn');
    const burgerMenu = document.getElementById('burgerMenu');

    // якщо елементів нема — тихо виходимо
    if (!burgerBtn || !burgerMenu) return;

    const openMenu = () => {
      burgerMenu.classList.remove('hidden');
      burgerBtn.setAttribute('aria-expanded', 'true');
    };

    const closeMenu = () => {
      burgerMenu.classList.add('hidden');
      burgerBtn.setAttribute('aria-expanded', 'false');
    };

    const toggleMenu = () => {
      burgerMenu.classList.contains('hidden') ? openMenu() : closeMenu();
    };

    // початковий стан
    burgerBtn.setAttribute('aria-expanded', burgerMenu.classList.contains('hidden') ? 'false' : 'true');

    burgerBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleMenu();
    });

    // клік всередині меню не закриває
    burgerMenu.addEventListener('click', (e) => e.stopPropagation());

    // клік поза меню закриває
    document.addEventListener('click', () => closeMenu());

    // ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeMenu();
    });
  });
})();
