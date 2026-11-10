#!/usr/bin/env bash
# Run from public_html/staging. Preserve hosting JSON and optional hPanel Basic Auth.
set -euo pipefail
if [ "$(basename "$PWD")" != staging ] || [ "$(basename "$(dirname "$PWD")")" != public_html ]; then
  echo 'STOP: jalankan dari public_html/staging.' >&2; exit 1
fi
if [ "$(git branch --show-current)" != staging ]; then echo 'STOP: branch harus staging.' >&2; exit 1; fi
if ! git diff --cached --quiet; then echo 'STOP: ada perubahan staged; tinjau dahulu.' >&2; exit 1; fi
git diff --name-only | while IFS= read -r changed; do
  case "$changed" in .htaccess|menu.json|faq.json|atmosphere.json|index.html|llms.txt) ;; *) echo "STOP: perubahan lain perlu ditinjau: $changed" >&2; exit 1;; esac
done
umask 077
STAGING_BACKUP_DIR="../../deploy-backups/staging-$(date +%Y%m%d-%H%M%S)-$$"
mkdir -p "$STAGING_BACKUP_DIR"
for file in .htaccess menu.json faq.json atmosphere.json; do cp "$file" "$STAGING_BACKUP_DIR/$file"; done
git rev-parse HEAD > "$STAGING_BACKUP_DIR/commit.txt"
tar --exclude=.git -czf "$STAGING_BACKUP_DIR/staging.tgz" -C .. staging
if [ -d ../../ruethies-data-staging ]; then tar -czf "$STAGING_BACKUP_DIR/private.tgz" -C ../.. ruethies-data-staging; fi
php -r '
$s=file_get_contents(".htaccess");$i=strpos($s,"# DO NOT REMOVE THIS LINE AND THE LINES BELOW PWPROTECTID:");
if($i===false){
  if(preg_match("/^\\s*(?:AuthType|AuthName|AuthUserFile|Require\\s+valid-user)\\b/im",$s)){fwrite(STDERR,"STOP: aturan autentikasi tanpa penanda hPanel perlu ditinjau.\n");exit(1);}
  if(file_put_contents($argv[1],"")===false)exit(1);
  echo "Staging tanpa password protection; pengaturan ini dipertahankan.\n";
  exit(0);
}
$b=substr($s,$i);
if(!preg_match("/AuthType\\s+Basic/i",$b)||!preg_match("/AuthUserFile\\s+\\S+/",$b)||!preg_match("/Require\\s+valid-user/i",$b)){fwrite(STDERR,"STOP: blok password protection belum lengkap.\n");exit(1);}
if(file_put_contents($argv[1],$b)===false)exit(1);
' "$STAGING_BACKUP_DIR/auth-block.txt"
# Restore protection/data if a fetch, stash or checkout fails. Never stash-pop over
# freshly upgraded templates; the hosting data is explicitly restored instead.
restore_on_error() {
  cp "$STAGING_BACKUP_DIR/.htaccess" .htaccess
  chmod 644 .htaccess
  for file in menu.json faq.json atmosphere.json; do cp "$STAGING_BACKUP_DIR/$file" "$file"; done
  echo "Update terhenti. Proteksi/konten dipulihkan. Backup: $STAGING_BACKUP_DIR" >&2
}
trap restore_on_error EXIT
umask 022
git stash push -m 'Hosting staging before update' -- .htaccess menu.json faq.json atmosphere.json index.html llms.txt
git pull --ff-only origin staging
for file in menu.json faq.json atmosphere.json; do cp "$STAGING_BACKUP_DIR/$file" "$file"; done
cat "$STAGING_BACKUP_DIR/auth-block.txt" >> .htaccess
chmod 644 .htaccess menu.json faq.json atmosphere.json
touch .htaccess
trap - EXIT
echo "Kode staging diperbarui. Konten dan proteksi hPanel dipertahankan. Backup: $STAGING_BACKUP_DIR"
echo 'Jangan stash pop. Lanjutkan CLI upgrade sesuai MIDTRANS-SANDBOX.md.'
