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
 * @subpackage Mysql
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Mysql\Schemas;

use Fwk\Db\Connection,
    Fwk\Db\Schema,
    Fwk\Db\Column,
    Fwk\Db\AbstractSchema,
    Fwk\Db\Table;

class Auto extends AbstractSchema implements Schema
{
    /**
     * Declared entities for tables
     * 
     * @var array
     */
    protected $entities;


    public function getTable($tableName) {
        $query      = $this->getConnection()
                           ->getDriver()
                           ->rawQuery(sprintf('SHOW FULL FIELDS FROM %s', $tableName));
        $cols       = $query->fetchAll(\PDO::FETCH_CLASS);
        $columns    = array();

        foreach ($cols as $col) {
            $name = $col->Field;
            $key = $col->Key;
            $default = $col->Default;
            $autoIncrement = (empty($col->Extra) ? false : true);
            $null = ($col->Null == 'NO' ? false : true);
            $type = $col->Type;

            if (strpos($type, '(') != false) {
                list($type, $size) = explode('(', $type);
                $size = (int)rtrim($size, ')');
            }

            if ($key == 'PRI')
                $key = Column::INDEX_PRIMARY;

            elseif ($key == 'UNI')
                $key = Column::INDEX_UNIQUE;

            elseif ($key == 'MUL')
                $key = Column::INDEX_INDEX;

            else
                $key = Column::INDEX_NONE;

            $column = self::columnFactory($name, $type, $size, $null, $default, $key, $autoIncrement);

            $columns[$name] = $column;
        }

        $table      = new Table($tableName);
        $table->addColumns($columns);
        $table->setDefaultEntity($this->getDeclaredEntity($tableName));
        
        return $table;
    }

    public static function columnFactory($name, $columnTypeName, $size = null, $null = false, $default = null, $key = null, $autoIncrement = false) {

        $typename = strtolower($columnTypeName);
        switch ($typename) {

            case 'tinyint':
            case 'mediumint':
            case 'bigint':
            case 'smallint':
            case 'int':
            case 'float':
            case 'decimal':
            case 'double':
                $type = 'Numeric';
                break;

            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
                $type = 'Blob';
                break;

            case 'char':
            case 'varchar':
            case 'text':
            case 'longtext':
            case 'mediumtext':
            case 'tinytext':
            case 'enum':
                $type = 'Text';
                break;

            case 'date':
            case 'datetime':
            case 'time':
            case 'year':
            case 'timestamp':
                $type = 'Date';
                break;

            case 'binary':
            case 'varbinary':
                $type = 'Binary';
                break;

            case 'relation':
                $type = 'Relation';
                break;

            default:
                throw new \RuntimeException(sprintf('Unknown column type: %s', $columnTypeName));
        }

        $className = sprintf('Fwk\Db\Columns\%sColumn', $type);
        $class = new $className($name, $columnTypeName, $size, $null, $default, $key, $autoIncrement);

        return $class;
    }

    public function getTables() {

        return array();
    }

}