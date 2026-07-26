<?php

use src\User;
use src\Connectbd;

require_once __DIR__ . '/admin-session.php';
require_once __DIR__ . '/csrf.php';

function verifyConnection($redirection)
{
    startAdminSession();
    $userId = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id'])
        ? filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($userId === false) {
        header('location: /management/users/login.php?redirect=' . $redirection);
        exit;
    }
}

function checkAdminAccess($id)
{
    $cnx = Connectbd::getConnection();
    $manager = new User($cnx);
    $user = $manager->getUserById($id);
    if ($user) {
        if ((int) $user['role'] !== 1) {
            header('Location: /management/orders/');
            exit();
        }
    } else {
        header('Location: /error.php?code=401');
        exit();
    }
}


function checkIsActive($id)
{
    $cnx = Connectbd::getConnection();
    $manager = new User($cnx);
    $user = $manager->getUserById($id);
    if ($user) {
        $_SESSION['role'] = (int) $user['role'];
        $_SESSION['is_active'] = (int) $user['is_active'];
        if ($user['is_active'] == 0) {
            header('Location: /error.php?code=403');
            exit();
        }
    } else {
        header('Location: /error.php?code=401');
        exit();
    }
}
