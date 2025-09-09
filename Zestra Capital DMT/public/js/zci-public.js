(function(){
  console.log('ZCI Public JS loaded');
  
  function initCharts(){
    var wrappers = document.querySelectorAll('.zci-chart-wrapper');
    wrappers.forEach(function(wrapper){
      var canvas = wrapper.querySelector('canvas');
      if(!canvas){ return; }
      var indicator = wrapper.getAttribute('data-indicator') || '';
      var countries = wrapper.getAttribute('data-countries') || '';
      var indicators = wrapper.getAttribute('data-indicators') || '';
      var range = wrapper.getAttribute('data-range') || '1y';
      var type = wrapper.getAttribute('data-type') || 'line';

      var params = [];
      if(indicators){ params.push('indicators=' + encodeURIComponent(indicators)); }
      if(indicator){ params.push('indicator=' + encodeURIComponent(indicator)); }
      params.push('countries=' + encodeURIComponent(countries));
      params.push('range=' + encodeURIComponent(range));
      var restBase = (zciPublic && zciPublic.restUrl ? zciPublic.restUrl : '');
      var url = restBase + '/chart?' + params.join('&');

      fetch(url, {})
        .then(function(r){ return r.json(); })
        .then(function(json){
          if(!json || !json.labels || !json.datasets){ throw new Error('Invalid payload'); }
          var ctx = canvas.getContext('2d');
          var palette = [
            'rgba(54, 162, 235, 1)','rgba(255, 99, 132, 1)','rgba(75, 192, 192, 1)',
            'rgba(255, 159, 64, 1)','rgba(153, 102, 255, 1)','rgba(201, 203, 207, 1)'
          ];
          var settings = (window.zciSettings || {});
          var legendPosition = settings.legend_position || 'top';
          var lineTension = typeof settings.line_tension === 'number' ? settings.line_tension : 0.2;
          var lineWidth = settings.line_width || 2;
          var showGrid = settings.show_grid !== 0 && settings.show_grid !== false;
          var chart = new Chart(ctx, {
            type: type,
            data: {
              labels: json.labels,
              datasets: json.datasets.map(function(ds, i){
                var color = palette[i % palette.length];
                return Object.assign({
                  borderColor: color,
                  backgroundColor: color.replace('1)', '0.2)'),
                  tension: lineTension,
                  pointRadius: 2,
                  pointHoverRadius: 4,
                  borderWidth: lineWidth,
                  clip: { left: 0, top: 0, right: 0, bottom: 0 },
                }, ds);
              })
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { display: true, position: legendPosition, labels: { usePointStyle: true, boxWidth: 10, color: '#374151' } },
                title: { display: false },
                tooltip: { mode: 'index', intersect: false }
              },
              interaction: { mode: 'nearest', intersect: false },
              scales: {
                x: { display: true, ticks: { color: '#6b7280' }, grid: { color: 'rgba(17,24,39,0.05)', display: showGrid } },
                y: { display: true, ticks: { color: '#6b7280' }, grid: { color: 'rgba(17,24,39,0.05)', display: showGrid } }
              }
            }
          });

          var updated = wrapper.querySelector('.zci-chart-updated');
          var showLastUpdated = settings.show_last_updated !== 0 && settings.show_last_updated !== false;
          if(updated){ updated.style.display = showLastUpdated ? 'block' : 'none'; }
          if(updated && showLastUpdated && json.last_updated){ updated.textContent = 'Last data updated: ' + json.last_updated; }
        })
        .catch(function(){
          var fallback = wrapper.querySelector('.zci-chart-fallback');
          if(fallback){ fallback.style.display = 'block'; }
        });
    });
    initCompare();
  }

  function initCompare(){
    console.log('Initializing compare functionality');
    var builders = document.querySelectorAll('.zci-compare-builder');
    console.log('Found compare builders:', builders.length);
    builders.forEach(function(root){
      var search = root.querySelector('.zci-compare-search');
      var results = root.querySelector('.zci-compare-results');
      var selected = root.querySelector('.zci-compare-selected');
      var canvas = root.querySelector('canvas');
      var type = root.getAttribute('data-type') || 'line';
      var range = root.getAttribute('data-range') || '1y';
      var palette = [
        'rgba(54, 162, 235, 1)','rgba(255, 99, 132, 1)','rgba(75, 192, 192, 1)',
        'rgba(255, 159, 64, 1)','rgba(153, 102, 255, 1)','rgba(201, 203, 207, 1)'
      ];
      var settings = (window.zciSettings || {});
      var legendPosition = settings.legend_position || 'top';
      var lineTension = typeof settings.line_tension === 'number' ? settings.line_tension : 0.2;
      var lineWidth = settings.line_width || 2;
      var showGrid = settings.show_grid !== 0 && settings.show_grid !== false;

      var chosen = [];
      function fetchAndRender(){
        if(chosen.length === 0){ return; }
        var restBase = (zciPublic && zciPublic.restUrl ? zciPublic.restUrl : '');
        var url = restBase + '/chart?indicators=' + encodeURIComponent(chosen.join(',')) + '&range=' + encodeURIComponent(range);
        fetch(url, {})
          .then(function(r){ return r.json(); })
          .then(function(json){
            if(!json || !json.labels){ return; }
            var ctx = canvas.getContext('2d');
            if(root._chart){ root._chart.destroy(); }
            root._chart = new Chart(ctx, {
              type: type,
              data: {
                labels: json.labels,
                datasets: (json.datasets||[]).map(function(ds, i){
                  var color = palette[i % palette.length];
                  return Object.assign({
                    borderColor: color,
                    backgroundColor: color.replace('1)', '0.2)'),
                    tension: lineTension,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    borderWidth: lineWidth,
                    clip: { left: 0, top: 0, right: 0, bottom: 0 },
                  }, ds);
                })
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: legendPosition } },
                scales: {
                  x: { grid: { display: showGrid } },
                  y: { grid: { display: showGrid } }
                }
              }
            });
          });
      }

      function renderSelected(){
        selected.innerHTML = '';
        chosen.forEach(function(slug, i){
          var tag = document.createElement('span');
          tag.textContent = slug + ' ✕';
          tag.style.marginRight = '8px';
          tag.style.cursor = 'pointer';
          tag.onclick = function(){ chosen.splice(i,1); renderSelected(); fetchAndRender(); };
          selected.appendChild(tag);
        });
      }

      var timer = null;
      console.log('Adding event listener to search input');
      search.addEventListener('input', function(){
        var q = search.value.trim();
        if(q.length < 2){ results.innerHTML=''; return; }
        clearTimeout(timer);
        timer = setTimeout(function(){
          var restBase = (zciPublic && zciPublic.restUrl ? zciPublic.restUrl : '');
          var url = restBase + '/search-indicators?q=' + encodeURIComponent(q);
          console.log('Compare search URL:', url);
          fetch(url, {})
            .then(function(r){ 
              console.log('Compare search response status:', r.status);
              return r.json(); 
            })
            .then(function(j){
              console.log('Compare search results:', j);
              results.innerHTML = '';
              if (j.items && j.items.length > 0) {
                j.items.forEach(function(it){
                  var li = document.createElement('li');
                  li.textContent = it.display_name + ' ('+ it.slug +')';
                  li.style.cursor = 'pointer';
                  li.onclick = function(){ if(chosen.indexOf(it.slug)===-1){ chosen.push(it.slug); renderSelected(); fetchAndRender(); } results.innerHTML=''; search.value=''; };
                  results.appendChild(li);
                });
              } else {
                results.innerHTML = '<li style="color:#666;">No indicators found. Add some from admin panel first.</li>';
              }
            })
            .catch(function(e){
              console.error('Compare search error:', e);
              results.innerHTML = '<li style="color:red;">Search failed. Check console for details.</li>';
            });
        }, 250);
      });
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initCharts);
  } else {
    initCharts();
  }
})();


