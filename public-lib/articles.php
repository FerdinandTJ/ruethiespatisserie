<?php
// Shared article storage and safe Markdown. No sessions/accounts are read here.
if(!defined('RUETHIES_ADMIN')&&!defined('RUETHIES_PUBLIC_RENDER')){http_response_code(404);exit;}
const ARTICLE_CATEGORIES=['panduan'=>['Panduan','Guides'],'ide-acara'=>['Ide Acara','Occasion ideas'],'tips-kue'=>['Tips Kue','Cake tips']];
function article_escape(string $text): string{return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function article_directory(): string{return dirname(__DIR__,2).'/ruethies-data';}
function article_file(): string{return article_directory().'/articles.json';}
function article_thumbnail_path(string $cover,int $width): string {
 if(!preg_match('~\Aimages/articles/[a-f0-9]{32}\.webp\z~D',$cover)||!in_array($width,[480,800],true))throw new DomainException('Foto artikel tidak valid.');
 return substr($cover,0,-5).'-'.$width.'.webp';
}
function article_image_info(string $path): ?array {
 if(!preg_match('~\Aimages/articles/[a-f0-9]{32}(?:-(?:480|800))?\.webp\z~D',$path))return null;
 $root=dirname(__DIR__);$file=$root.'/'.$path;
 if(is_link($root.'/images')||is_link($root.'/images/articles')||is_link($file)||!is_file($file))return null;
 $info=@getimagesize($file);
 return $info&&$info[2]===IMAGETYPE_WEBP&&$info[0]>=2&&$info[1]>=2&&$info[0]<=8192&&$info[1]<=8192&&$info[0]*$info[1]<=8000000?[$info[0],$info[1]]:null;
}
function article_empty(): array{return ['schema'=>1,'enabled'=>false,'revision'=>str_repeat('0',64),'items'=>[],'audit'=>[]];}
function article_slug(string $title): string{$latin=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$title);return trim(substr(preg_replace('/[^a-z0-9]+/','-',strtolower($latin===false?$title:$latin)),0,100),'-');}
function article_text(array $input,string $key,int $limit): string{$v=$input[$key]??'';if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||str_contains($v,"\0")||mb_strlen($v)>$limit)throw new DomainException('Isian '.$key.' tidak valid atau terlalu panjang.');return trim($v);}
function article_link(string $url): bool{return !preg_match('/[\x00-\x20\x7f\\\\]/',$url)&&((str_starts_with($url,'/')&&!str_starts_with($url,'//'))||(preg_match('~\Ahttps?://~i',$url)&&is_string(parse_url($url,PHP_URL_HOST))));}
function article_inline(string $text): string{
 $out='';$offset=0;$pattern='~\[([^\]\n]{1,200})\]\(([^\s)]{1,1500})\)|\*\*([^*\n]{1,2000})\*\*|(?<!\*)\*([^*\n]{1,2000})\*|`([^`\n]{1,2000})`~u';
 preg_match_all($pattern,$text,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
 foreach($matches as$m){$out.=article_escape(substr($text,$offset,$m[0][1]-$offset));$raw=$m[0][0];
  if($raw[0]==='['&&article_link($m[2][0]))$out.='<a href="'.article_escape($m[2][0]).'"'.(str_starts_with($m[2][0],'/')?'':' target="_blank" rel="noopener noreferrer"').'>'.article_escape($m[1][0]).'</a>';
  elseif(str_starts_with($raw,'**'))$out.='<strong>'.article_escape(substr($raw,2,-2)).'</strong>';
  elseif($raw[0]==='*')$out.='<em>'.article_escape(substr($raw,1,-1)).'</em>';
  elseif($raw[0]==='`')$out.='<code>'.article_escape(substr($raw,1,-1)).'</code>';
  else $out.=article_escape($raw);$offset=$m[0][1]+strlen($raw);
 }return $out.article_escape(substr($text,$offset));
}
function article_markdown(string $body): array{
 $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$body));$html='';$toc=[];$used=[];$paragraph=[];$list=null;
 $flush=static function()use(&$paragraph,&$html,&$list){if($paragraph){$html.='<p>'.article_inline(implode(' ',$paragraph)).'</p>';$paragraph=[];}if($list){$html.='</'.$list.'>';$list=null;}};
 for($i=0;$i<count($lines);$i++){$line=trim($lines[$i]);if($line===''){$flush();continue;}
  if(preg_match('/\A(#{2,3})\s+(.+)\z/u',$line,$m)){$flush();$base=article_slug($m[2])?:'section';$n=($used[$base]??0)+1;$used[$base]=$n;$id=$base.($n>1?'-'.$n:'');$tag=strlen($m[1])===2?'h2':'h3';if($tag==='h2')$toc[]=['id'=>$id,'text'=>$m[2]];$html.='<'.$tag.' id="'.article_escape($id).'">'.article_inline($m[2]).'</'.$tag.'>';continue;}
  if(str_contains($line,'|')&&isset($lines[$i+1])&&preg_match('/\A\s*\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?\s*\z/',$lines[$i+1])){$flush();$cells=static fn($row)=>array_map('trim',explode('|',trim(trim($row),'|')));$heads=$cells($line);$html.='<div class="article-table" tabindex="0" role="region" aria-label="Tabel" data-article-label-id="Tabel" data-article-label-en="Table"><table><thead><tr>';foreach($heads as$c)$html.='<th scope="col">'.article_inline($c).'</th>';$html.='</tr></thead><tbody>';$i++;while(isset($lines[$i+1])&&str_contains($lines[$i+1],'|')&&trim($lines[$i+1])!==''){$i++;$row=$cells($lines[$i]);$html.='<tr>';foreach($heads as$k=>$_)$html.='<td>'.article_inline($row[$k]??'').'</td>';$html.='</tr>';}$html.='</tbody></table></div>';continue;}
  if(preg_match('/\A(?:([-*])\s+|(\d+)\.\s+)(.+)\z/u',$line,$m)){if($paragraph){$html.='<p>'.article_inline(implode(' ',$paragraph)).'</p>';$paragraph=[];}$type=$m[1]!==''?'ul':'ol';if($list!==$type){if($list)$html.='</'.$list.'>';$html.='<'.$type.'>';$list=$type;}$html.='<li>'.article_inline($m[3]).'</li>';continue;}
  if(str_starts_with($line,'> ')){$flush();$html.='<blockquote><p>'.article_inline(substr($line,2)).'</p></blockquote>';continue;}
  if($list){$html.='</'.$list.'>';$list=null;}$paragraph[]=$line;
 }$flush();return ['html'=>$html,'toc'=>$toc];
}
function article_validate(array $row,bool $publishing=false): void{
 foreach(['id','title','slug','description','category','body','status','createdBy','updatedBy','createdAt','updatedAt','cover','coverAlt','lang','translationOf']as$key)if(!is_string($row[$key]??null)||!mb_check_encoding($row[$key],'UTF-8')||str_contains($row[$key],"\0"))throw new DomainException('Format artikel tidak valid.');
 if(!preg_match('/\A[a-z0-9-]{1,80}\z/D',$row['id'])||!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',$row['slug'])||strlen($row['slug'])>100||$row['title']===''||mb_strlen($row['title'])>180||mb_strlen($row['description'])>155||mb_strlen($row['body'])>60000||mb_strlen($row['coverAlt'])>180||!isset(ARTICLE_CATEGORIES[$row['category']])||!in_array($row['lang'],['id','en'],true)||!in_array($row['status'],['draft','published'],true))throw new DomainException('Periksa judul, slug, ringkasan (maksimal 155), kategori, bahasa, dan isi.');
 foreach(['title','description','coverAlt']as$k)if(strip_tags($row[$k])!==$row[$k]||preg_match('/[\x00-\x1f\x7f]/',$row[$k]))throw new DomainException('Judul, ringkasan dan alt harus teks biasa.');
 foreach(['createdAt','updatedAt']as$k)if(!preg_match('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\z/D',$row[$k])||strtotime($row[$k])===false)throw new DomainException('Waktu artikel tidak valid.');
 foreach(['publishedAt','deletedAt']as$k)if(!array_key_exists($k,$row))throw new DomainException('Metadata artikel tidak lengkap.');
 foreach(['publishedAt','deletedAt']as$k)if(($row[$k]??null)!==null&&(!is_string($row[$k])||!preg_match('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\z/D',$row[$k])||strtotime($row[$k])===false))throw new DomainException('Waktu artikel tidak valid.');
 if(!is_array($row['related']??null)||!array_is_list($row['related'])||count($row['related'])>3)throw new DomainException('Maksimal 3 tautan terkait.');foreach($row['related']as$url)if(!is_string($url)||!preg_match('~\A/(?:|menu/|snack-box-cikarang/|tentang-kami/|hubungi-kami/|cara-pesan/|menu/[a-z0-9]+(?:-[a-z0-9]+)*/)\z~D',$url))throw new DomainException('Tautan terkait tidak valid.');
 if($row['cover']!==''&&(!preg_match('~\Aimages/articles/[a-f0-9]{32}\.webp\z~D',$row['cover'])||$row['coverAlt']===''))throw new DomainException('Foto sampul harus memiliki alt yang valid.');
 if(($row['status']==='published'||$publishing)&&($row['description']===''||trim($row['body'])===''||preg_match('/\[\s*PERLU\s+KONFIRMASI\b/iu',implode(' ',[$row['title'],$row['description'],$row['body']]))))throw new DomainException('Penerbitan ditolak: lengkapi ringkasan/isi dan selesaikan semua penanda [PERLU KONFIRMASI].');
 if($row['status']==='published'||$publishing){$seenH2=false;foreach(explode("\n",$row['body'])as$line){if(preg_match('/\A#\s/',trim($line)))throw new DomainException('Gunakan ## untuk judul bagian, bukan H1.');if(preg_match('/\A##\s/',trim($line)))$seenH2=true;if(preg_match('/\A###\s/',trim($line))&&!$seenH2)throw new DomainException('Mulai judul bagian dengan ## sebelum memakai ###.');}}
 if($row['status']==='published'&&!is_string($row['publishedAt']??null))throw new DomainException('Tanggal terbit belum tersedia.');
}
function article_state_validate(array $state): void{
 if(($state['schema']??null)!==1||!is_bool($state['enabled']??null)||!is_string($state['revision']??null)||!preg_match('/\A[a-f0-9]{64}\z/D',$state['revision'])||!is_array($state['items']??null)||!array_is_list($state['items'])||count($state['items'])>1000)throw new RuntimeException('Penyimpanan artikel tidak valid.');
 if(!is_array($state['audit']??[])||!array_is_list($state['audit']??[])||count($state['audit']??[])>5000)throw new RuntimeException('Audit artikel tidak valid.');
 $ids=[];$slugs=[];foreach($state['items']as$row){if(!is_array($row))throw new RuntimeException('Penyimpanan artikel tidak valid.');try{article_validate($row);}catch(DomainException $e){throw new RuntimeException('Penyimpanan artikel tidak valid.');}if(isset($ids[$row['id']])||isset($slugs[$row['slug']]))throw new RuntimeException('Identitas artikel ganda.');$ids[$row['id']]=$row;$slugs[$row['slug']]=true;}
 foreach($state['items']as$row)if($row['translationOf']!==''&&(!isset($ids[$row['translationOf']])||$row['translationOf']===$row['id']||$ids[$row['translationOf']]['lang']===$row['lang']))throw new RuntimeException('Referensi terjemahan tidak valid.');
}
function article_read_unlocked(): array{
 $file=article_file();if(is_link(article_directory())||is_link($file)||file_exists($file)&&!is_file($file))throw new RuntimeException('Penyimpanan artikel tidak tersedia.');if(!is_file($file))return article_empty();if(filesize($file)>16*1024*1024)throw new RuntimeException('Penyimpanan artikel terlalu besar.');$raw=@file_get_contents($file);if($raw===false)throw new RuntimeException('Penyimpanan artikel tidak tersedia.');$state=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($state))throw new RuntimeException('Penyimpanan artikel tidak valid.');article_state_validate($state);return $state;
}
function article_lock(bool $write,callable $work){
 $dir=article_directory();if(!$write&&!is_file(article_file()))return $work();if($write&&!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException('Penyimpanan artikel tidak tersedia.');if(is_link($dir)||!is_dir($dir))throw new RuntimeException('Penyimpanan artikel tidak tersedia.');$path=$dir.'/articles.lock';if(is_link($path))throw new RuntimeException('Lock artikel tidak valid.');$fh=@fopen($path,'c');if(!$fh)throw new RuntimeException('Penyimpanan artikel tidak tersedia.');@chmod($path,0600);$until=microtime(true)+2;
 try{do{if(flock($fh,($write?LOCK_EX:LOCK_SH)|LOCK_NB)){try{return $work();}finally{flock($fh,LOCK_UN);}}usleep(10000);}while(microtime(true)<$until);throw new RuntimeException('Artikel sedang dipakai. Coba lagi.');}finally{fclose($fh);}
}
function article_read(): array{return article_lock(false,fn()=>article_read_unlocked());}
function article_backup(): ?string{
 if(!is_file(article_file()))return null;$dir=article_directory().'/backups';if(is_link($dir)||!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException('Backup artikel gagal.');$file=$dir.'/articles-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5)).'.json';if(!@copy(article_file(),$file)||!@chmod($file,0600))throw new RuntimeException('Backup artikel gagal.');return $file;
}
function article_write(array $state): void{
 article_state_validate($state);$raw=json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(strlen($raw)>16*1024*1024)throw new RuntimeException('Penyimpanan artikel mencapai batas aman.');$tmp=article_file().'.tmp.'.bin2hex(random_bytes(10));$fh=@fopen($tmp,'x');if(!$fh)throw new RuntimeException('Penyimpanan artikel gagal.');try{if(!chmod($tmp,0600))throw new RuntimeException('Penyimpanan artikel gagal.');$offset=0;while($offset<strlen($raw)){$n=fwrite($fh,substr($raw,$offset));if(!$n)throw new RuntimeException('Penyimpanan artikel gagal.');$offset+=$n;}if(!fflush($fh))throw new RuntimeException('Penyimpanan artikel gagal.');fclose($fh);$fh=null;if(!@rename($tmp,article_file()))throw new RuntimeException('Penyimpanan artikel gagal.');}finally{if(is_resource($fh))fclose($fh);if(is_file($tmp))unlink($tmp);}
}
function article_published(array $state): array{if(!$state['enabled'])return [];$items=array_values(array_filter($state['items'],fn($r)=>$r['status']==='published'&&$r['deletedAt']===null));usort($items,fn($a,$b)=>strcmp($b['publishedAt'],$a['publishedAt'])?:strcmp($a['id'],$b['id']));return $items;}
function article_find(array $state,string $id): ?array{foreach($state['items']as$row)if($row['id']===$id)return $row;return null;}
function article_minutes(string $body): int{return max(1,(int)ceil(count(preg_split('/\s+/u',trim(strip_tags($body)),-1,PREG_SPLIT_NO_EMPTY))/200));}
