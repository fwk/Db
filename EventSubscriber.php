<?php
namespace Fwk\Db;


interface EventSubscriber
{
    /**
     * @return array
     */
    public function getListeners();
}