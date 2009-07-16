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
    public static function doLogin($user, $password)
    {
        $user = new User;
        $user->user = $user;
        $user->pass = $password;
        if ($user->load()->valid() === true) {
            $user->visits++;
            $user->lastvisit = date("Y-m-d H:i:s");
            $user->save();
            return true;
        }
        return false;
    }

    protected function pass_filter(&$password)
    {
        $password = md5($password);
    }

    protected function user_filter(&$user)
    {
        $oUser = $this->getOriginalValue('user');
        if (isset($this->ID) && $user!= $oUser) {
            throw new Exception("It is not possible to change username $oUser:$user");
        }
    }
}

?>
