const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class ClassList { constructor(el){ this.el=el; this.set=new Set((el.className||'').split(/\s+/).filter(Boolean)); } add(c){this.set.add(c); this.el.className=[...this.set].join(' ');} remove(c){this.set.delete(c); this.el.className=[...this.set].join(' ');} toggle(c,on){ on ? this.add(c) : this.remove(c); } contains(c){ return this.set.has(c); } }
class El { constructor(tag='div'){ this.tagName=tag; this.children=[]; this.attributes={}; this.dataset={}; this._className=''; this.textContent=''; this.parent=null; this.classList=new ClassList(this); } set className(v){ this._className=String(v); this.classList=new ClassList(this); } get className(){ return this._className; }
  append(...els){ els.forEach(e=>this.appendChild(e)); } appendChild(e){ e.parent=this; this.children.push(e); return e; } removeChild(e){ this.children=this.children.filter(x=>x!==e); e.parent=null; } get firstChild(){ return this.children[0]||null; } setAttribute(k,v){ this.attributes[k]=String(v); if(k==='id') this.id=String(v); if(k==='class'){this.className=String(v); this.classList=new ClassList(this);} } getAttribute(k){ return this.attributes[k]; }
  querySelector(sel){ return this.querySelectorAll(sel)[0]||null; } querySelectorAll(sel){ const out=[]; const match=(e)=> sel.startsWith('.') ? e.classList.contains(sel.slice(1)) : sel.startsWith('#') ? e.id===sel.slice(1) : e.tagName===sel; const walk=(e)=>{ if(match(e)) out.push(e); e.children.forEach(walk); }; this.children.forEach(walk); return out; }}
function makeContainer() { const c = new El('section'); c.setAttribute('id','lousy-outages-teaser'); c.classList.add('lo-home-teaser--clear'); c.dataset.loEndpoint = 'https://example.test/wp-json/lousy-outages/v1/summary'; c.dataset.loRefreshInterval='60000'; const light = new El('span'); light.className='lo-home-status-light'; light.classList=new ClassList(light); const sr = new El('span'); sr.className='screen-reader-text'; sr.classList=new ClassList(sr); light.append(sr); const screen = new El('div'); screen.className='lo-home-teaser__screen'; screen.classList=new ClassList(screen); const fallback = new El('div'); fallback.className='server-fallback'; fallback.classList=new ClassList(fallback); fallback.textContent='fallback stays'; screen.append(fallback); c.append(light, screen); return c; }
function load({ fetchImpl=()=>Promise.resolve({ok:true,json:()=>Promise.resolve({providers:[],fetched_at:'2026-07-19T10:00:00Z'})}), interval=(fn)=>1, hidden=false }={}) { const listeners={}; const container=makeContainer(); const doc={ hidden, createElement:(t)=>new El(t), addEventListener:(n,cb)=>{listeners[n]=cb;}, getElementById:(id)=>id==='lousy-outages-teaser'?container:null}; const sandbox={ window:{}, document:doc, location:{href:'https://example.test/'}, console, URL, Date, fetch:fetchImpl, setInterval:interval, clearInterval:()=>{} }; sandbox.window=sandbox; vm.runInNewContext(fs.readFileSync('assets/js/lousy-outages-teaser.js','utf8'), sandbox); return { teaser:sandbox.window.LousyOutagesTeaser, container, listeners, doc }; }
const payload = (providers) => ({ fetched_at:'2026-07-19T08:00:00Z', meta:{ fetchedAt:'2099-01-01T00:00:00Z' }, providers });

test('init fetches summary, not status, and success replaces fallback', async () => { const urls=[]; const {teaser,container}=load({fetchImpl:(u)=>{urls.push(u); return Promise.resolve({ok:true,json:()=>Promise.resolve(payload([{id:'cf',name:'Cloudflare',tile_kind:'outage',stateCode:'outage',incidents:[{title:'Edge down',status:'investigating',updatedAt:'2026-07-19T07:00:00Z'}]}]))});}}); teaser.init(container); await new Promise(setImmediate); assert.match(urls[0], /\/summary\?/); assert.doesNotMatch(urls[0], /\/status/); assert.equal(container.querySelector('.server-fallback'), null); assert.equal(container.querySelectorAll('.lo-home-alert--outage').length, 1); });
test('failed request preserves fallback and delayed notice; later success removes notice', async () => { let fail=true; const {teaser,container}=load({fetchImpl:()=> fail ? Promise.reject(new Error('x')) : Promise.resolve({ok:true,json:()=>Promise.resolve(payload([]))})}); teaser.init(container); await new Promise(setImmediate); assert.ok(container.querySelector('.server-fallback')); assert.ok(container.querySelector('.lo-home-delayed')); fail=false; container.dataset.loTeaserReady='0'; teaser.init(container); await new Promise(setImmediate); assert.equal(container.querySelector('.lo-home-delayed'), null); assert.equal(container.querySelector('.server-fallback'), null); assert.ok(container.classList.contains('lo-home-teaser--clear')); });
test('canonical tile kinds and lifecycle filtering are honored', () => { const {teaser}=load(); const items=teaser.currentItems(payload([{id:'ok',name:'OK',tile_kind:'operational',incidents:[]},{id:'o',name:'Out',tile_kind:'outage',incidents:[]},{id:'s',name:'Sig',tile_kind:'signal',incidents:[]},{id:'u',name:'Unk',tile_kind:'unknown',stateCode:'degraded',incidents:[]},{id:'m',name:'Man',tile_kind:'manual',stateCode:'degraded',incidents:[]},{id:'eta',name:'Eta',tile_kind:'outage',incidents:[{title:'Eta resolved',status:'investigating',eta:'resolved',impact:'minor'}]},{id:'done',name:'Done',tile_kind:'outage',incidents:[{title:'Done',status:'completed',impact:'minor'},{title:'Post',status:'postmortem',impact:'minor'}]}])); assert.equal(JSON.stringify(items.map(i=>[i.provider,i.type])), JSON.stringify([['Out','outage'],['Sig','signal']])); });
test('duplicates are deduplicated before five-item limit', () => { const {teaser}=load(); const providers=[{id:'dup',name:'Dup',tile_kind:'outage',incidents:[{title:'Same outage',status:'investigating',startedAt:'2026-07-19T09:00:00Z',updatedAt:'2026-07-19T09:10:00Z'},{title:'Same outage',status:'identified',startedAt:'2026-07-19T09:00:00Z',updatedAt:'2026-07-19T09:20:00Z'}]},...Array.from({length:4},(_,i)=>({id:`p${i}`,name:`P${i}`,tile_kind:'outage',incidents:[{title:`I${i}`,status:'investigating',updatedAt:`2026-07-19T0${i}:00:00Z`}]}))]; const items=teaser.currentItems(payload(providers)); assert.equal(items.length,5); assert.equal(items.filter(i=>i.provider==='Dup').length,1); });
test('polling has no duplicate timers/overlap and visibility refreshes at most once', async () => { let fetches=0,timers=0; let release; const pending=new Promise(r=>{release=r}); const {teaser,container,listeners}=load({fetchImpl:()=>{fetches++; return pending.then(()=>({ok:true,json:()=>Promise.resolve(payload([]))}));}, interval:()=>{timers++; return 1;}}); teaser.init(container); teaser.init(container); assert.equal(timers,1); listeners.visibilitychange(); assert.equal(fetches,1); release(); await new Promise(setImmediate); listeners.visibilitychange(); assert.equal(fetches,2); });

test('long-running AWS regional incident appears as current issue with official context', () => {
  const {teaser,container}=load();
  const providers=[
    {id:'teamviewer',name:'TeamViewer',tile_kind:'outage',incidents:[{title:'Connectivity issues',status:'investigating',updated_at:'2026-07-19T07:00:00Z'}]},
    {id:'cloudflare',name:'Cloudflare',tile_kind:'outage',incidents:[{title:'Dashboard errors',status:'identified',updated_at:'2026-07-19T06:00:00Z'}]},
    {id:'aws',name:'AWS',tile_kind:'outage',checked_at:'2026-07-19T10:00:00Z',incidents:[{display_title:'Multiple AWS services disrupted in UAE (ME-CENTRAL-1)',source_title:'Operational issue - Multiple services (UAE)',title:'Operational issue - Multiple services (UAE)',summary:'Official source says recovery is expected to take months due to physical infrastructure damage.',status:'outage',impact:'outage',scope:'regional',region_name:'UAE',region_code:'ME-CENTRAL-1',is_long_running:true,last_official_update:'2026-04-30T12:00:00Z',updated_at:'2026-04-30T12:00:00Z'}]},
    {id:'openai',name:'OpenAI',tile_kind:'operational',incidents:[],recent_incidents:[{title:'Resolved',status:'operational'}]}
  ];
  const items=teaser.currentItems(payload(providers));
  assert.equal(items.length,3);
  const aws=items.find((item)=>item.provider==='AWS');
  assert.ok(aws);
  assert.equal(aws.type,'outage');
  assert.equal(aws.label,'Ongoing regional disruption');
  assert.equal(aws.region,'UAE · ME-CENTRAL-1');
  assert.equal(aws.summary,'Multiple AWS services disrupted in UAE (ME-CENTRAL-1)');
  assert.match(aws.details,/recovery is expected to take months/);
  assert.equal(aws.lastOfficialUpdate,'2026-04-30T12:00:00Z');
  assert.equal(aws.checkedAt,'2026-07-19T10:00:00Z');
  assert.notEqual(aws.summary,'Major outage reported');
  teaser.render(container, payload(providers), {dashboardUrl:'/lousy-outages/'});
  const text=(el)=>[el.textContent].concat(...el.children.map(text)).join(' ');
  assert.match(text(container), /3 current issues/);
  assert.match(text(container), /Ongoing regional disruption/);
  assert.match(text(container), /UAE · ME-CENTRAL-1/);
  assert.match(text(container), /Last official update Apr 30/);
  assert.match(text(container), /Status checked Jul 19/);
});
