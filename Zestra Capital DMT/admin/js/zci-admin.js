(function(){
  console.log('ZCI Admin JS loaded');
  function qsel(id){ return document.getElementById(id); }
  var input = qsel('zci-base-search');
  var slugInput = qsel('zci-base-slug');
  var list = qsel('zci-base-results');
  if(!input || !slugInput || !list){ return; }

  function render(items){
    list.innerHTML = '';
    items.forEach(function(it, idx){
      var li = document.createElement('li');
      li.style.padding = '6px 8px';
      li.style.border = '1px solid #e5e7eb';
      li.style.borderTop = idx === 0 ? '1px solid #e5e7eb' : '0';
      li.style.cursor = 'pointer';
      li.textContent = it.display_name + ' (' + it.slug + ')';
      li.onclick = function(){ slugInput.value = it.slug; list.innerHTML = ''; };
      list.appendChild(li);
    });
  }

  var timer = null;
  input.addEventListener('input', function(){
    var q = input.value.trim();
    if(q.length < 2){ list.innerHTML = ''; return; }
    clearTimeout(timer);
    timer = setTimeout(function(){
      var url = (window.zciAdmin ? zciAdmin.restUrl : '') + '/search-indicators?q=' + encodeURIComponent(q);
      console.log('Admin search URL:', url);
      fetch(url, { headers: { 'X-WP-Nonce': (window.zciAdmin ? zciAdmin.nonce : '') }})
        .then(function(r){ 
          console.log('Admin search response status:', r.status);
          return r.json(); 
        })
        .then(function(j){
          console.log('Admin search results:', j);
          render(j.items || []);
        })
        .catch(function(e){ 
          console.error('Admin search error:', e);
          list.innerHTML = '<li style="color:red;">Search failed. Check console for details.</li>';
        });
    }, 250);
  });
})();


