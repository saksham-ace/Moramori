<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) json_out(['ok' => false, 'message' => 'Bad request'], 405);
csrf_check();
$email = strtolower(post('email'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'message' => 'Please enter a valid email.']);
q('INSERT IGNORE INTO newsletter (email) VALUES (?)', [$email]);
json_out(['ok' => true, 'message' => 'Thank you — you are on the list.']);
