<?php
namespace Newageerp\SfAuth\Interface;

interface IAuthService
{
    public static function getInstance();

    public function getUser();

    public function setUser($user);
}