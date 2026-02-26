@extends('layouts.app')

@section('content')
<main class="">

  <div class="card" style="margin-bottom:15px;">
    <div style="font-weight:800; font-size:18px;">
      🏗 Будівельні проекти
    </div>
  </div>

  <div id="constructionProjectsContainer"></div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function(){

  fetch('/api/sales-projects')
    .then(r => r.json())
    .then(projects => {

      const container = document.getElementById('constructionProjectsContainer');
      container.innerHTML = '';

      projects.forEach(p => {

        const card = document.createElement('div');
        card.className = 'card';
        card.style.marginBottom = '12px';
        card.style.cursor = 'pointer';

        card.innerHTML = `
        <div class="project-header" style="display:flex; justify-content:space-between;">
            <div style="font-weight:700;">${p.client_name}</div>
            <div style="opacity:.6; font-size:12px;">${p.created_at}</div>
        </div>

        <div class="project-body" style="display:none; margin-top:12px; border-top:1px solid #ffffff20; padding-top:12px;">

            <input class="btn" placeholder="Посилання на Telegram групу" style="width:100%; margin-bottom:8px;">

            <input class="btn" placeholder="Інвертор" style="width:100%; margin-bottom:8px;">
            <input class="btn" placeholder="BMS" style="width:100%; margin-bottom:8px;">

            <!-- АКБ + Кількість -->
            <div style="display:flex; gap:8px;">
            <input class="btn" placeholder="АКБ (назва)" style="flex:2; width:70%;">
            <input type="number" class="btn" placeholder="К-сть" style="flex:1; margin-left:2%; width:30%;">
            </div>

            <!-- Панелі + Кількість -->
            <div style="display:flex; gap:8px;">
            <input class="btn" placeholder="ФEM (назва)" style="flex:2; width:70%;">
            <input type="number" class="btn" placeholder="К-сть" style="flex:1; margin-left:2%; width:30%;">
            </div>

            <input class="btn" placeholder="Електрик" style="width:100%; margin-bottom:8px;">
            <input class="btn" placeholder="Монтажна бригада" style="width:100%; margin-bottom:8px;">

            <div style="margin-top:10px; margin-bottom:15px; text-align:center; font-weight:600;">Недоліки</div>
            <textarea class="btn" placeholder="Опис недоліків..." style="width:100%; height:70px; margin-bottom:8px;"></textarea>

            <input type="file" accept="image/*" style="width:100%; margin-bottom:10px;">

            <button class="btn" style="width:100%; margin-bottom:8px;">
            💾 Зберегти
            </button>

            <button class="btn close-project-btn" 
                    data-id="${p.id}"
                    style="width:100%; background:#7a1c1c;">
            🔒 Закрити проект
            </button>

        </div>
        `;

        // toggle
        card.querySelector('.project-header').addEventListener('click', function(){
          const body = card.querySelector('.project-body');
          body.style.display = body.style.display === 'none' ? 'block' : 'none';
        });

        container.appendChild(card);
      });

    });

});

// кнопка закриття (поки просто confirm)
document.addEventListener('click', function(e){
  if(!e.target.classList.contains('close-project-btn')) return;

  const id = e.target.dataset.id;

  if(confirm('Закрити цей проект?')){
    console.log('Закриваємо проект', id);
    // тут потім буде fetch на API
  }
});
</script>

@include('partials.nav.bottom')
@endsection