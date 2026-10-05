<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin'],'Cash Receipt');
$workspaceBook='CRB';require __DIR__.'/includes/accounting_entry_page.php';
