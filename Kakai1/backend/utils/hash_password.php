<?php
$password = 'admin123'; // ← change this to the password you want
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hashed password: " . $hash;
?>
