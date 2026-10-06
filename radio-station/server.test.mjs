import {test} from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import {once} from 'node:events';
import {spawn,spawnSync} from 'node:child_process';
import {mkdtempSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {createRadioServer} from './server.mjs';
const token='test-only-not-a-real-secret-0123456789';
async function setup(t,options={}){const server=createRadioServer({token,...options});server.listen(0,'127.0.0.1');await once(server,'listening');t.after(()=>server.shutdown());return 'http://127.0.0.1:'+server.address().port;}
function source(base,secret=token){return http.request(base+'/source',{method:'POST',headers:{Authorization:'Bearer '+secret,'Content-Type':'audio/mpeg'}});}
async function waitOnline(base){for(let i=0;i<100;i++){if((await (await fetch(base+'/status.json')).json()).online)return;await new Promise(r=>setTimeout(r,30));}throw new Error('Source did not become online');}
test('authentication, offline state, single-source lock, capacity and disconnect',async t=>{
 const base=await setup(t,{maxListeners:1});
 assert.equal((await fetch(base+'/live.mp3')).status,503);
 assert.equal((await fetch(base+'/source',{method:'POST'})).status,401);
 const src=source(base);src.on('error',()=>{});const acknowledged=once(src,'response');src.write(Buffer.from([255,251,144,0]));assert.equal((await acknowledged)[0].statusCode,200);await waitOnline(base);
 assert.equal((await fetch(base+'/source',{method:'POST',headers:{Authorization:'Bearer '+token,'Content-Type':'audio/mpeg'}})).status,409);
 const client=http.get(base+'/live.mp3');client.on('error',()=>{});const [response]=await once(client,'response');response.on('error',()=>{});response.resume();
 assert.equal(response.statusCode,200);assert.equal(response.headers['access-control-allow-origin'],'*');
 assert.equal((await fetch(base+'/live.mp3')).status,503);
 src.end();await once(response,'end');assert.equal((await (await fetch(base+'/status.json')).json()).online,false);
 assert.equal((await fetch(base+'/source',{method:'POST',headers:{Authorization:'Bearer '+token,'Content-Type':'audio/wav'}})).status,415);
});
test('idle source releases the single-source slot',async t=>{
 const base=await setup(t,{idleMs:100});const src=source(base);src.on('error',()=>{});src.write(Buffer.from([255,251]));await once(src,'response');await new Promise(r=>setTimeout(r,250));
 assert.equal((await (await fetch(base+'/status.json')).json()).online,false);
});
test('oversized listener queue disconnects instead of growing unbounded',async t=>{
 const base=await setup(t,{maxQueue:1024});const src=source(base);src.on('error',()=>{});src.write(Buffer.from([255,251]));await once(src,'response');await waitOnline(base);
 const client=http.get(base+'/live.mp3');client.on('error',()=>{});const [response]=await once(client,'response');response.on('error',()=>{});const closed=new Promise(r=>response.once('close',r));response.resume();src.write(Buffer.alloc(2048));await closed;
 assert.equal((await (await fetch(base+'/status.json')).json()).listeners,0);src.destroy();
});
test('real FFmpeg → Python transmitter → relay → decodable MP3', {timeout:15000},async t=>{
 if(spawnSync('ffmpeg',['-version']).status!==0){t.skip('FFmpeg unavailable');return;}
 const base=await setup(t),dir=mkdtempSync(join(tmpdir(),'suzy-radio-test-'));t.after(()=>rmSync(dir,{recursive:true,force:true}));
 const path=join(dir,'test-tone.wav');
 assert.equal(spawnSync('ffmpeg',['-v','error','-f','lavfi','-i','sine=frequency=440:duration=3','-y',path]).status,0);
 const child=spawn('python3',[new URL('./transmit.py',import.meta.url).pathname,'--server',base,'--file',path],{env:{...process.env,SOURCE_TOKEN:token}});let stderr='';child.stderr.on('data',x=>stderr+=x);child.stdout.resume();t.after(()=>child.kill());const exited=once(child,'exit');
 await waitOnline(base);const request=http.get(base+'/live.mp3');const [response]=await once(request,'response');const chunks=[];response.on('data',x=>chunks.push(x));await once(response,'end');const [code]=await exited;
 assert.equal(code,0,stderr);const audio=Buffer.concat(chunks);assert.ok(audio.length>10000,'Listeners receive MP3 bytes');
 const decode=spawnSync('ffmpeg',['-v','error','-i','pipe:0','-f','null','-'],{input:audio});assert.equal(decode.status,0,decode.stderr.toString());
 assert.equal((await (await fetch(base+'/status.json')).json()).online,false);
});
