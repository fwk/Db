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

/**
 * Represents a database driver
 *
 */
interface Driver
{
    /**
     * Connects to the database
     *
     * @return boolean true on success
     */
    public function connect();

    /**
     * Ends connection to database
     *
     * @return boolean true on success
     */
    public function disconnect();

    /**
     * Defines the connection for this driver
     *
     * @param  \Fwk\Db\Connection $connection The connection object
     * @return void
     */
    public function setConnection(\Fwk\Db\Connection $connection);

    /**
     * Returns the connection defined for this driver
     *
     * @return \Fwk\Db\Connection
     */
    public function getConnection();

    /**
     * Executes a query in raw format and return results without transformation
     *
     * @param  mixed $query Raw query
     * @return mixed
     */
    public function rawQuery($query);

    /**
     * Returns last insert ID if supported by the DB server
     *
     * @return integer
     */
    public function getLastInsertId();

    /**
     * Executes an ORM query
     *
     * @return mixed
     */
    public function query(\Fwk\Db\Query $query, array $params = array(), array $options = array());

    /**
     * Begins a transaction
     *
     * @return void
     */
    public function beginTransaction();

    /**
     * Commits a transaction
     *
     * @return void
     */
    public function commit();

    /**
     * Cancel a transaction
     *
     * @return void
     */
    public function rollBack();
}
