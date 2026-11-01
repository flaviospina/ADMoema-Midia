<?php
// public/logout.php — encerra a sessão (somente via POST com token CSRF)
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

require_post();
Auth::logout();
Session::start();
flash('info', 'Você saiu do sistema.');
redirect('login.php');
