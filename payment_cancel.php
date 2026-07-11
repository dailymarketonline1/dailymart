<?php
// payment_cancel.php
session_start();
header('Location: cart.php?payment=cancelled');
exit;
?>
