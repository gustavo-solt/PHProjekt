<?php
/**
 * Unit test
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @category   PHProjekt
 * @package    UnitTests
 * @subpackage Default
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Eduardo Polidor <polidor@mayflower.de>
 */

require_once 'PHPUnit/Framework.php';

/**
 * Tests Default Model class
 *
 * @category   PHProjekt
 * @package    UnitTests
 * @subpackage Default
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Eduardo Polidor <polidor@mayflower.de>
 * @group      default
 * @group      model
 * @group      default-model
 */
class Phprojekt_DefaultModelDefault_Test extends PHPUnit_Framework_TestCase
{
    /**
     * Test valid method
     *
     */
    public function testDefaultModelsDefault()
    {
        $defaultModel = Phprojekt_Loader::getModel('Default', 'Default');
        $this->assertEquals($defaultModel->valid(), false);
        $this->assertEquals($defaultModel->save(), false);
        $this->assertEquals($defaultModel->getRights(), array());
        $this->assertEquals($defaultModel->recordValidate(), true);
        $this->assertEquals($defaultModel->find(), null);
        $this->assertEquals($defaultModel->fetchAll(), null);
        $this->assertEquals($defaultModel->current(), null);
        $this->assertEquals($defaultModel->rewind(), null);
        $this->assertEquals($defaultModel->next(), null);
        $this->assertEquals($defaultModel->getInformation(), null);
        $this->assertEquals($defaultModel->key(), null);
    }
}
