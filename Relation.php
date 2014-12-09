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
 * @category  Database
 * @package   Fwk\Db
 * @author    Julien Ballestracci <julien@nitronet.org>
 * @copyright 2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      http://www.phpfwk.com
 */
namespace Fwk\Db;

/**
 * Relation Interface
 * 
 * A Relation describes a database relation between entities. Usually, Relations
 * and Foreign Keys work together to keep data integrity.
 * 
 * @category Interfaces
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
interface Relation
{
    /**
     * Fetch relation on top query
     */
    const FETCH_EAGER   = 'eager';
    
    /**
     * Fetch relation only when required
     */
    const FETCH_LAZY    = 'lazy';

    /**
     * Tells if data has been fetched yet
     * 
     * @return boolean
     */
    public function isFetched();

    /**
     * Tells if this is a LAZY relation
     * 
     * @return boolean
     */
    public function isLazy();

    /**
     * Tells if this is an EAGER relation
     * 
     * @return boolean
     */
    public function isEager();

    /**
     * Fetches (if necessary) relation's entities
     * 
     * @return void
     */
    public function fetch();

    /**
     * Prepares a top-query to fetch this relation's entities at the same time.
     * On the SQL side, this create some JOINs. 
     * This only works for FETCH_EAGER relations.
     * 
     * @param \Fwk\Db\Query $query      The top-query before execution
     * @param string        $columnName The column name of this relation
     * 
     * @return void
     */
    public function prepare(\Fwk\Db\Query $query, $columnName);

    /**
     * Defines a parent entity for this relation.
     * 
     * @param mixed                  $object Parent object
     * @param \Fwk\Events\Dispatcher $evd    Event's dispatcher for parent
     *
     * @return void
     */
    public function setParent($object, \Fwk\Events\Dispatcher $evd);

    /**
     * Returns defined entity for this relation.
     * 
     * @return mixed
     */
    public function getEntity();

    /**
     * Returns the foreign column name
     * 
     * @return string
     */
    public function getForeign();

    /**
     * Returns the local column name
     * 
     * @return string
     */
    public function getLocal();

    /**
     * Change the "fetched" state of this relation.
     * 
     * @param boolean $bool Fetched or not Fetched ? 
     * 
     * @return void
     */
    public function setFetched($bool);

    /**
     * Defines this relation as EAGER or LAZY.
     * 
     * @param string $mode Fetch mode {@see Relation::FETCH_ Constants}
     * 
     * @return void
     */
    public function setFetchMode($mode);

    /**
     * Transforms relation's data to a simple PHP array.
     * 
     * @return array
     */
    public function toArray();

    /**
     * Returns relation's data to a Traversable iterator.
     * {@see \Traversable}
     * 
     * @return \ArrayIterator
     */
    public function getIterator();

    /**
     * Return the table name for this relation
     *
     * @return string
     */
    public function getTableName();

    /**
     * Returns this relation's registry
     *
     * @return Registry
     */
    public function getRegistry();

    /**
     * Defines a Registry for this relation
     *
     * @param Registry $registry The registry
     *
     * @return Relation
     */
    public function setRegistry(Registry $registry);
}