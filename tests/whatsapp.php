<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$html=file_get_contents($root.'/index.html');$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML($html);libxml_clear_errors();$xp=new DOMXPath($doc);
function wa_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$links=$xp->query('//a[@data-contact-wa]');wa_check($links->length===5,'header, hero, location, mobile navigation and contact bar have direct contact links');
foreach($links as$link){$url=parse_url($link->getAttribute('href'));parse_str($url['query']??'',$query);wa_check($url['scheme']==='https'&&$url['host']==='wa.me'&&$url['path']==='/6285139281257'&&($query['text']??'')==='Halo Ruethies Patisserie! Saya ingin bertanya tentang menu dan pemesanan.'&&$link->getAttribute('target')==='_blank'&&in_array('noopener',explode(' ',$link->getAttribute('rel')),true),'contact link works without JavaScript and opens safe destination: '.$link->getAttribute('data-i18n'));}
wa_check($xp->query('//*[@id="backdrop" or @id="wa-form" or @data-open-wa]')->length===0,'reservation dialog and triggers removed');
wa_check($xp->query('//*[@id="lightbox"]')->length===1&&$xp->query('//*[@id="stickybar"]//a[@data-contact-wa]')->length===1,'gallery lightbox and mobile contact bar retained');
$faq=json_decode(file_get_contents($root.'/faq.json'),true,512,JSON_THROW_ON_ERROR);wa_check($faq[1]['q']==='Bagaimana cara menghubungi atau memesan?'&&str_contains($faq[1]['ae'],'availability, prices and ordering'),'FAQ explains contact and ordering in both languages');
$schema=json_decode($xp->query('//script[@type="application/ld+json"]')->item(3)->textContent,true,512,JSON_THROW_ON_ERROR);wa_check($schema['mainEntity'][1]['name']===$faq[1]['q'],'FAQ structured data matches the updated question');
echo "All WhatsApp markup checks passed.\n";
