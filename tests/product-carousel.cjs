const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../product.js'),'utf8');
function element(){return {hidden:false,dataset:{},attrs:{},events:{},addEventListener(n,f){this.events[n]=f},setAttribute(k,v){this.attrs[k]=v},removeAttribute(k){delete this.dataset[k.replace('data-','')]}};}
function fixture(reduced=false){
 const buttons=Object.fromEntries(['pause','prev','next'].map(n=>[n,element()])),controls=element(),position=element(),root=element(),timers=new Map(),document=element(),motion=element();
 motion.matches=reduced;document.hidden=false;let timerId=0,observer;
 const dots=Array.from({length:3},()=>element()),dotGroup=element();
 const slides=Array.from({length:3},(_,i)=>{const slide=element(),img=element();slide.hidden=i!==0;if(i)img.dataset.src='/photo-'+i+'.webp';slide.img=img;slide.querySelectorAll=s=>s==='[data-src]'&&img.dataset.src?[img]:[];return slide;});
 root.querySelectorAll=s=>s==='[data-slide]'?slides:dots;root.querySelector=s=>s==='.carousel-dots'?dotGroup:s==='.carousel-controls'?controls:s==='.carousel-position'?position:buttons[s.match(/"(.*?)"/)[1]];
 const context=vm.createContext({document,window:{IntersectionObserver:true},IntersectionObserver:class{constructor(cb){observer=cb}observe(){}},matchMedia:()=>motion,setTimeout:f=>{timers.set(++timerId,f);return timerId},clearTimeout:id=>timers.delete(id)});
 vm.runInContext(source.slice(source.indexOf('  function initCarousel'),source.indexOf('  const carousel=initCarousel')),context);
 const api=context.initCarousel(root);
 return{root,slides,dots,buttons,controls,position,document,motion,timers,api,context,intersect(yes){observer([{isIntersecting:yes}])},tick(){assert.equal(timers.size,1);const [id,work]=timers.entries().next().value;timers.delete(id);work()}};
}
const f=fixture();assert.equal(f.timers.size,0);assert.equal(f.slides[1].img.src,undefined);f.intersect(true);f.tick();assert.equal(f.slides[1].hidden,false);assert.equal(f.slides[1].img.src,'/photo-1.webp');assert.equal(f.slides[2].img.src,undefined);
console.log('PASS: rotation starts only in view and hydrates only the selected image');
f.root.events.mouseenter();assert.equal(f.timers.size,0);f.root.events.mouseleave();assert.equal(f.timers.size,1);
f.root.events.focusin();assert.equal(f.timers.size,0);f.root.events.mouseleave();assert.equal(f.timers.size,0);
f.buttons.pause.events.click();assert.equal(f.timers.size,1);f.document.hidden=true;f.document.events.visibilitychange();assert.equal(f.timers.size,0);f.document.hidden=false;f.document.events.visibilitychange();assert.equal(f.timers.size,1);f.intersect(false);assert.equal(f.timers.size,0);f.intersect(true);assert.equal(f.timers.size,1);
console.log('PASS: hover, focus, off-screen state and background tab suspend rotation');
f.buttons.pause.events.pointerdown();f.root.events.focusin();f.buttons.pause.events.click();assert.equal(f.timers.size,0);f.buttons.pause.events.click();assert.equal(f.timers.size,1);
f.buttons.next.events.click();assert.equal(f.slides[2].hidden,false);assert.equal(f.timers.size,0);let prevented=false;f.root.events.keydown({key:'ArrowLeft',preventDefault(){prevented=true}});assert(prevented);assert.equal(f.slides[1].hidden,false);f.api.localize(true);assert.equal(f.buttons.pause.textContent,'Play');assert.equal(f.buttons.next.attrs['aria-label'],'Next photo');
console.log('PASS: pointer pause, manual navigation, keyboard arrows and English controls work');
f.dots[0].events.click();assert.equal(f.slides[0].hidden,false);assert.equal(f.dots[0].attrs['aria-current'],'true');assert.equal(f.position.textContent,'01 / 03');
f.root.events.pointerdown({pointerType:'touch',clientX:180,clientY:100,target:{closest(){return null}}});f.root.events.pointerup({clientX:70,clientY:105});assert.equal(f.slides[1].hidden,false);
f.root.events.pointerdown({pointerType:'touch',clientX:180,clientY:100,target:{closest(){return null}}});f.root.events.pointerup({clientX:175,clientY:200});assert.equal(f.slides[1].hidden,false);
console.log('PASS: indicators select photos and horizontal touch swipes navigate without treating vertical scroll as a swipe');
const r=fixture(true);r.intersect(true);assert.equal(r.timers.size,0);r.buttons.pause.events.click();assert.equal(r.timers.size,1);r.motion.events.change();assert.equal(r.timers.size,0);assert.equal(r.context.initCarousel(null),null);
console.log('PASS: reduced motion disables autoplay and the control can explicitly start rotation');
