<?php
/**
 * Fwk
 *
 * Copyright (c) 2011-2014, Julien Ballestracci <julien@nitronet.org>.
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
 * @category   Database
 * @package    Fwk
 * @subpackage Db
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2014 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.nitronet.org/fwk
 */
namespace Fwk\Db\Listeners;

use Fwk\Db\Accessor;
use Fwk\Db\Events\BeforeSaveEvent;
use Fwk\Db\Events\BeforeUpdateEvent;

/**
 * Timestampable
 *
 * This listeners helps an Entity to trace creation and update times.
 *
 * @category Listeners
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
class Timestampable
{
    /**
     * Name of the creation column
     *
     * @var string
     */
    protected $creationColumn = 'created_at';

    /**
     * Name of the update column
     *
     * @var string
     */
    protected $updateColumn = 'updated_at';

    /**
     * Date format
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Constructor
     *
     * @param string $creationColumn Name of the created_at column
     * @param string $updateColumn   Name of the updated_at column
     * @param string $dateFormat     Date format
     *
     * @return
     */
    public function __construct($creationColumn = 'created_at',
        $updateColumn = 'updated_at', $dateFormat = 'Y-m-d H:i:s'
    ) {
        $this->creationColumn   = $creationColumn;
        $this->updateColumn     = $updateColumn;
        $this->dateFormat       = $dateFormat;
    }

    /**
     * Listener triggered when a new entity is saved
     *
     * @param BeforeSaveEvent $event The BeforeSave event
     *
     * @return void
     */
    public function onBeforeSave(BeforeSaveEvent $event)
    {
        $date = new \DateTime();
        Accessor::factory(
            $event->getEntity()
        )->set($this->creationColumn, $date->format($this->dateFormat));
    }

    /**
     * Listener triggered when an existing entity is updated
     *
     * @param BeforeUpdateEvent $event The BeforeUpdate event
     *
     * @return void
     */
    public function onBeforeUpdate(BeforeUpdateEvent $event)
    {
        $date = new \DateTime();
        Accessor::factory(
            $event->getEntity()
        )->set($this->updateColumn, $date->format($this->dateFormat));
    }
}