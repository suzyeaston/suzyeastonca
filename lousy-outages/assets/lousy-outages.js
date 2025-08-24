(function(){
  var container = document.getElementById('lousy-outages');
  if(!container) return;
  var tbody = container.querySelector('tbody');
  var tickerEl = container.querySelector('.ticker');
  var tickerMsgs = [], tickerIdx = 0, tickerTimer;
  function updateTicker(){
    if(!tickerEl) return;
    if(!tickerMsgs.length){ tickerEl.textContent=''; return; }
    tickerEl.textContent = tickerMsgs[tickerIdx];
    tickerIdx = (tickerIdx + 1) % tickerMsgs.length;
  }
  function setTicker(msgs){
    tickerMsgs = msgs;
    tickerIdx = 0;
    clearInterval(tickerTimer);
    updateTicker();
    if(msgs.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches){
      tickerTimer = setInterval(updateTicker,3000);
    }
  }
  function render(data){
    Object.keys(data).forEach(function(id){
      var row = tbody.querySelector('tr[data-id="'+id+'"]');
      if(!row) return;
      var statusCell = row.querySelector('.status');
      statusCell.textContent = data[id].status;
      statusCell.className = 'status '+data[id].status;
      row.querySelector('.msg').textContent = data[id].message;
    });
    var messages = Object.values(data).filter(function(s){ return s.message; }).map(function(s){ return s.message; });
    setTicker(messages);
    var timeSpan = container.querySelector('.last-updated span');
    if(timeSpan){ timeSpan.textContent = new Date().toUTCString(); }
  }
  function update(){
    fetch(LousyOutages.endpoint).then(function(r){ return r.json(); }).then(render);
  }
  var coin = container.querySelector('.coin-btn');
  if(coin){
    coin.addEventListener('click', function(){
      if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
        container.classList.add('spinning');
        setTimeout(function(){ container.classList.remove('spinning'); },1000);
      }
      if(window.AudioContext){
        var ctx = new AudioContext();
        var osc = ctx.createOscillator();
        osc.frequency.value = 880;
        osc.connect(ctx.destination);
        osc.start();
        setTimeout(function(){ osc.stop(); ctx.close(); },150);
      }
      update();
    });
  }
  setInterval(update,60000);
  update();
})();
