// B1/B2 behavior with synthetic scroll geometry; no network, orders or persisted data.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
function environment(){const frames=[],watches=new Map();const motion={mobile:{matches:true},reduced:{matches:false},schedule(){},watch(node,fn){watches.set(node,fn);},onFrame(fn){frames.push(fn);}};return {motion,frames,watches};}
{
 const {motion,frames,watches}=environment();
 const cards=[88,102,116,130].map((top,i)=>({top,offsetHeight:190,href:'/menu/?kategori=unchanged-'+i,getBoundingClientRect(){return {top:this.top};},style:{value:null,setProperty(k,v){assert.equal(k,'--mb-scale');this.value=Number(v);},removeProperty(){this.value=null;}}}));
 const root={querySelectorAll:()=>cards,addEventListener(){}};const document={querySelector:()=>root,activeElement:null};
 vm.runInNewContext(fs.readFileSync('mobile-category-stack.js','utf8'),{window:{RuethiesMotion:motion},document,innerHeight:844});
 watches.get(root)({isIntersecting:true});frames[0]();assert(cards[0].style.value>=.9&&cards[0].style.value<.94);assert(cards[2].style.value<1);assert.equal(cards[3].style.value,1);
 document.activeElement=cards[0];frames[0]();assert.equal(cards[0].style.value,1);
 cards.forEach((card,i)=>card.top=100+i*500);frames[0]();cards.forEach(card=>assert.equal(card.style.value,1));
 motion.reduced.matches=true;frames[0]();cards.forEach(card=>assert.equal(card.style.value,null));
 cards.forEach((card,i)=>assert.equal(card.href,'/menu/?kategori=unchanged-'+i));
}
function luminance(hex){const rgb=hex.match(/\w\w/g).map(x=>parseInt(x,16)/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4);return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;}
for(const [ink,paper]of[['3D0000','FAF6F0'],['3D0000','F1E8DC'],['FFFDF9','7A0000'],['EAD9CC','7A0000'],['7A0000','FAF6F0']]){const values=[luminance(ink),luminance(paper)].sort((a,b)=>a-b);assert((values[1]+.05)/(values[0]+.05)>=4.5);}
console.log('PASS: dynamic category count/order, reversible stack transforms, focus, reduced motion, AA palette');
