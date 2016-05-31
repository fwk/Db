<?php
namespace Fwk\Db\Registry;


final class RegistryState
{
    const REGISTERED      = 0x01;
    const FRESH           = 0x02;
    const CHANGED         = 0x03;
    const UNKNOWN         = 0x04;
    const UNREGISTERED    = 0x05;
}