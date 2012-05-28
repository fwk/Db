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
namespace Fwk\Db\Columns;

use Fwk\Db\Column;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

abstract class AbstractColumn
{
    protected $name;

    protected $typeName;

    protected $size = null;

    protected $null = false;

    protected $default = null;

    protected $key = null;

    protected $autoIncrement = false;

    /**
     *
     * @param  string  $name
     * @param  string  $typeName
     * @param  integer $size
     * @param  boolean $null
     * @param  string  $default
     * @param  integer $key
     * @param  boolean $autoIncrement
     * @return void
     */
    public function __construct($name, $typeName, $size = null, $null = null, $default = null, $key = Column::INDEX_NONE, $autoIncrement = false)
    {
        $this->name = $name;
        $this->typeName = $typeName;
        $this->size = (int) $size;
        $this->null = (bool) $null;
        $this->default = (string) $default;

        if(!in_array($key, array(Column::INDEX_INDEX, Column::INDEX_NONE, Column::INDEX_PRIMARY, Column::INDEX_UNIQUE)))
                throw new Exceptions\ColumnException(sprintf("Unknown index type: '%s' on column '%s'", $key, $name));

        $this->key = $key;
        $this->autoIncrement = (bool) $autoIncrement;
    }

    public function isAutoIncrement()
    {
        return $this->autoIncrement;
    }

    public function isIndex($indexType = Column::INDEX_INDEX)
    {
        return ($this->key === $indexType);
    }

    public function isPrimary()
    {
        return $this->isIndex(Column::INDEX_PRIMARY);
    }

    public function isNull()
    {
        return $this->null;
    }

    public function hasDefault()
    {
        return ($this->default === null ? false : true);
    }

    public function getName()
    {
        return $this->name;
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function getTypeName()
    {
        return $this->typeName;
    }

    abstract public function getTypedValue($value);
}
