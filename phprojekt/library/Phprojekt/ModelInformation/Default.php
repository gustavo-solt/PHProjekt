<?php
/**
 * A default module that can be feed with a array and provides
 * all necessary methods from that
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
 * @subpackage ModelInformation
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Gustavao Solt <solt@mayflower.de>
 */

/**
 * A default module that can be feed with a array and provides
 * all necessary methods from that
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage ModelInformation
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Gustavao Solt <solt@mayflower.de>
 */
class Phprojekt_ModelInformation_Default implements Phprojekt_ModelInformation_Interface
{
    /**
     * The fields given by the ModelInformation interface
     * are used by a list view and therefore ordered in that way.
     */
    const ORDERING_LIST = 1;

    /**
     * The fields given by the ModelInformation interface
     * are used by a form and therefore ordered in that way.
     */
    const ORDERING_FORM = 2;

    /**
     * The fields given by the ModelInformation interface
     * are used by a filter and therefore ordered in that way.
     */
    const ORDERING_FILTER = 3;

    /**
     * The fields given by the ModelInformation interface
     * are used by something undeclared, therefore we must use a default value.
     */
    const ORDERING_DEFAULT = Phprojekt_ModelInformation_Default::ORDERING_LIST;

    /**
     * Array that contains the field values.
     *
     * @var array
     */
    protected $_fields = null;

    /**
     * An array that defines the default values used when
     * setting the form or list fields if they are not given.
     *
     * @var array
     */
    protected $_defaultValues = array (
                                'key'           => '',
                                'label'         => '',
                                'originalLabel' => '',
                                'type'          => '',
                                'hint'          => '',
                                'listPosition'  => 0,
                                'formPosition'  => 0,
                                'fieldset'      => null,
                                'range'         => null,
                                'required' => false,
                                'readOnly' => false,
                                'tab'      => 1,
                                'integer'  => false,
                                'length'   => 0,
                                'default'  => null);

    /**
     * Fills the _field array with mandatory data and optional keys.
     * Undefined keys are stripped.
     *
     * @param string  $key          Name of the model property.
     * @param string  $label        Label of the form field (will get translated).
     * @param string  $type         Type of the form control.
     * @param integer $listPosition Position of the field in the list.
     * @param integer $formPosition Position of the field in the form.
     * @param array   $data         Optional additional keys.
     *
     * @return void
     */
    public function fillField($key, $label, $type, $listPosition, $formPosition, array $data = array())
    {
        $result = $this->_defaultValues;

        foreach ($data as $index => $value) {
            if (isset($result[$index]) || (null === $result[$index] && null !== $value)) {
                $result[$index] = $value;
            }
        }

        $result['key']           = $key;
        $result['label']         = Phprojekt::getInstance()->translate($label);
        $result['originalLabel'] = $label;
        $result['type']          = $type;
        $result['hint']          = Phprojekt::getInstance()->getTooltip($key);
        $result['listPosition']  = (int) $listPosition;
        $result['formPosition']  = (int) $formPosition;

        $this->_fields[] = $result;
    }

    /**
     * Set all the module fields with the data of each one.
     *
     * @return void
     */
    public function setFields()
    {
        $this->_fields = array();
    }

    /**
     * Return the fields array.
     *
     * @return array Array with all the fields definitions.
     */
    protected function _getFields()
    {
        if (null === $this->_fields) {
            $this->setFields();
        }

        return $this->_fields;
    }

    /**
     * Returns all the necessary field definitions based on the ordering const that's given.
     *
     * @param integer $ordering Type of order.
     *
     * @see Phprojekt_ModelInformation_Interface::getFieldDefinition()
     *
     * @return array Array with all the fields definitions, sorted.
     */
    public function getFieldDefinition($ordering = Phprojekt_ModelInformation_Default::ORDERING_DEFAULT)
    {
        $definition = array();
        $fields     = $this->_getFields();

        switch ($ordering) {
            case Phprojekt_ModelInformation_Default::ORDERING_FILTER:
            case Phprojekt_ModelInformation_Default::ORDERING_LIST:
                usort($fields, array("Phprojekt_ModelInformation_Default", "sortByListPosition"));
                $sort = 'listPosition';
                break;
            default:
            case Phprojekt_ModelInformation_Default::ORDERING_FORM:
                usort($fields, array("Phprojekt_ModelInformation_Default", "sortByFormPosition"));
                $sort = 'formPosition';
                break;
        }

        foreach ($fields as $key => $field) {
            switch ($field['type']) {
                case 'selectValues':
                case 'selectbox':
                    $fields[$key] = Phprojekt_ModelInformation_Convert::convertSelect($field, $field['range'],
                        $field['required'], $field['type']);
                    break;
                case 'multipleSelectValues':
                case 'multipleselectbox':
                    $fields[$key] = Phprojekt_ModelInformation_Convert::convertSelect($field, $field['range'],
                        $field['required'], $field['type']);
                    $fields[$key]['type'] = 'multipleselectbox';
                    break;
                case 'display':
                    // Has it an Id value that should be translated into a descriptive String?
                    if (null !== $field['range'] && !empty($field['range'])) {
                        $fields[$key] = Phprojekt_ModelInformation_Convert::convertSelect($field, $field['range'],
                            $field['required'], $field['type']);
                    }
                    $fields[$key]['type']     = 'display';
                    $fields[$key]['readOnly'] = true;
                    break;
                default:
                    $fields[$key]['range'] = array('id'   => '',
                                                   'name' => '');
                    break;
            }
        }

        foreach ($fields as $key => $field) {
            if ($field[$sort] == 0) {
                unset($fields[$key]);
            }
        }
        $fields = array_values($fields);

        return $fields;
    }

    /**
     * Return the type of one field.
     *
     * @param string $fieldName The name of the field to check.
     *
     * @return string Type of the field.
     */
    public function getType($fieldName)
    {
        $return = null;
        $fields = $this->_getFields();
        foreach ($fields as $field) {
            if ($field['key'] == $fieldName) {
                $return = $field['type'];
                break;
            }
        }

        return $return;
    }

    /**
     * Return the API string for the range.
     *
     * @param array $values Array with Key => Value.
     *
     * @return string key1|value1 # key2|value2 # ...
     */
    public function getRangeValues($values)
    {
        $range = '';
        $first = 1;
        foreach ($values as $key => $value) {
            if (!$first) {
                $range .= '|';
            }
            $range .= $key . '#' . $value;
            $first = 0;
        }

        return $range;
    }

    /**
     * Return the project string for make a range.
     *
     * @return string The correct string for range.
     */
    public function getProjectRange()
    {
        return 'Project#id#title';
    }

    /**
     * Return the user string for make a range.
     *
     * @return string The correct string for range.
     */
    public function getUserRange()
    {
        return 'User#id#name';
    }

    /**
     * Sort the array using the listPosition value.
     *
     * @param array $a First array.
     * @param array $b Second array.
     *
     * @return integer Comparation value.
     */
    public static function sortByListPosition($a, $b)
    {
        if ($a['listPosition'] == $b['listPosition']) {
            return 0;
        }

        return ($a['listPosition'] < $b['listPosition']) ? -1 : 1;
    }

    /**
     * Sort the array using the formPosition value.
     *
     * @param array $a First array.
     * @param array $b Second array.
     *
     * @return integer Comparation value.
     */
    public static function sortByFormPosition($a, $b)
    {
        if ($a['formPosition'] == $b['formPosition']) {
            return 0;
        }

        return ($a['formPosition'] < $b['formPosition']) ? -1 : 1;
    }
}
