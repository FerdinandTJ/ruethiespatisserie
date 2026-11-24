// Test real shared header language handler without a browser or dependencies.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../profile.js'),'utf8');
// Match the multiline handler, not the aria-pressed rendering loop.
const handlerStart=source.indexOf("  document.querySelectorAll('[data-language]').forEach(button=>{\n");
assert(handlerStart>=0);
const handler=source.slice(handlerStart,source.indexOf("  const burger=",handlerStart));
const initial=source.slice(source.indexOf("  let language='id'"),source.indexOf('  const phrase='));
function page(data,preference='en'){
 const storage=new Map([['ruethies-lang',preference]]),paths=[];let renders=0;
 const buttons=['id','en'].map(lang=>({dataset:{language:lang},disabled:true,addEventListener(event,fn){this.click=()=>{if(!this.disabled)fn()}}}));
 const link={dataset:{articleTranslation:'en'},addEventListener(event,fn){this.click=fn}};
 const context=vm.createContext({data,localStorage:{getItem:key=>storage.get(key),setItem:(key,value)=>storage.set(key,value)},document:{querySelectorAll:selector=>selector==='[data-language]'?buttons:[link]},location:{assign:path=>paths.push(path)},render:()=>renders++});
 vm.runInContext(initial+handler,context);
 return{buttons,link,storage,paths,language:()=>vm.runInContext('language',context),renders:()=>renders};
}
let p=page({articleLang:'id',articleTranslations:{id:'/artikel/panduan/',en:'/artikel/guide/'}});
assert.equal(p.language(),'id','URL language overrides remembered preference without navigation');assert.deepEqual(p.paths,[]);
p.buttons[1].click();assert.deepEqual(p.paths,['/artikel/guide/']);assert.equal(p.storage.get('ruethies-lang'),'en');assert.equal(p.renders(),0);
p=page({articleLang:'en',articleTranslations:{en:'/artikel/guide/',id:'/artikel/panduan/'}},'id');assert.equal(p.language(),'en');p.buttons[0].click();assert.deepEqual(p.paths,['/artikel/panduan/']);
p=page({articleLang:'id',articleTranslations:{id:'/artikel/panduan/'}});assert(p.buttons[1].disabled);p.buttons[1].click();assert.deepEqual(p.paths,[]);p.buttons[0].click();assert.equal(p.renders(),1);
for(const unsafe of ['https://evil.test/','//evil.test/','/admin/','/artikel/../admin/','/artikel/test/?x=1']){p=page({articleLang:'id',articleTranslations:{id:'/artikel/panduan/',en:unsafe}});assert(p.buttons[1].disabled);}
p=page({},'id');p.buttons[1].click();assert.equal(p.language(),'en');assert.deepEqual(p.paths,[]);assert.equal(p.renders(),1);p.link.click();assert.equal(p.storage.get('ruethies-lang'),'en');
console.log('PASS: article header selects real published translation, URL owns language, no load redirect, unavailable/unsafe targets disabled; ordinary pages retain UI toggle');
