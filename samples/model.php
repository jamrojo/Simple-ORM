<?php

class User extends DB
{
    public $user;
    public $pass;
    public $visits;
    public $lastvisit;

    /**
     *  Performs login to the user and password
     *
     *  @param string $user     Username
     *  @param string $password Password
     */
    public static function doLogin($username, $password)
    {
        $user = new User;
        $user->user = $username;
        $user->pass = $password;
        if ($user->load()->valid() === true) {
            $user->visits++;
            $user->lastvisit = date("Y-m-d H:i:s");
            $user->save();
            return true;
        }
        return false;
    }

    /**
     *  We store the password's md5.
     *
     */
    protected function pass_filter(&$password)
    {
        $password = md5($password);
    }

    /**
     *  The username can't be changed.
     *
     */
    protected function user_filter(&$user)
    {
        $oUser = $this->getOriginalValue('user');
        if (isset($this->ID) && $user!= $oUser) {
            throw new Exception("It is not possible to change username $oUser:$user");
        }
    }
}

?>
