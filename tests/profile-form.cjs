const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../profile.js'),'utf8');
function input(value=''){return{value,min:'',message:'',setCustomValidity(message){this.message=message},get validity(){return{valid:!this.message}},reportValidity(){this.reported=true}};}
const fields={'order-quantity':input('1'),'order-date':input(),'order-note':input(),'order-whatsapp':{href:''}},sticky={href:''};
class Clock extends Date{constructor(...args){super(...(args.length?args:['2026-11-12T18:00:00Z']))}}
const context=vm.createContext({Date:Clock,Intl,product:{id:{n:'Kue A&B <spesial>'},en:{n:'Special cake'}},data:{canonical:'https://ruethiespatisserie.com/menu/test/'},language:'id',phrase:(id,en)=>context.language==='en'?en:id,document:{getElementById:id=>fields[id],querySelectorAll:()=>[sticky]}});
vm.runInContext(source.slice(source.indexOf('  function todayWIB'),source.indexOf('  if(product){document.querySelector')),context);
assert(context.updateOrder());assert.equal(fields['order-date'].min,'2026-11-13');
for(const value of ['','0','-1','1.5','1000','NaN','Infinity','9007199254740992']){fields['order-quantity'].value=value;assert.equal(context.updateOrder(true),false);assert(fields['order-quantity'].reported);}
fields['order-quantity'].value='3';fields['order-date'].value='2026-11-12';assert.equal(context.updateOrder(),false);fields['order-date'].value='2026-11-13';fields['order-note'].value='Catatan & "kutipan" <tag>\nPesanan khusus';assert(context.updateOrder());
let text=new URL(fields['order-whatsapp'].href).searchParams.get('text');assert(text.includes('Kue A&B <spesial>'));assert(text.includes('Jumlah: 3'));assert(text.includes('Tanggal kebutuhan: 2026-11-13'));assert(text.includes(fields['order-note'].value));assert(text.includes(context.data.canonical));assert.equal(sticky.href,fields['order-whatsapp'].href);
context.language='en';assert(context.updateOrder());text=new URL(sticky.href).searchParams.get('text');assert(text.includes('Special cake'));assert(text.includes('Quantity: 3'));assert.equal(new URL(sticky.href).pathname,'/6285139281257');
fields['order-quantity'].value='999';assert(context.updateOrder());fields['order-quantity'].value='1000';assert.equal(context.updateOrder(),false);fields['order-quantity'].value='3';fields['order-note'].value='a'.repeat(501);assert.equal(context.updateOrder(),false);fields['order-note'].value='';fields['order-date'].value='';assert(context.updateOrder());assert(!new URL(sticky.href).searchParams.get('text').includes('Date needed:'));
assert(!source.includes('sessionStorage'));assert.equal((source.match(/localStorage.setItem/g)||[]).length,1);assert(source.includes("localStorage.setItem('ruethies-lang'"));
console.log('PASS: WhatsApp draft validates positive whole quantities, Jakarta date boundary, optional values, note limit, Unicode/special text, language and contextual sticky link; drafts are not persisted');

// Variant selection is required only on enabled products; the base link remains for no-JS.
const radios=['a','b'].map(value=>({...input(),value,checked:false,addEventListener(){}}));
const variantFields={'product-variants':{querySelectorAll:()=>radios,classList:{add(){}}},'variant-prompt':{},'order-quantity':input('2'),'order-date':input(),'order-note':input(),'order-whatsapp':{href:'https://wa.me/6285139281257'}};
const variantsContext=vm.createContext({Date:Clock,Intl,product:{variants_enabled:true,id:{n:'Swiss Roll'},en:{n:'Swiss Roll'},variants:[{uid:'a',id:{n:'Vanilla & Strawberry'},en:{n:'Strawberry Vanilla'}},{uid:'b',id:{n:'Pandan Keju'},en:{n:'Pandan Cheese'}}]},data:{canonical:'https://ruethiespatisserie.com/menu/swiss-roll/'},language:'id',phrase:(id,en)=>variantsContext.language==='en'?en:id,document:{getElementById:id=>variantFields[id],querySelectorAll:()=>[]}});
vm.runInContext(source.slice(source.indexOf('  function todayWIB'),source.indexOf('  if(product){document.querySelector')),variantsContext);
assert.equal(variantsContext.updateOrder(true),false);assert(radios[0].reported);
radios[0].checked=true;assert(variantsContext.updateOrder());assert(radios.every(r=>r.message===''));
assert.match(new URL(variantFields['order-whatsapp'].href).searchParams.get('text'),/Varian: Vanilla & Strawberry/);
variantsContext.language='en';assert(variantsContext.updateOrder());assert.match(new URL(variantFields['order-whatsapp'].href).searchParams.get('text'),/Variant: Strawberry Vanilla/);
radios[0].checked=false;radios[1].checked=true;assert(variantsContext.updateOrder());assert.match(new URL(variantFields['order-whatsapp'].href).searchParams.get('text'),/Variant: Pandan Cheese/);
variantsContext.product.variants_enabled=false;assert(variantsContext.updateOrder());assert(!new URL(variantFields['order-whatsapp'].href).searchParams.get('text').includes('Variant:'));
console.log('PASS: explicit flavour choice, encoded ID/EN WhatsApp variant, stable radio selection and disabled-product fallback');
