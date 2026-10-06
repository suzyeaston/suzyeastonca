const {chromium}=require('@playwright/test');
const fs=require('fs'),assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--use-gl=angle','--use-angle=swiftshader','--enable-unsafe-swiftshader']});const page=await browser.newPage({viewport:{width:1280,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 let html=fs.readFileSync('template-yvr-radio.php','utf8').replace(/<\?php[\s\S]*?\?>/g,'');
 html='<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{background:#060d12;margin:0}'+fs.readFileSync('node_modules/maplibre-gl/dist/maplibre-gl.css','utf8')+fs.readFileSync('assets/css/yvr-radio.css','utf8')+'</style></head><body>'+html+'</body></html>';
 await page.route('https://radio.test/**',r=>r.fulfill({contentType:'text/html; charset=utf-8',body:html}));await page.route('https://tiles.openfreemap.org/styles/dark',r=>r.fulfill({json:{version:8,sources:{},layers:[{id:'background',type:'background',paint:{'background-color':'#0a1b20'}}]}}));await page.goto('https://radio.test/');
 await page.evaluate(()=>{
 window.YvrRadio={channels:[
 {key:'citr',label:'CiTR 101.9 FM',hint:'Campus and community radio · UBC',place:'UBC campus · approximate studio location',map_lat:49.2666,map_lon:-123.2499,group:'Community radio',region:'vancouver',mode:'stream',format:'aac',stream_url:'https://example.com/aac',source_url:'https://player.citr.ca/'},
 {key:'cfro',label:'CFRO 100.5 FM',hint:'Vancouver Co-operative Radio',place:'370 Columbia Street · studio area',map_lat:49.2815,map_lon:-123.1021,group:'Community radio',region:'vancouver',mode:'link_out',source_url:'https://coopradio.org/'},
 {key:'cbc',label:'CBC Radio One',hint:'Vancouver live',place:'CBC Vancouver · studio area',map_lat:49.2795,map_lon:-123.1144,group:'Radio',region:'vancouver',mode:'stream',format:'hls',stream_url:'https://example.com/live.m3u8'},
 {key:'cjsf',label:'CJSF 90.1 FM',hint:'Independent radio · SFU',place:'SFU Burnaby campus',map_lat:49.2788,map_lon:-122.9197,group:'Community radio',region:'vancouver',mode:'link_out',source_url:'https://www.cjsf.ca/streaming'},
 {key:'yvr',label:'YVR approach',hint:'Airspace chatter',place:'YVR airspace · reference point',map_lat:49.1939,map_lon:-123.1764,group:'Scanners',region:'vancouver',mode:'link_out',source_url:'https://www.liveatc.net/'},
 {key:'marine',label:'Vancouver marine',hint:'Port and coast VHF',place:'Vancouver harbour · listening area',map_lat:49.305,map_lon:-123.08,group:'Scanners',region:'vancouver',mode:'link_out',source_url:'https://www.broadcastify.com/'},
 {key:'hydro',label:'Bush Point, WA',hint:'Whidbey Island, Washington',place:'Hydrophone location',map_lat:48.0337,map_lon:-122.604,group:'Hydrophones',region:'salish',mode:'link_out',source_url:'https://live.orcasound.net/listen/bush-point'}]};
 HTMLMediaElement.prototype.play=function(){return Promise.resolve()};HTMLMediaElement.prototype.load=function(){};Object.defineProperty(HTMLMediaElement.prototype,'src',{set(v){this._src=v},get(){return this._src||''}});
 });
 await page.addScriptTag({content:fs.readFileSync('node_modules/maplibre-gl/dist/maplibre-gl.js','utf8')});
 await page.addScriptTag({content:fs.readFileSync('assets/js/yvr-radio-map.js','utf8')});await page.addScriptTag({content:fs.readFileSync('assets/js/yvr-radio.js','utf8')});
 await page.locator('.yr-map-pin').first().waitFor({timeout:20000});
 assert.equal(await page.locator('.yr-map-pin').count(),6);
 await page.getByRole('button',{name:/CiTR 101.9 FM — choose/}).click();await page.locator('.yr-map-popup button').click();assert.equal(await page.locator('#yr-title').textContent(),'CiTR 101.9 FM');
 await page.getByRole('button',{name:'Salish Sea',exact:true}).click();assert.equal(await page.locator('.yr-map-pin').count(),7);
 await page.getByRole('button',{name:'Vancouver',exact:true}).click();assert.equal(await page.locator('.yr-map-pin').count(),6);
 await page.locator('#yr-search').fill('SFU');assert.equal(await page.locator('.yr-map-pin').count(),1);await page.locator('#yr-search').fill('');
 await page.waitForFunction(()=>document.querySelector('#yr-map-status').textContent!=='Loading the listening map…',{},{timeout:25000}).catch(()=>{});
 await page.locator('#yr-map-title').scrollIntoViewIfNeeded();await page.screenshot({path:'/tmp/yvr-radio-map.png'});
 await page.setViewportSize({width:390,height:844});await page.locator('#yr-map-title').scrollIntoViewIfNeeded();assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:'/tmp/yvr-radio-map-mobile.png'});
 assert.deepEqual(errors,[]);console.log('PASS: real MapLibre rendered pins, pin tunes receiver, region switches, search filters map, mobile width. Map status:',await page.locator('#yr-map-status').textContent());await browser.close();
})();
