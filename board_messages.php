<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin'],'Legacy Communications Archive');require_once __DIR__.'/includes/legacy_communications_view.php';legacy_communications_page($pdo,false);
