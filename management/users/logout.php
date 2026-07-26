<?php 

// ---------------------------------------------------------------------------//
//                           Logique de déconnexion                           //
// ---------------------------------------------------------------------------//
require_once '../../utils/admin-session.php';
destroyAdminSession();
header("location:login.php");
