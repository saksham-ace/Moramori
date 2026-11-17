<?php
require __DIR__ . '/../includes/bootstrap.php';
logout_user();
flash('success', 'You have been signed out.');
redirect('');
