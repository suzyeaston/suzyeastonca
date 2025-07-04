document.addEventListener('DOMContentLoaded',function(){
  const title=document.getElementById('home-title');
  if(!title) return;
  let pos=0;
  setInterval(()=>{
    pos=(pos+1)%300;
    title.style.backgroundPosition=`${pos}%`;
  },60);
});
