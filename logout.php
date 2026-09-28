<?php
require __DIR__ . '/includes/bootstrap.php';
if (uid()) log_activity('auth', 'Signed out');
logout_user();
flash('success', 'You have been signed out.');
redirect('login.php');
