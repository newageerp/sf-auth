<?php
namespace Newageerp\SfAuth\Service;

use Newageerp\SfAuth\Iterface\IAuthService;
use Newageerp\SfBaseEntity\Object\BaseUser;

class AuthService implements IAuthService
{
    private static $_instance = null;

    protected ?BaseUser $user = null;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (!(self::$_instance instanceof AuthService)) {
            self::$_instance = new AuthService();
        }

        return self::$_instance;
    }

    /**
     * Get the value of user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @return  self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }
}