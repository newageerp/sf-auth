<?php
namespace Newageerp\SfAuth\Iterface;

interface IAuthService
{
    public static function getInstance();

    public function getUser();

    public function setUser($user);
}