// Isolated local preview: never sends WhatsApp or contacts production.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),cp=require('node:child_process');
const root=path.resolve(__dirname,'..'),files=['menu.json','faq.json','atmosphere.json'],before=files.map(f=>fs.readFileSync(path.join(root,f),'utf8'));
(async()=>{
 const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));const dirs=new Set(fs.readdirSync(os.tmpdir()));
 const child=cp.spawn('php',['tools/preview-profile.php','--port='+port],{cwd:root,stdio:['ignore','pipe','pipe']});let output='';child.stdout.on('data',d=>output+=d);child.stderr.on('data',d=>output+=d);let fixture;
 const closed=new Promise(resolve=>child.on('close',resolve));
 try{
  let response;for(let i=0;i<50;i++){try{response=await fetch('http://127.0.0.1:'+port+'/');if(response.status===200)break;}catch{}await new Promise(r=>setTimeout(r,50));}
  assert(response&&response.status===200,output);assert((await response.text()).includes('profile-data'));
  const added=fs.readdirSync(os.tmpdir()).filter(d=>!dirs.has(d)&&/^ruethies-profile-[a-f0-9]{12}$/.test(d));assert.equal(added.length,1);fixture=path.join(os.tmpdir(),added[0]);
  const catalogue=JSON.parse(fs.readFileSync(path.join(fixture,'public_html/menu.json'),'utf8'));assert(catalogue.every(m=>m.uid&&m.slug));assert.deepEqual(fs.readdirSync(path.join(fixture,'public_html/admin')),['catalog.php']);
  assert.equal((await fetch('http://127.0.0.1:'+port+'/admin/setup.php')).status,404);
  child.kill('SIGTERM');await Promise.race([closed,new Promise((_,reject)=>setTimeout(()=>reject(Error('Preview failed to stop')),4000))]);assert(!fs.existsSync(fixture));
  files.forEach((f,i)=>assert.equal(fs.readFileSync(path.join(root,f),'utf8'),before[i]));
  console.log('PASS: preview runs on loopback, prepares only copied catalogue metadata, excludes accounts/admin setup, preserves source JSON and cleans its own fixture');
 }finally{if(child.exitCode===null)child.kill('SIGKILL');if(fixture&&fs.existsSync(fixture))fs.rmSync(fixture,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exitCode=1;});
