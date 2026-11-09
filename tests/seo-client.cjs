const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../app.js'),'utf8');
const bootstrap=source.slice(source.indexOf('const catalogueElement ='),source.indexOf('const I18N ='));
const item={cat:'packages',img:'images/menu/snackbox.jpg',slug:'snackbox',id:{n:'Snackbox hosting',d:'Actual hosting content'},available:true};
async function testBootstrap(snapshot,data){let fetches=0,renders=0;const ctx=vm.createContext({document:{getElementById:()=>snapshot?{textContent:JSON.stringify({menu:[item],faq:[]})}:null},fetch:async()=>{fetches++;return{ok:true,json:async()=>data};},renderMenus(){renders++;}});vm.runInContext(bootstrap,ctx);await new Promise(r=>setImmediate(r));return{ctx,fetches,renders};}
(async()=>{
 let result=await testBootstrap(true);assert.equal(result.fetches,0);assert.equal(vm.runInContext('MENU[0].id.n',result.ctx),item.id.n);console.log('PASS: server snapshot uses no separate catalogue request');
 result=await testBootstrap(false,[item]);assert.equal(result.fetches,1);assert.equal(vm.runInContext('MENU[0].url',result.ctx),'/snack-box-cikarang/');assert.equal(result.renders,1);console.log('PASS: older cached HTML reads current hosting products and canonical URLs');
 result=await testBootstrap(false,{broken:true});assert.equal(vm.runInContext('MENU.length',result.ctx),0);console.log('PASS: invalid legacy response never revives sample products');
 let click,shown=0;const ctx=vm.createContext({document:{addEventListener:(type,handler)=>{click=handler;}},MENU:[item],showLB(){shown++;}});
 vm.runInContext(source.slice(source.indexOf('document.addEventListener("click", e=>{'),source.indexOf('document.getElementById("lb-close").onclick')),ctx);
 for(const modifier of ['ctrlKey','metaKey','shiftKey','altKey']){let prevented=false;click({target:{closest:selector=>selector==='[data-product]'?{dataset:{product:'0'}}:null},button:0,[modifier]:true,preventDefault(){prevented=true;}});assert.equal(prevented,false);}
 assert.equal(shown,0);let prevented=false;click({target:{closest:()=>({dataset:{product:'0'}})},button:0,preventDefault(){prevented=true;}});assert(prevented);assert.equal(shown,1);console.log('PASS: normal clicks enhance to modal while modified clicks retain native link behavior');
})().catch(error=>{console.error(error);process.exitCode=1;});
