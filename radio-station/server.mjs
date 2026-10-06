/** Suzy Pirate Radio: small, single-source MP3 broadcast relay. Node 22+. */
import http from 'node:http';
import {timingSafeEqual} from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {readFileSync} from 'node:fs';

export function createRadioServer({token,maxListeners=30,idleMs=20000,maxQueue=262144}={}) {
  if(typeof token!=='string'||token.length<32)throw new Error('SOURCE_TOKEN must contain at least 32 characters. Run setup-local.sh.');
  let source=null,lastData=0,bytes=0;
  const listeners=new Set();
  const authorised=req=>{const actual=Buffer.from(req.headers.authorization||''),expected=Buffer.from('Bearer '+token);return actual.length===expected.length&&timingSafeEqual(actual,expected);};
  function reply(res,status,body){res.writeHead(status,{'Content-Type':'application/json','Cache-Control':'no-store','Access-Control-Allow-Origin':'*','X-Content-Type-Options':'nosniff','Connection':'close'});res.end(JSON.stringify(body));}
  const server=http.createServer({requestTimeout:0,headersTimeout:10000,maxHeaderSize:8192},(req,res)=>{
    // Bound idle sockets even on rejected requests; uploads have their own watchdog.
    req.socket.setTimeout(idleMs,()=>req.socket.destroy());
    const path=(req.url||'').split('?')[0];
    if(req.method==='GET'&&path==='/status.json'){reply(res,200,{station:'Suzy Pirate Radio',online:!!source&&Date.now()-lastData<idleMs&&lastData>0,listeners:listeners.size,bytes_received:bytes});return;}
    if(req.method==='GET'&&path==='/'){
      res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'});res.end(readFileSync(new URL('./listen.html',import.meta.url)));return;
    }
    if(req.method==='POST'&&path==='/source'){
      if(!authorised(req)){reply(res,401,{error:'Source authentication required'});return;}
      if(source){reply(res,409,{error:'A broadcaster is already connected'});return;}
      if((req.headers['content-type']||'').split(';')[0]!=='audio/mpeg'){reply(res,415,{error:'Send MP3 as audio/mpeg'});return;}
      source=req;lastData=0;bytes=0;
      res.writeHead(200,{'Content-Type':'text/plain','Cache-Control':'no-store'});res.flushHeaders();
      let lastChunk=Date.now();
      const timer=setInterval(()=>{if(Date.now()-lastChunk>idleMs)req.destroy();},Math.min(1000,idleMs));timer.unref();
      let ended=false;
      const finish=()=>{if(ended)return;ended=true;clearInterval(timer);if(source===req){source=null;lastData=0;for(const listener of listeners)listener.end();listeners.clear();}if(!res.destroyed)res.end('Broadcast ended\n');};
      req.on('data',chunk=>{
        lastChunk=lastData=Date.now();bytes+=chunk.length;
        for(const listener of listeners){
          if(listener.destroyed||listener.writableLength+chunk.length>maxQueue){listener.destroy();listeners.delete(listener);continue;}
          listener.write(chunk);
        }
      });
      req.on('end',finish);req.on('close',finish);req.on('error',finish);res.on('close',()=>{if(!ended)req.destroy();});return;
    }
    if(req.method==='GET'&&path==='/live.mp3'){
      if(!source||!lastData){reply(res,503,{error:'Off air. The next broadcast has not started.'});return;}
      if(listeners.size>=maxListeners){reply(res,503,{error:'Listener capacity reached. Try again later.'});return;}
      res.writeHead(200,{'Content-Type':'audio/mpeg','Cache-Control':'no-store, no-cache, must-revalidate','Access-Control-Allow-Origin':'*','X-Accel-Buffering':'no','X-Content-Type-Options':'nosniff','icy-name':'Suzy Pirate Radio','icy-br':'128'});
      res.flushHeaders();listeners.add(res);res.on('close',()=>listeners.delete(res));res.on('error',()=>listeners.delete(res));return;
    }
    reply(res,404,{error:'Not found'});
  });
  server.on('clientError',(_error,socket)=>{if(socket.writable)socket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');});
  server.shutdown=()=>{if(source)source.destroy();for(const listener of listeners)listener.destroy();server.close();server.closeAllConnections();};
  return server;
}
if(process.argv[1]===fileURLToPath(import.meta.url)){
  const max=Number(process.env.MAX_LISTENERS||30),port=Number(process.env.PORT||8788);
  if(!Number.isInteger(max)||max<1||max>1000)throw new Error('MAX_LISTENERS must be between 1 and 1000');
  if(!Number.isInteger(port)||port<1||port>65535)throw new Error('Invalid PORT');
  const server=createRadioServer({token:process.env.SOURCE_TOKEN,maxListeners:max});
  const host=process.env.BIND_HOST||'127.0.0.1';
  server.listen(port,host,()=>console.log(`Suzy Pirate Radio listening on ${host}:${port}; no source connected yet.`));
  for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>server.shutdown());
}
