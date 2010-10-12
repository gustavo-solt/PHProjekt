<?php
/**
 * Default model
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
 * @subpackage Model
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Gustavao Solt <gustavo.solt@mayflower.de>
 */

/**
 * Default model
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage Model
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Gustavao Solt <gustavo.solt@mayflower.de>
 */
abstract class Phprojekt_Model_Default extends Phprojekt_ActiveRecord_Abstract implements Phprojekt_Model_Interface
{
    /**
     * The standard information manager that manage the field definitions.
     *
     * @var Phprojekt_ModelInformation_Interface
     */
    protected $_informationManager;

    /**
     * Validate object.
     *
     * @var Phprojekt_Model_Validate
     */
    protected $_validate = null;

    /**
     * Initialize new model.
     *
     * @param array $db Configuration for Zend_Db_Table.
     *
     * @return void
     */
    public function __construct($db = null)
    {
        if (null === $db) {
            $db = Phprojekt::getInstance()->getDb();
        }
        parent::__construct($db);

        $this->_validate           = Phprojekt_Loader::getLibraryClass('Phprojekt_Model_Validate');
        $this->_informationManager = $this->setInformation();
    }

    /**
     * Define the clone function for prevent the same point to same object.
     *
     * @return void
     */
    public function __clone()
    {
        parent::__clone();

        $this->_validate           = Phprojekt_Loader::getLibraryClass('Phprojekt_Model_Validate');
        $this->_informationManager = $this->setInformation();
    }

    /**
     * Define the information manager.
     *
     * @return Phprojekt_ModelInformation_Interface An instance of Phprojekt_ModelInformation_Interface.
     */
    public function setInformation()
    {
        return Phprojekt_Loader::getLibraryClass('Phprojekt_ModelInformation_Default');
    }

    /**
     * Get the information manager.
     *
     * @see Phprojekt_Model_Interface::getInformation()
     *
     * @return Phprojekt_ModelInformation_Interface An instance of Phprojekt_ModelInformation_Interface.
     */
    public function getInformation()
    {
        return $this->_informationManager;
    }

    /**
     * Save the rights for the current model.
     *
     * @param array $rights Array of user IDs with the bitmask access.
     *
     * @return void
     */
    public function saveRights($rights)
    {
    }

    /**
     * Validate the current record.
     *
     * @return boolean True for valid.
     */
    public function recordValidate()
    {
        $data   = $this->_data;
        $fields = $this->getInformation()->getFieldDefinition(Phprojekt_ModelInformation_Default::ORDERING_FORM);

        return $this->_validate->recordValidate($this, $data, $fields);
    }

    /**
     * Returns the error data.
     *
     * @return array Array with errors.
     */
    public function getError()
    {
        return (array) $this->_validate->error->getError();
    }

    /**
     * Delete the upload files if there is any.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleteUploadFiles();

        parent::delete();
    }

    /**
     * Delete all the files uploaded in the upload fields.
     *
     * @return void
     */
    public function deleteUploadFiles()
    {
        // Is there is any upload file, -> delete the files from the server
        foreach ($this as $field => $value) {
            $field = Phprojekt_ActiveRecord_Abstract::convertVarFromSql($field);
            if ($this->getInformation()->getType($field) == 'upload') {
                $filesField = $value;
                $files      = explode('||', $filesField);
                foreach ($files as $file) {
                    $md5Name          = substr($file, 0, strpos($file, '|'));
                    $fileAbsolutePath = Phprojekt::getInstance()->getConfig()->uploadPath . $md5Name;
                    if (!empty($md5Name) && file_exists($fileAbsolutePath)) {
                        unlink($fileAbsolutePath);
                    }
                }
            }
        }
    }

    /**
     * Assign a value to a var using some validations deppend on the type.
     *
     * @param string $varname Name of the var to assign.
     * @param mixed  $value   Value for assign to the var.
     *
     * @return void
     */
    public function __set($varname, $value)
    {
        $var  = Phprojekt_ActiveRecord_Abstract::convertVarToSql($varname);
        $info = $this->info();

        if (true == isset($info['metadata'][$var])) {
            $type  = $info['metadata'][$var]['DATA_TYPE'];
            $value = Phprojekt_Converter_Value::set($type, $value);
        } else {
            $value = Cleaner::sanitize('string', $value);
        }

        parent::__set($varname, $value);
    }

    /**
     * Get a value of a var.
     * Is the var is a float, return the locale float.
     *
     * @param string $varname Name of the var to assign.
     *
     * @return mixed Value of the var.
     */
    public function __get($varname)
    {
        $info  = $this->info();
        $value = parent::__get($varname);
        $var   = Phprojekt_ActiveRecord_Abstract::convertVarToSql($varname);

        if (true == isset($info['metadata'][$var])) {
            $type  = $info['metadata'][$var]['DATA_TYPE'];
            $value = Phprojekt_Converter_Value::get($type, $value);
        }

        return $value;
    }
}
