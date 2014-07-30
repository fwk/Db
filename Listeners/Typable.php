<?php
namespace Fwk\Db\Listeners;

use Fwk\Db\Accessor;
use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\AfterUpdateEvent;
use Fwk\Db\Events\BeforeSaveEvent;
use Fwk\Db\Events\BeforeUpdateEvent;
use Fwk\Db\Events\FreshEvent;
use Fwk\Db\Relation;
use Fwk\Db\Table;

class Typable
{
    protected $skipColumns = array();

    public function __construct(array $skipColumns = array())
    {
        $this->skipColumns = $skipColumns;
    }

    public function onFresh(FreshEvent $event)
    {
        $this->fromDatabaseToTypes($event->getEntity(), $event->getTable());
    }

    public function onBeforeSave(BeforeSaveEvent $event)
    {
        $this->fromTypesToDatabase($event->getEntity(), $event->getTable());
    }

    public function onBeforeUpdate(BeforeUpdateEvent $event)
    {
        $this->fromTypesToDatabase($event->getEntity(), $event->getTable());
    }

    public function onAfterSave(AfterSaveEvent $event)
    {
        $this->fromDatabaseToTypes($event->getEntity(), $event->getTable());
    }

    public function onAfterUpdate(AfterUpdateEvent $event)
    {
        $this->fromDatabaseToTypes($event->getEntity(), $event->getTable());
    }

    protected function fromDatabaseToTypes($entity, Table $table)
    {
        $accessor   = Accessor::factory($entity);
        $array      = $accessor->toArray();
        $platform   = $table->getConnection()->getDriver()->getDatabasePlatform();
        foreach ($array as $key => $value) {
            if (in_array($key, $this->skipColumns) || $value instanceof Relation) {
                continue;
            }

            $accessor->set($key, $table->getColumn($key)->getType()->convertToPHPValue($value, $platform));
        }
    }

    protected function fromTypesToDatabase($entity, Table $table)
    {
        $accessor   = Accessor::factory($entity);
        $array      = $accessor->toArray();
        $platform   = $table->getConnection()->getDriver()->getDatabasePlatform();
        foreach ($array as $key => $value) {
            if (in_array($key, $this->skipColumns) || $value instanceof Relation) {
                continue;
            }

            $accessor->set($key, $table->getColumn($key)->getType()->convertToDatabaseValue($value, $platform));
        }
    }
}