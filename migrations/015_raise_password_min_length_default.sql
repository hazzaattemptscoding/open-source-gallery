-- Migration: raise the stored password_min_length default from 8 to 12.
--
-- security.password_min_length was a dead setting: settings_registry had it,
-- nothing read it. app/controllers/admin/setup_wizard.php and
-- app/controllers/admin/admins.php each hardcoded their own minimum for the
-- same policy -- 12 for the first admin, 8 for every admin created
-- afterwards. Wiring the setting live in admins.php without correcting this
-- default would have silently weakened the effective policy for every
-- existing install the moment this shipped, since 8 is what was already
-- sitting in the row. Raising it to 12 makes both paths agree at the
-- stronger of the two existing values rather than the weaker one.

UPDATE settings_registry SET value = '12' WHERE category = 'security' AND key_name = 'password_min_length';
