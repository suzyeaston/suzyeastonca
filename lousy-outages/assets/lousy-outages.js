(function(){
  function update(){
    fetch(LousyOutages.endpoint).then(r=>r.json()).then(function(data){
      var container=document.getElementById('lousy-outages');
      if(!container) return;
      var tbody=container.querySelector('tbody');
      Object.keys(data).forEach(function(id){
        var row=tbody.querySelector('tr[data-id="'+id+'"]');
        if(!row) return;
        var statusCell=row.querySelector('.status');
        statusCell.textContent=data[id].status;
        statusCell.className='status '+data[id].status;
        row.querySelector('.msg').textContent=data[id].message;
      });
      var timeSpan=container.querySelector('.last-updated span');
      if(timeSpan){ timeSpan.textContent=new Date().toUTCString(); }
    });
  }
  setInterval(update,60000);
  update();
})();
