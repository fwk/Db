# Events and Listeners

Fwk\Db API sends [Events](https://github.com/fwk/Events) before and after tasks execution, when possible. Any developer can then create listeners to modify the way the library behaves.

## Connection Events

These events are notified from the ```Connection``` object.

| Event                 |      Class                 |  Context                                     |
|-----------------------| ---------------------------|----------------------------------------------|
| connect               | ConnectEvent               | Fired when connection to DBRM is established |
| connectionError       | ConnectionErrorEvent       | Fired when connection encounters an error    |
| connectionStateChange | ConnectionStateChangeEvent | Fired when connection's state change         |
| disconnect            | DisconnectEvent            | Fired when connection to DBRM is closed      |
| beforeQuery           | BeforeQueryEvent           | Fired before a query is executed             |
| afterQuery            | AfterQueryEvent            | Fired after a query has been executed        |

For example, you can log all database queries with this simple listener:

``` php
use Psr\Log\LoggerInterface;
use Fwk\Db\Events\BeforeQueryEvent;

class QueryLogListener
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onBeforeQuery(BeforeQueryEvent $event)
    {
        $this->logger->debug($event->getQueryBridge()->getQueryString());
    }
}

/* ... */
$db->addListener(new QueryLogListener());
```

Other example, simple Closure listener:

``` php
$db->on('connect', function($event) {
  echo "Database is connected!";
});
``` 

## Entity Events

These events are notified from any [Model](./models.md) object.

| Event                 |      Class                 |  Context                                           |
|-----------------------| ---------------------------|----------------------------------------------------|
| beforeDelete          | BeforeDeleteEvent          | Fired before an entity is deleted from its table   |
| afterDelete           | AfterDeleteEvent           | Fired after an entity has been deleted             |
| beforeSave            | BeforeSaveEvent            | Fired before an entity is save in database         |
| afterSave             | AfterSaveEvent             | Fired after an entity is saved in database         |
| fresh                 | FreshEvent                 | Fired when an entity has just been fetched from db |

To describe permanents listeners, your model should implement the  ```getListeners()``` method from the ```EventSubscriber``` interface. This method should return a list of Listeners:

``` php
use Fwk\Db\Listeners\Typable;
use \stdClass;

/**
 * user model based on stdClass with typed values
 */
class User extends stdClass implements EventSubscriber
{
    public function getListeners()
    {
        return array(
            new Typable()
        );
    }
}
``` 

Otherwise, you can define listeners [per specific query](./query.md).

# Built-in Listeners

### Typable

This Listener brings strong values types (based on columns descriptions) to your [Models](./models.md). For example, if you have a DATETIME ```createdOn``` column, you'll get a PHP's [DateTime](http://php.net/manual/fr/class.datetime.php) object has a result.

**Beware!** When using ```Typable```, you should ALWAYS set values using their [according type](http://doctrine-dbal.readthedocs.org/en/latest/reference/types.html) or you'll get errors when saving your model. 

You can also skip desired columns by passing them to the constructor:

``` php
use Fwk\Db\Listeners\Typable;

public function getListeners()
{
    return array(
        new Typable(array('skippedColumn', 'otherColumn'))
    );
}
``` 

### Timestampable

This Listener automates the use of ```created_at``` and ```updated_at``` columns.

You can specify columns names to the constructor and the date format as well:
``` php
use Fwk\Db\Listeners\Timestampable;

public function getListeners()
{
    return array(
        new Timestampable('createdOn', 'updatedAt', 'Y-m-d H:i:s')
    );
}
``` 
