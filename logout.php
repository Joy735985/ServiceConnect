<?php
require 'functions.php';
session_destroy();
header("Location: login.php");
exit;
