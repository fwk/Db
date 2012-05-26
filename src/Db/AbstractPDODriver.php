<?php
/**
 * Fwk
 *
 * Copyright (c) 2011-2012, Julien Ballestracci <julien@nitronet.org>.
 * All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 * PHP Version 5.3
 * 
 * @package    Fwk
 * @subpackage Db
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db;

use Fwk\Db\Connection;
use Fwk\Events\Event;

abstract class AbstractPDO {

    /**
     * The connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * The PDO handle
     * 
     * @var \PDO
     */
    protected $handle;

    
    /**
     * Connects to a PDO compatible database
     *
     * @return boolean
     */
    public function connect() {
        $handle     = $this->getHandle();
        if($handle instanceof \PDO) {
            return true;
        }

        return false;
    }

    /**
     * Returns the connection
     *
     * @return Connection
     */
    public function getConnection() {
        if(!isset($this->connection))
                    throw new \RuntimeException (sprintf('No connection defined for this driver'));
        
        return $this->connection;
    }

    /**
     * Defines a connection to handle
     *
     * @param Connection $connection
     * @return AbstractPDO
     */
    public function setConnection(Connection $connection) {
        $this->connection   = $connection;

        return $this;
    }


    /**
     * Executes a plain SQL query and return results without transformation
     * 
     * @param string $query
     * @return mixed
     */
    public function rawQuery($query) {

        return $this->getHandle()->query($query);
    }


    /**
     * Returns the PDO handle 
     *
     * @return \PDO
     */
    public function getHandle() {
        if(!isset($this->handle)) {
            $connection     = $this->getConnection();

            $dsn            = $connection->get('dsn');
            $user           = $connection->get('username', null);
            $password       = $connection->get('password', null);
            $charset        = $connection->get('charset', null);

            if(empty($dsn))
                throw new \RuntimeException (sprintf('A dsn is required to connect to a database'));

            try {
                $pdo        = new \PDO($dsn, $user, $password);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                
                $connection->set('password', '******');

                if(!empty($charset)) {
                    $pdo->exec(sprintf("SET NAMES %s", $pdo->quote($charset)));
                }
                
                $this->handle = $pdo;
                $this->getConnection()->notify(new Event(Driver::EVENT_CONNECT, array('driver' => $this)));
                
            } catch(\PDOException $e) {
                $this->getConnection()->notify(new Event(Driver::EVENT_ERROR, array('exception' => $e)));
            }
        }

        return $this->handle;
    }

    /**
     * Escapes a string
     *
     * @alias quote
     * @param string $string
     */
    public function escape($string) {

        return $this->getHandle()->quote($string);
    }

    public function getLastInsertId() {

        return $this->getHandle()->lastInsertId();
    }
    
    public function beginTransaction() {

        $this->getHandle()->beginTransaction();
    }

    public function commit() {

        $this->getHandle()->commit();
    }

    public function rollBack() {
        $this->getHandle()->rollBack();
    }
}