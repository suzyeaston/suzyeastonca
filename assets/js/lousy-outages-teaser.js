(function(){
  const container = document.getElementById('lousy-outages-teaser');
  if(!container) return;
  const grid = container.querySelector('.providers');
  const names = { github:'GitHub', slack:'Slack', cloudflare:'Cloudflare', openai:'OpenAI', aws:'AWS', azure:'Azure', gcp:'GCP' };
  function statusClass(s){
    if(s === 'operational') return 'status-up';
    if(s === 'major_outage') return 'status-down';
    return 'status-warn';
  }
  function render(data){
    grid.innerHTML='';
    Object.keys(data).slice(0,6).forEach(id => {
      const state = data[id];
      const card = document.createElement('a');
      card.className = 'card';
      card.href = '/lousy-outages/';
      const dot = document.createElement('span');
      dot.className = 'dot ' + statusClass(state.status);
      card.appendChild(dot);
      const name = document.createElement('span');
      name.textContent = names[id] || id;
      card.appendChild(name);
      grid.appendChild(card);
    });
  }
  async function update(){
    try {
      const res = await fetch('/wp-json/lousy-outages/v1/status');
      const data = await res.json();
      render(data);
    } catch(e) {}
  }
  update();
  setInterval(update, 60000);
})();
