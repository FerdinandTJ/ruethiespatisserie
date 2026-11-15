// Isolated observer, replay and translation tests. No browser/network or service calls.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../order-story.js'),'utf8');
function node(text=''){
 let value=text;const classes=new Set();return{dataset:{},children:[],className:'',style:{setProperty(){}},classList:{add:x=>classes.add(x),remove:x=>classes.delete(x),contains:x=>classes.has(x),toggle:(x,on)=>on?classes.add(x):classes.delete(x)},appendChild(n){this.children.push(n);return n},replaceChildren(...nodes){this.children=nodes;value='';this.replaces=(this.replaces||0)+1},get textContent(){return this.children.length?this.children.map(c=>c.textContent).join(''):value},set textContent(v){value=v;this.children=[]}};
}
function fixture({mobile=false,reduced=false}={}){
 const desktop={matches:!mobile,addEventListener(){}},motion={matches:reduced,addEventListener(n,fn){this.change=fn}},root=node(),doc={documentElement:{lang:'id'},createElement:()=>node(),createTextNode:text=>node(text),createDocumentFragment:()=>node()},observers=[],mutations=[],cardTexts=[node(),node()];
 for(const n of cardTexts)n.dataset={id:'Halo Ruethies, saya mau pesan 20 snack box untuk hari Sabtu.',en:'Hello Ruethies, I would like to order 20 snack boxes for Saturday.'};
 const steps=[1,2,3].map((n,i)=>{const step=node();step.dataset.orderStep=String(n);step.top=i*600;step.getBoundingClientRect=()=>({top:step.top,height:600});step.card=node();step.querySelector=()=>step.card;return step});
 root.querySelectorAll=q=>q==='[data-order-step]'?steps:cardTexts.filter(n=>n.classList.contains('ow-typing'));
 root.querySelector=q=>q.startsWith('.ow-sticky')?cardTexts[0]:cardTexts[1];
 doc.querySelector=q=>q==='[data-order-story]'?root:{getBoundingClientRect:()=>({height:96})};
 vm.runInNewContext(source,{document:doc,window:{IntersectionObserver:true,addEventListener(){}},innerHeight:900,matchMedia:q=>q.includes('reduced-motion')?motion:desktop,IntersectionObserver:class{constructor(fn,options){this.fn=fn;this.options=options;observers.push(this)}observe(){}},MutationObserver:class{constructor(fn){mutations.push(fn)}observe(){}}});
 const focus=n=>{steps.forEach((s,i)=>s.top=(i-n+1)*600+150);observers[0].fn(steps.map(s=>({target:s,isIntersecting:Number(s.dataset.orderStep)===n})));};
 return{root,steps,focus,observers,mutations,doc,cardTexts,motion};
}
const f=fixture();assert.equal(f.observers[0].options.rootMargin,'-40% 0px -40% 0px');
for(const n of[1,2,3,2,1]){f.focus(n);assert.equal(f.root.dataset.activeStep,String(n));}
assert.equal(f.cardTexts[0].replaces,1);assert.equal(f.cardTexts[0].textContent,f.cardTexts[0].dataset.id);
console.log('PASS: observer follows forward/reverse steps and types the exact message only once, preserving spaces');
f.doc.documentElement.lang='en';f.mutations[0]();assert.equal(f.cardTexts[0].textContent,f.cardTexts[0].dataset.en);assert(!f.cardTexts[0].classList.contains('ow-typing'));
f.focus(2);assert(!f.cardTexts[0].classList.contains('ow-typing'));
console.log('PASS: language changes finish typing in the selected language without replay or mixed text');
const m=fixture({mobile:true});m.observers[1].fn([{target:m.steps[1],isIntersecting:true}]);assert(m.steps[1].classList.contains('ow-entered'));assert(m.cardTexts[1].classList.contains('ow-typing'));assert(!m.cardTexts[0].classList.contains('ow-typing'));
m.observers[2].fn([{isIntersecting:false}]);assert(!m.cardTexts[1].classList.contains('ow-typing'));
console.log('PASS: mobile reveals inline cards and offscreen state stops unfinished typing');
const r=fixture({reduced:true});r.focus(2);assert(!r.cardTexts[0].classList.contains('ow-typing'));
vm.runInNewContext(source,{document:{querySelector:()=>null}});
console.log('PASS: reduced motion skips typing and pages without the section are untouched');
