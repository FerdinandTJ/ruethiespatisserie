<?php defined('RUETHIES_ADMIN') or die('Langsung tidak diizinkan.'); ?>
<?php if(!function_exists('security_require_reauth')): ?>
<label>Kata sandi pemilik<input type="password" name="current_password" autocomplete="current-password" required></label>
<?php if(!empty($me['totp'])): ?><label>Kode Authenticator<input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label><?php endif; ?>
<?php else: ?><p class="sub">Tindakan ini memerlukan verifikasi keamanan yang masih berlaku. <a href="index.php?p=verifikasi">Verifikasi keamanan</a></p><?php endif; ?>
