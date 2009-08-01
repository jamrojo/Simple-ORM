<?php

require "../../DB.php";
require "model.php";

DB::setDB("sample.db");
DB::setDriver("sqlite");

try {
    DB::begin();
    for ($i = 0; $i < 100; $i++) {
        $user = new User(array("user"=>"foobar{$i}", "pass" => "nothing"));
        $user->save();
    }
    DB::commit();
} catch (PDOException $e) {
    /* probably the table is already populated */
    DB::rollback();
}

/* Load users with `user` foobar1 or foobar2 and change its password */
$users = new User;
$users->user = array("foobar1", "foobar2");
DB::begin();
foreach ($users->load() as $user) {
    $user->pass = "pass";
    $user->save();
}
DB::commit();

/* now let's try some queries, the first should work, the other should fail */

foreach (array("foobar1"=>"pass", "foobar10" => "pass") as $user => $pass) {
    if (User::doLogin($user, $pass)) {
        print "Welcome user $user\n";
    } else {
        print "Bad username or password ($user)\n";
    }
}
?>
