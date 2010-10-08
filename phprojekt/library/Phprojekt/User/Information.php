<?php
/**
 * Meta information about the User model.
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
 * @package    Phprojekt
 * @subpackage User
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */

/**
 * Meta information about the User model.
 *
 * The fields are hardcore.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage User
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */
class Phprojekt_User_Information extends Phprojekt_ModelInformation_Default
{
    /**
     * Sets a fields definitions for each field.
     *
     * @return void
     */
    public function setFields()
    {
        // username
        $this->fillField('username', 'Username', 'text', 1, 1, array(
            'required' => true,
            'length'   => 255));

        // password
        $this->fillField('password', 'Password', 'password', 0, 2, array(
            'length' => 50));

        // firstname
        $this->fillField('firstname', 'First name', 'text', 2, 3, array(
            'required' => true,
            'length'   => 255));

        // lastname
        $this->fillField('lastname', 'Last name', 'text', 3, 4, array(
            'required' => true,
            'length'   => 255));

        // email
        $this->fillField('email', 'Email', 'text', 0, 5, array(
            'length'   => 255));

        // language
        $this->fillField('language', 'Language', 'selectbox', 0, 6, array(
            'range'    => $this->getRangeValues(Phprojekt_LanguageAdapter::getLanguageList()),
            'required' => true,
            'default'  => 'en'));

        // timeZone
        $this->fillField('timeZone', 'Time zone', 'selectbox', 0, 7, array(
            'range'    => $this->getRangeValues(Phprojekt_Converter_Time::getTimeZones()),
            'required' => true,
            'default'  => '000'));

        // status
        $this->fillField('status', 'Status', 'selectbox', 4, 8, array(
            'range'    => 'A#Active|I#Inactive',
            'default'  => 'A'));

        // admin
        $this->fillField('admin', 'Admin', 'selectbox', 5, 9, array(
            'range'    => '0#No|1#Yes',
            'integer'  => true,
            'default'  => 0));
    }
}
