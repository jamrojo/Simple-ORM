<?php

require "../DB.php";
require "model.php";

DB::setUser("root");
DB::setDB("sample");
DB::setDriver("mysql");

/*$user = new User;
$user->user = "david";
$user->pass = "david";
$user->save();*/

$users = new User;
foreach ($users->load() as $user) {
    $user->save();
}
?>
