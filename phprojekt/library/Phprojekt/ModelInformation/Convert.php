<?php
/**
 * Helper class for convert the data of the fields into values for use in the API.
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
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Gustavao Solt <gustavo.solt@mayflower.de>
 */

/**
 * Helper class for convert the data of the fields into values for use in the API.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage ModelInformation
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Gustavao Solt <gustavo.solt@mayflower.de>
 */
final class Phprojekt_ModelInformation_Convert
{
    /**
     * Convert to a a selectbox.
     *
     * @param array $field Array with data of the field.
     *
     * @return array Array with range value set.
     */
    static public function convertSelect($field, $fieldRange, $isRequired, $type, $module = null)
    {
        $field['range'] = array();
        $field['type']  = 'selectbox';

        if (strpos($fieldRange, "|") > 0) {
            foreach (explode('|', $fieldRange) as $range) {
                list($key, $value) = explode('#', $range);
                if (is_numeric($key)) {
                    $key = (int) $key;
                }
                $value = trim($value);
                $name  = Phprojekt::getInstance()->translate($value, null, $module);

                $field['range'][] = array('id'           => $key,
                                          'name'         => $name,
                                          'originalName' => $value);
            }
        } else {
            $field['range'] = self::getRangeFromModel($field, $fieldRange, $isRequired, $type);
        }

        return $field;
    }

    /**
     * Gets the data range for a select using a model.
     *
     * @param unknown_type $field
     * @param unknown_type $fieldRange
     * @param unknown_type $isRequired
     * @param unknown_type $type
     *
     * @return array Array with 'id' and 'name'.
     */
    static public function getRangeFromModel($field, $fieldRange, $isRequired, $type)
    {
        $options                    = array();
        list($module, $key, $value) = explode('#', $fieldRange);
        $module                     = trim($module);
        $key                        = trim($key);
        $value                      = trim($value);

        switch ($module) {
            case 'Project':
                $activeRecord = Phprojekt_Loader::getModel('Project', 'Project');
                $tree         = new Phprojekt_Tree_Node_Database($activeRecord, 1);
                $tree         = $tree->setup();
                foreach ($tree as $node) {
                    $options[] = array('id'   => (int) $node->$key,
                                       'name' => $node->getDepthDisplay($value));
                }
                break;
            case 'User':
                $activeRecord = Phprojekt_Loader::getLibraryClass('Phprojekt_User_User');
                $result       = $activeRecord->getAllowedUsers();
                if (!$isRequired && $type == 'selectValues') {
                    $options[] = array('id'   => 0,
                                       'name' => '');
                }
                $options = array_merge($options, $result);
                break;
            default:
                $activeRecord = Phprojekt_Loader::getModel($module, $module);
                if (method_exists($activeRecord, 'getRangeFromModel')) {
                    $options = call_user_func(array($activeRecord, 'getRangeFromModel'), $field);
                } else {
                    $result  = $activeRecord->fetchAll();
                    $options = self::_setRangeValues($isRequired, $result, $key, $value);
                }
                break;
        }

        return $options;
    }

    /**
     * Process the Range value and return the options as array.
     *
     * @param boolean $isRequired
     * @param Object  $result     Result set of items.
     * @param string  $key        Field key for the select (id by default).
     * @param string  $value      Fields for show in the select.
     *
     * @return array Array with 'id' and 'name'.
     */
    static private function _setRangeValues($isRequired, $result, $key, $value)
    {
        $options = array();

        if (!$isRequired) {
            $options[] = array('id'   => 0,
                               'name' => '');
        }

        if (preg_match_all("/([a-zA-z_]+)/", $value, $values)) {
            $values = $values[1];
        } else {
            $values = $value;
        }

        foreach ($result as $item) {
            $showValue = array();
            foreach ($values as $value) {
                if (isset($item->$value)) {
                    $showValue[] = $item->$value;
                }
            }
            $showValue = implode(", ", $showValue);
            $options[] = array('id'   => $item->$key,
                               'name' => $showValue);
        }

        return $options;
    }
}
