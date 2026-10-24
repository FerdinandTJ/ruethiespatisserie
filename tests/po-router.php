<?php
// Router khusus PHP built-in server untuk tes HTTP; hosting memakai .htaccess.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~\A/pre-order(?:/status)?/?\z~', $path)) {
  require $_SERVER['DOCUMENT_ROOT'] . '/po.php';
  return true;
}
return false;
