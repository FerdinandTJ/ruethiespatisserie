<?php
if(PHP_SAPI!=='cli')exit;
define('RUETHIES_ADMIN',true);require dirname(__DIR__).'/public-lib/articles.php';require dirname(__DIR__).'/admin/article-editor-lib.php';
function vt($ok,$name){if(!$ok)throw new RuntimeException($name);echo "PASS: $name\n";}
$html='<h2>Pilih sajian</h2><p>Menu <strong>manis</strong> dan <em>gurih</em>. <a href="/menu/">Menu</a></p><ul><li>Satu</li><li>Dua</li></ul><ol><li>Tiga</li></ol><blockquote><p>Tanyakan harga.</p></blockquote><h3>Catatan</h3><table><thead><tr><th>Jenis</th><th>Jumlah</th></tr></thead><tbody><tr><td>Snackbox</td><td>20</td></tr></tbody></table>';
$body=article_editor_markdown($html);$out=article_markdown($body)['html'];
foreach(['<h2','<h3','<strong>manis</strong>','<em>gurih</em>','href="/menu/"','<ul>','<ol>','<blockquote>','<table>','<td>20</td>']as$part)vt(str_contains($out,$part),'visual conversion retains '.$part);
vt(article_editor_markdown($out)===$body,'safe renderer and visual editor roundtrip including existing table');
$bad=article_editor_markdown('<p onclick="evil()">Hello<script>alert(1)</script><img src=x onerror=evil()><svg onload=evil()>x</svg><a href="javascript:evil()">Click</a><iframe src="https://bad.test"></iframe></p>');
vt(!preg_match('/script|onclick|onerror|iframe|svg|javascript:|evil\(/i',$bad),'active markup and unsafe links never enter stored Markdown');
vt(str_contains(article_editor_markdown('<p>Ruéthies &amp; kue 🍰</p>'),'Ruéthies & kue 🍰'),'Unicode preserved');
vt(article_input_body(['body'=>"## Original\n\n**Format**"])==="## Original\n\n**Format**",'legacy input unchanged');
foreach([['body_mode'=>[]],['body_mode'=>'visual','body_html'=>[]],['body_mode'=>'visual','body_html'=>str_repeat('a',60001)],['body_mode'=>'visual','body_html'=>'<ul><li>A<ul><li>B</li></ul></li></ul>'],['body_mode'=>'visual','body_html'=>'<p><strong>A <em>B</em></strong></p>']]as$input){$thrown=false;try{article_input_body($input);}catch(DomainException $e){$thrown=true;}vt($thrown,'unsupported or invalid input rejected without flattening silently');}

$emptyTable='<table><thead><tr><th>Kolom 1</th><th>Kolom 2</th></tr></thead><tbody><tr><td><br></td><td><br></td></tr><tr><td>Snackbox</td><td>20</td></tr></tbody></table><ul><li>Cek jumlah tamu</li></ul>';
$converted=article_editor_markdown($emptyTable);$rendered=article_markdown($converted)['html'];
vt(str_contains($rendered,'<td></td>')&&str_contains($rendered,'<td>Snackbox</td>')&&str_contains($rendered,'<li>Cek jumlah tamu</li>'),'new visual tables preserve blank cells, filled rows and following bullet list');
vt(article_editor_markdown($rendered)===$converted,'new table and bullet list survive visual/Markdown roundtrip');
