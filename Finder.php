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

class Finder 
{
    /**
     * Results 
     * 
     * @var ResultSet
     */
    protected $resultSet;

    /**
     * Table to navigate
     *
     * @var Table
     */
    protected $table;

    /**
     * Current Connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Current query
     *
     * @var Query
     */
    protected $query;

    /**
     * Query parameters
     * 
     * @var array
     */
    protected $params;
    
    /**
     * Constructor
     *
     * @param Table      $table
     * @param Connection $connection
     *
     * @return void
     */
    public function __construct(Table $table, Connection $connection = null)
    {
        $query = Query::factory()
                    ->select()
                    ->from(sprintf('%s %s', $table->getName(), 'f'));
        
        $this->table        = $table;
        $this->connection   = $connection;
        $this->query        = $query;
        $this->params       = array();
    }

    /**
     * Defines a connection
     *
     * @param Connection $connection
     *
     * @return Finder
     */
    public function setConnection(Connection $connection)
    {
        $this->connection   = $connection;

        return $this;
    }

    /**
     * Returns current connection
     *
     * @throws Exception
     *
     * @return Connection
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            throw new Exception('No connection is defined');
        }

        return $this->connection;
    }

    /**
     *
     * @param array $identifiers
     * @return ResultSet 
     */
    public function find(array $identifiers)
    {
        if(isset($this->resultSet)) {
            throw new Exception('Finder already executed');
        }
        
        $this->query->where('1 = 1');
        foreach($identifiers as $key => $value) {
            $this->query->andWhere(sprintf('f.%s = ?', $key));
            $this->params[] = $value;
        }
        
        return $this->getResultSet();
    }

    /**
     *
     * @param mixed $identifiers
     * @return mixed 
     */
    public function one($identifiers)
    {
        if(isset($this->resultSet)) {
            throw new Exception('Finder already executed');
        }
        
        $ids = $this->table->getIdentifiersKeys();
        if(!is_array($identifiers)) {
            if(count($ids) === 1) {
                $identifiers = array($ids[0] => $identifiers);
            } else {
                $identifiers = array($identifiers);
            }
        }
        
        if(count($ids) != count($identifiers)) {
            throw new Exceptions\MissingIdentifier(sprintf('Table has %u identifiers (%s), only %u provided', count($ids), implode(', ', $ids), count($identifiers)));
        }
        
        $this->query->where('1 = 1');
        foreach($ids as $key) {
            if(!isset($identifiers[$key])) {
                 throw new Exceptions\MissingIdentifier(sprintf('Missing required identifier "%s"', $key));
            }
            
            $this->query->andWhere(sprintf('f.%s = ?', $key));
            $this->params[] = $identifiers[$key];
        }
        
        $res = $this->getResultSet();
        if($res->count() >= 1) {
            return $res[0];
        }
        
        return null;
    }

    /**
     *
     * @return ResultSet
     */
    public function all()
    {
         return $this->getResultSet();
    }

    /**
     *
     * @return ResultSet 
     */
    public function getResultSet()
    {
        if(!isset($this->resultSet)) {
            $this->resultSet = $this->connection->execute($this->query, $this->params);
        }
        
        return $this->resultSet;
    }
}
