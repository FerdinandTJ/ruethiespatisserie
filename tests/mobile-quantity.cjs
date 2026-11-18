// Exercise the stepper module against a tiny DOM fixture, without sending messages.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const elements=[],animations=[];
class Node{
 constructor(tag){this.tag=tag;this.children=[];this.handlers={};this.attributes={};this.className='';this.hidden=false;this.value='';this.disabled=false;this.textContent='';this.classList={add(){},remove(){}};elements.push(this);}
 append(...nodes){for(const node of nodes){if(node.parent)node.parent.children.splice(node.parent.children.indexOf(node),1);this.children.push(node);node.parent=this;}}
 before(node){const parent=this.parent,index=parent.children.indexOf(this);parent.children.splice(index,0,node);node.parent=parent;}
 after(node){const parent=this.parent,index=parent.children.indexOf(this);parent.children.splice(index+1,0,node);node.parent=parent;}
 closest(tag){let node=this.parent;while(node){if(node.tag===tag)return node;node=node.parent;}return null;}
 setAttribute(k,v){this.attributes[k]=v;}addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);}dispatchEvent(event){for(const fn of this.handlers[event.type]||[])fn(event);}
 replaceChildren(...nodes){this.children.forEach(node=>node.parent=null);this.children=[];this.append(...nodes);}
 animate(frames){const animation={frames,cancelled:false,cancel(){this.cancelled=true;},finished:new Promise(()=>{})};animations.push(animation);return animation;}
}
const group=new Node('div'),label=new Node('label'),input=new Node('input');group.append(label);label.append(input);input.value='1';input.min='1';input.max='999';input.reportValidity=()=>input.reported=true;Object.defineProperty(input,'validity',{get:()=>({valid:Number.isInteger(Number(input.value))&&Number(input.value)>=1&&Number(input.value)<=999})});
const observed=[];input.addEventListener('input',()=>observed.push(input.value));
const document={getElementById:()=>input,createElement:tag=>new Node(tag),documentElement:{lang:'id'}},motion={reduced:{matches:false,addEventListener(type,fn){this.changed=fn;}},localize(fn){this.localized=fn;fn();}};
vm.runInNewContext(fs.readFileSync('mobile-quantity.js','utf8'),{document,window:{RuethiesMotion:motion},Event:class{constructor(type){this.type=type;}},Promise});
const [minus,plus]=elements.filter(node=>node.tag==='button'),live=elements.find(node=>node.className==='mc-quantity-live'),visual=elements.find(node=>node.className==='mc-number-visual');
assert.equal(minus.disabled,true);assert(!label.children.some(node=>node.tag==='button'));plus.dispatchEvent({type:'click'});assert.equal(input.value,'2');assert.equal(observed.at(-1),'2');assert.equal(live.textContent,'Jumlah: 2');assert.equal(visual.hidden,false);assert.equal(animations.at(-1).frames[0].transform,'translateY(100%)');
plus.dispatchEvent({type:'click'});assert.equal(input.value,'3');assert(animations[0].cancelled);
minus.dispatchEvent({type:'click'});assert.equal(input.value,'2');assert.equal(animations.at(-1).frames[0].transform,'translateY(-100%)');
input.value='998';input.dispatchEvent({type:'input'});plus.dispatchEvent({type:'click'});assert.equal(input.value,'999');assert.equal(plus.disabled,true);
input.value='1.5';input.dispatchEvent({type:'input'});plus.dispatchEvent({type:'click'});assert(input.reported);assert.equal(input.value,'1.5');
document.documentElement.lang='en';motion.localized();assert.equal(plus.attributes['aria-label'],'Increase quantity');assert.equal(minus.attributes['aria-label'],'Decrease quantity');
input.value='20';input.dispatchEvent({type:'input'});motion.reduced.matches=true;const previous=animations.length;plus.dispatchEvent({type:'click'});assert.equal(input.value,'21');assert.equal(animations.length,previous);assert.equal(visual.hidden,true);
console.log('PASS: stepper bounds, directional digits, rapid cancellation, input event integration, label separation, ID/EN and reduced motion');
