<?php
// Admin-only adapter: visual HTML is reduced to the existing safe Markdown format.
defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.');
function article_editor_markdown(string $html): string {
 if(strlen($html)>500000||!mb_check_encoding($html,'UTF-8')||str_contains($html,"\0"))throw new DomainException('Isi terlalu panjang atau tidak valid.');
 $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
 try{$ok=$doc->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);if(!$ok)throw new DomainException('Isi belum dapat dibaca.');}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
 $count=0;
 $walk=function(DOMNode $node,int $depth=0)use(&$walk,&$count):string{
  if(++$count>20000||$depth>60)throw new DomainException('Isi terlalu rumit. Tempel sebagai teks biasa.');
  if($node instanceof DOMText)return str_replace(["\xc2\xa0","\r"],[' ',''],$node->nodeValue);
  if(!$node instanceof DOMElement)return '';
  $tag=strtolower($node->tagName);
  if(in_array($tag,['script','style','iframe','object','embed','svg','math','img','video','audio','input','button','textarea','select','template','noscript'],true))return '';
  $parts='';foreach($node->childNodes as$child)$parts.=$walk($child,$depth+1);
  $text=trim($parts);
  if($tag==='br')return "\n";
  if(in_array($tag,['b','strong','i','em'],true)){
   // The existing Markdown renderer does not support nested inline formats.
   if(strpbrk($text,'*[]`')!==false)throw new DomainException('Gunakan tebal, miring, kode atau tautan secara terpisah pada teks yang sama.');
   return $text===''?'':(in_array($tag,['b','strong'],true)?'**'.$text.'**':'*'.$text.'*');
  }
  if($tag==='code'){if(str_contains($text,'`')||str_contains($text,"\n"))throw new DomainException('Kode beberapa baris belum didukung. Gunakan teks biasa.');return '`'.$text.'`';}
  if($tag==='a'){
   $url=$node->getAttribute('href');if(!article_link($url))return $parts;
   if(strpbrk($url,'()')!==false)$url=str_replace(['(',')'],['%28','%29'],$url);
   if(strpbrk($text,'[]*`')!==false)throw new DomainException('Gunakan teks tautan tanpa format tambahan atau tanda kurung siku.');
   return '['.$text.']('.$url.')';
  }
  if(in_array($tag,['h1','h2','h3','h4','h5','h6'],true))return "\n\n".($tag==='h3'?'### ':'## ').$text."\n\n";
  if($tag==='li'){
   if($node->getElementsByTagName('li')->length)throw new DomainException('Daftar bertingkat belum didukung. Gunakan satu tingkat daftar.');
   $prefix=$node->parentNode?->nodeName==='ol'?'1. ':'- ';return $prefix.preg_replace('/\s*\n\s*/u',' ',$text)."\n";
  }
  if(in_array($tag,['ul','ol'],true))return "\n\n".$text."\n\n";
  if($tag==='blockquote')return "\n\n> ".preg_replace('/\s*\n\s*/u',' ',$text)."\n\n";
  if($tag==='table'){
   $rows=[];foreach($node->getElementsByTagName('tr')as$tr){$cells=[];foreach($tr->childNodes as$cell)if($cell instanceof DOMElement&&in_array($cell->tagName,['th','td'],true)){
    if($cell->hasAttribute('colspan')||$cell->hasAttribute('rowspan'))throw new DomainException('Tabel dengan sel gabungan belum didukung.');
    $value='';foreach($cell->childNodes as$c)$value.=$walk($c,$depth+1);
    if(str_contains($value,'|'))throw new DomainException('Tanda | dalam sel tabel belum didukung.');$cells[]=preg_replace('/\s*\n\s*/u',' ',trim($value));
   }if($cells)$rows[]=$cells;}
   if(!$rows)return ''; $width=count($rows[0]);$lines=[];foreach($rows as$i=>$cells){if(count($cells)!==$width)throw new DomainException('Jumlah kolom tabel harus sama.');$lines[]='| '.implode(' | ',$cells).' |';if($i===0)$lines[]='| '.implode(' | ',array_fill(0,$width,'---')).' |';}return "\n\n".implode("\n",$lines)."\n\n";
  }
  if(in_array($tag,['p','div','section','article','pre'],true))return "\n\n".$text."\n\n";
  return $parts;
 };
 $body=$doc->getElementsByTagName('body')->item(0);$out='';foreach($body->childNodes as$node)$out.=$walk($node);
 $out=trim(preg_replace('/\n{3,}/',"\n\n",$out));if(mb_strlen($out)>60000)throw new DomainException('Isi maksimal 60.000 karakter.');return $out;
}
function article_input_body(array $input): string {
 $mode=$input['body_mode']??'markdown';if(!is_string($mode)||!in_array($mode,['markdown','visual'],true))throw new DomainException('Mode isi tidak valid.');
 if($mode==='markdown')return article_text($input,'body',60000);
 if(!is_string($input['body_html']??null))throw new DomainException('Isi visual tidak valid.');return article_editor_markdown($input['body_html']);
}
