const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../app.js'),'utf8');
const helpers=source.slice(source.indexOf('function whatsappURL('),source.indexOf('// Escape teks'));
const row=source.slice(source.indexOf('function rowHTML('),source.indexOf('let query ='));
const esc=source.slice(source.indexOf('const esc ='),source.indexOf('let FAQ ='));
for(const lang of ['id','en']){
 const context=vm.createContext({lang,WA_NUMBER:'6285139281257',t:()=>lang==='id'?'Tanya via WA':'Ask on WA',pic:()=>'',productIndex:()=>0,productPhotoLabel:n=>'Lihat foto: '+n});
 vm.runInContext(helpers+esc+row,context);
 const general=new URL(vm.runInContext('whatsappURL(contactMessage())',context));
 assert.equal(general.origin,'https://wa.me');assert.equal(general.pathname,'/6285139281257');assert.match(general.searchParams.get('text'),lang==='id'?/menu dan pemesanan/:/menu and placing an order/);
 const name='Kue "A&B" <spesial> + café #1?';context.item={id:{n:name,d:'Deskripsi'},en:{n:name,d:'Description'},cat:'cakery',img:'test.jpg'};
 const html=vm.runInContext('rowHTML(item,0)',context);assert(html.includes('data-product="0"'));assert(!html.includes('<spesial>'));assert(html.includes('target="_blank" rel="noopener"'));
 const url=new URL(html.match(/class="askbtn" href="([^"]+)"/)[1].replace(/&amp;/g,'&'));assert(url.searchParams.get('text').includes(name));assert.match(url.searchParams.get('text'),lang==='id'?/harga dan pemesanan/:/prices and ordering/);
 console.log('PASS: general and product links preserve language, destination and special characters: '+lang);
}
