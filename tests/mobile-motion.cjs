// Contracts for shared observers and Jakarta business hours; no network or customer messages.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const events=[],frames=[],observers=[];
class Observer{constructor(fn){this.fn=fn;observers.push(this);}observe(){}}
const media={matches:false,addEventListener(){}};
const context={window:{IntersectionObserver:Observer},document:{documentElement:{lang:'id'}},matchMedia:()=>media,IntersectionObserver:Observer,MutationObserver:Observer,requestAnimationFrame:fn=>frames.push(fn),addEventListener:(...x)=>events.push(x),Set,Map};
vm.runInNewContext(fs.readFileSync('mobile-motion.js','utf8'),context);
const motion=context.window.RuethiesMotion;let first=0,second=0;const node={};motion.watch(node,()=>first++);motion.watch(node,()=>second++);observers[0].fn([{target:node,isIntersecting:true}]);assert.equal(first,1);assert.equal(second,1);
motion.onFrame(()=>{});motion.schedule();motion.schedule();assert.equal(frames.length,1);frames.shift()();assert.equal(frames.length,0);assert.equal(events.filter(x=>x[0]==='scroll').length,1);assert.equal(events[0][2].passive,true);
function status(date,language){const labels=[{classList:{toggle(){}},textContent:''}];const hours={window:{RuethiesMotion:{localize:fn=>fn(),watch(){},reduced:{matches:true}}},document:{documentElement:{lang:language},querySelectorAll:()=>labels,querySelector:()=>null,addEventListener(){},hidden:true},Intl,Date:class extends Date{constructor(){super(date);}},clearInterval(){},setInterval(){throw Error('hidden tab must not start timers');}};vm.runInNewContext(fs.readFileSync('mobile-hours.js','utf8'),hours);return labels[0].textContent;}
assert.match(status('2026-11-15T23:59:00Z','id'),/^Tutup/);assert.match(status('2026-11-16T00:00:00Z','id'),/^Buka/);assert.match(status('2026-11-16T13:59:00Z','en'),/^Open/);assert.match(status('2026-11-16T14:00:00Z','en'),/^Closed/);
console.log('PASS: one passive scheduler, frame coalescing, shared-node subscribers, WIB opening/closing boundaries and ID/EN');
