/* MOSAIC · one musical arrangement, video tiles + instrument lanes.
 * Pure layout data for an iMovie-like visual timeline, not an audio engine.
 */
(function(root,factory){
  const api=factory();
  if(typeof module==='object'&&module.exports)module.exports=api;
  if(root)root.MosaicTimeline=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
  'use strict';
  const clamp=(n,min,max)=>Math.max(min,Math.min(max,Number(n)||0));
  function segments(seconds,total,max=24,startAt=0){
    const period=Number(seconds),length=Number(total),offset=Math.max(0,Number(startAt)||0);
    if(!Number.isFinite(period)||period<=0||!Number.isFinite(length)||length<=0)return [];
    const count=Math.ceil(Math.max(0,length-offset)/period),display=Math.min(count,max),segments=[];
    for(let i=0;i<display;i++){
      const start=offset+i*period,until=i===display-1&&count>max?length:Math.min(length,start+period);
      segments.push({start,seconds:until-start,skipped:i===display-1&&count>max?count-display:0});
    }
    return segments;
  }
  function makeLanes(video,notes,total){
    const duration=Number(total)||0;
    if(duration<=0)return [];
    const takes=Array.from(video||[]).filter(clip=>Number.isInteger(clip.index)).sort((a,b)=>a.index-b.index)
      .map(clip=>({
        id:'take-'+clip.index,
        type:clip.audioOnly?'audio':'video',
        name:'TAKE '+String(clip.index+1).padStart(2,'0'),
        subtitle:clip.audioOnly?'MIC / AUDIO':'CAMERA',
        muted:!!clip.muted,seconds:Number(clip.seconds)||0,
        segments:segments(clip.seconds,duration),notes:[],index:clip.index,
      }));
    const instruments=Array.from(notes||[]).map((layer,i)=>({
      id:'instrument-'+String(layer.id||i),type:layer.kind==='drums'?'rhythm':'instrument',
      name:String(layer.name||layer.kind.toUpperCase()).slice(0,60),
      subtitle:String(layer.voice||layer.kind).replace(/-/g,' ').toUpperCase(),
      kind:layer.kind,muted:!!layer.muted,seconds:Number(layer.seconds)||0,
      segments:segments(layer.seconds,duration,24,layer.startAt||0),
      notes:(Array.isArray(layer.events)?layer.events:[]).slice(0,64)
        .filter(e=>Number.isFinite(e.t)&&e.t>=0&&e.t<layer.seconds)
        .map(e=>({left:clamp(100*e.t/layer.seconds,0,99),top:clamp(80-((e.note||60)%12)*5,8,85)})),
      layerId:layer.id,
    }));
    return [...takes,...instruments];
  }
  return Object.freeze({segments,makeLanes});
});
