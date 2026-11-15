// Scroll state tests without network, production data or browser dependencies.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../storytelling.js'),'utf8');
const set=()=>{const values=new Set();return{add:x=>values.add(x),contains:x=>values.has(x),toggle:(x,on)=>on?values.add(x):values.delete(x)}};
const chapters=[0,1,2].map(i=>({dataset:{storyStep:String(i+1)},classList:set(),getBoundingClientRect:()=>({top:i*400-scroll,height:400})}));
let scroll=0,id=0;const observers=[],frames=new Map(),listeners=new Map(),scene={dataset:{}},desktop={matches:true,addEventListener(){}},section={classList:set(),style:{setProperty(){}},querySelectorAll:()=>chapters,querySelector:()=>scene};
const context={document:{getElementById:()=>section,querySelector:()=>({getBoundingClientRect:()=>({height:96})})},window:{IntersectionObserver:true,addEventListener:(n,f)=>listeners.set(n,f),removeEventListener:n=>listeners.delete(n)},IntersectionObserver:class{constructor(fn){this.fn=fn;observers.push(this)}observe(){}unobserve(){}},matchMedia:()=>desktop,innerHeight:800,requestAnimationFrame:fn=>{frames.set(++id,fn);return id},cancelAnimationFrame:id=>frames.delete(id)};
vm.runInNewContext(source,context);
function tick(){const work=[...frames.values()];frames.clear();work.forEach(f=>f());}
assert.equal(frames.size,0);assert(!listeners.has('scroll'));
observers[0].fn([{isIntersecting:true}]);tick();assert.equal(scene.dataset.phase,'2');
for(const [offset,phase]of[[0,'2'],[400,'3'],[-400,'1'],[0,'2']]){scroll=offset;listeners.get('scroll')();listeners.get('scroll')();assert.equal(frames.size,1);tick();assert.equal(scene.dataset.phase,phase);assert.equal(chapters.filter(c=>c.classList.contains('is-active')).length,1);}
console.log('PASS: native forward/reverse scroll updates one shared scene, coalescing work into one animation frame');
listeners.get('scroll')();assert.equal(frames.size,1);observers[0].fn([{isIntersecting:false}]);assert.equal(frames.size,0);assert(!listeners.has('scroll'));
console.log('PASS: leaving the section cancels scheduled work and detaches its scroll listener');
desktop.matches=false;observers[0].fn([{isIntersecting:true}]);listeners.get('scroll')();assert.equal(frames.size,0);observers[1].fn([{target:chapters[0],isIntersecting:true}]);assert(chapters[0].classList.contains('has-entered'));
console.log('PASS: mobile preserves static phase visuals and reveals each inline scene independently');
