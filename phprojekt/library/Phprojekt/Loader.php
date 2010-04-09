<?php
/**
 * An own class loader that reads the class files from the
 * /application directory or from the Zend library directory depending
 * on the name of the class.
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
 * @subpackage Core
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     David Soria Parra <soria_parra@mayflower.de>
 */

/**
 * An own class loader that reads the class files from the
 * /application directory or from the Zend library directory depending
 * on the name of the class.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage Core
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     David Soria Parra <soria_parra@mayflower.de>
 */
class Phprojekt_Loader extends Zend_Loader
{
    /**
     * Identifier for models.
     * It's normaly only needed by the internals.
     *
     * @see _getClass
     */
    const MODEL = 'Models';

    /**
     * Identifier for views.
     * It's normaly only needed by the internals.
     *
     * @see _getClass
     */
    const VIEW = 'Views';

    /**
     * Identifier for Controllers.
     * It's normaly only needed by the internals.
     *
     * @see _getClass
     */
    const CONTROLLER = 'Controllers';

    /**
     * Identifier for Helpers.
     * It's normaly only needed by the internals.
     *
     * @see _getClass
     */
    const HELPER = 'Helpers';

    /**
     * Identifier for Controllers.
     * It's normaly only needed by the internals.
     *
     * @see _getClass
     */
    const LIBRARY = 'Phprojekt';

    /**
     * Define the set of allowed characters for classes..
     */
    const CLASS_PATTERN = '[A-Za-z0-9_]+';

    /**
     * Load a class
     *
     * @param string       $class Name of the class.
     * @param string|array $dirs  Directories to search.
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Class name.
     */
    public static function loadClass($class, $dirs = null)
    {
        try {
            if (preg_match("@Controller$@", $class)) {
                // Controller class
                $class = self::getControllerClassname($class);
            } else {
                // Model class
                $pattern = str_replace('_', '', self::CLASS_PATTERN);
                if (preg_match("@^(" . $pattern . ")_Models_(" . self::CLASS_PATTERN . ")@", $class, $match)) {
                    $class = self::getModelClassname($match[1], $match[2]);
                } else {
                    // Helper class
                    if (preg_match("@^(" . $pattern . ")_Helpers_(" . self::CLASS_PATTERN . ")@", $class, $match)) {
                        $class = self::getHelperClassname($match[1], $match[2]);
                    } else {
                        // Library Class
                        if (null === $dirs) {
                            $dirs = array(PHPR_LIBRARY_PATH);
                        }
                        parent::loadClass($class, $dirs);
                    }
                }
            }
        } catch (Zend_Exception $error) {
            Phprojekt::getInstance()->getLog()->debug($error->getMessage());
        }

        return $class;
    }

    /**
     * The autoload method used to load classes on demand.
     * Returns either the name of the class or false, if loading failed.
     *
     * @param string $class The name of the class.
     *
     * @return string|false Class name on success; false on failure.
     */
    public static function autoload($class)
    {
        try {
            return self::loadClass($class);
        } catch (Exception $error) {
            $error->getMessage();
            return false;
        }

        return false;
    }

    /**
     * Instantiate a given class name.
     * We asume that it's allready loaded.
     *
     * @param string $name Name of the class.
     * @param array  $args Argument list.
     *
     * @return object
     */
    protected static function _newInstance($name, $args)
    {
        // We have to use the reflection here, as expanding arguments
        // to an array is not possible without reflection.
        $class = new ReflectionClass($name);
        if (null !== $class->getConstructor()) {
            return $class->newInstanceArgs($args);
        } else {
            return $class->newInstance();
        }
    }

    /**
     * Finds a class.
     * If a customized class is available in the Customized/directory,
     * it's loaded and the name is returned, instead of the normal class.
     *
     * @param string $module Name of the module.
     * @param string $item   Name of the class to be loaded.
     * @param string $ident  Ident, might be 'Models', 'Controllers', 'Views' or 'Phprojekt'.
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    protected static function _getClass($module, $item, $ident)
    {
        switch ($ident) {
            case self::MODEL:
            case self::HELPER:
            default:
                $nIdentifier = sprintf("%s_%s_%s", $module, $ident, $item);
                $cIdentifier = sprintf("%s_Customized_%s_%s", $module, $ident, $item);
                $path        = PHPR_CORE_PATH;
                $directories = array(PHPR_CORE_PATH);
                break;
            case self::CONTROLLER:
                if ($module != 'Default') {
                    $nIdentifier = sprintf("%s_%s", $module, $item);
                    $cIdentifier = sprintf("%s_Customized_%s", $module, $item);
                } else {
                    $nIdentifier = sprintf("%s", $item);
                    $cIdentifier = sprintf("Customized_%s", $item);
                }
                $pathIdentifier = sprintf("%s", $item);
                $directories    = Zend_Controller_Front::getInstance()->getControllerDirectory();
                $nPath          = $directories[$module];
                $cPath          = str_replace('application' . DIRECTORY_SEPARATOR . $module, 'application'
                    . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Customized', $nPath);
                $directories = array(PHPR_CORE_PATH);
                break;
            case self::LIBRARY:
                // Don't try to load any customized file
                $nIdentifier = sprintf("%s_%s_%s", $ident, $module, $item);
                $cIdentifier = sprintf("%s_%s_%s", $ident, $module, $item);
                $path        = PHPR_LIBRARY_PATH;
                $directories = array(PHPR_LIBRARY_PATH);
                break;
        }

        // Try the custom class
        if (class_exists($cIdentifier, false) || interface_exists($cIdentifier, false)) {
            return $cIdentifier;
        } else  {
            // Try to load the custom file
            if ($ident == self::CONTROLLER) {
                $cLoadFile = $cPath . DIRECTORY_SEPARATOR . self::classToFilename($pathIdentifier);
            } else {
                $cLoadFile = $path . DIRECTORY_SEPARATOR . self::classToFilename($cIdentifier);
            }
            if (is_readable($cLoadFile)) {
                // Load the original class first
                if ($ident == self::CONTROLLER) {
                    $nLoadFile = $nPath . DIRECTORY_SEPARATOR . self::classToFilename($pathIdentifier);
                } else {
                    $nLoadFile = $path . DIRECTORY_SEPARATOR . self::classToFilename($nIdentifier);
                }
                self::_loadClass($nIdentifier, $nLoadFile, $directories);
                // Load the custom class
                self::loadFile($cLoadFile, $directories, true);

                // Try again load the custom class
                if (class_exists($cIdentifier, false) || interface_exists($cIdentifier, false)) {
                    return $cIdentifier;
                } else {
                    return $nIdentifier;
                }
            } else {
                // Load the original class
                if ($ident == self::CONTROLLER) {
                    $nLoadFile = $nPath . DIRECTORY_SEPARATOR . self::classToFilename($pathIdentifier);
                } else {
                    $nLoadFile = $path . DIRECTORY_SEPARATOR . self::classToFilename($nIdentifier);
                }

                return self::_loadClass($nIdentifier, $nLoadFile, $directories);
            }
        }
    }

    /**
     * Try to load an original file.
     *
     * @param string $identifier  Name of the class.
     * @param string $loadFile    Path to the file.
     * @param array  $directories Array with directories where looking for the file.
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    private static function _loadClass($identifier, $loadFile, $directories)
    {
        // Try the class
        if (class_exists($identifier, false) || interface_exists($identifier, false)) {
            return $identifier;
        } else {
            // Try to load the file
            if (is_readable($loadFile)) {
                self::loadFile($loadFile, $directories, true);
            } else {
                throw new Zend_Exception('Cannot load class "' . $identifier . '" from file "' . $loadFile . "'");
            }

            if (class_exists($identifier, false) || interface_exists($identifier, false)) {
                return $identifier;
            } else {
                throw new Zend_Exception('Invalid class ("' . $identifier . '")');
            }
        }
    }

    /**
     * Load the class of a model and return the name of the class.
     *
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method.
     *
     * @param string $module Name of the module.
     * @param string $model  Name of the class to be loaded.
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    public static function getModelClassname($module, $model)
    {
        return self::_getClass($module, $model, self::MODEL);
    }

    /**
     * Load the class of a view and return the name of the class.
     *
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method
     *
     * @param string $module Name of the module.
     * @param string $view   Name of the class to be loaded.
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    public static function getViewClassname($module, $view)
    {
        return self::_getClass($module, $view, self::VIEW);
    }

    /**
     * Load the class of a controller and return the name of the class.
     *
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method
     *
     * @param string $controller Name of the class to be loaded.
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    public static function getControllerClassname($controller)
    {
        $name = explode("_", $controller);
        if (count($name) == 1) {
            $module = 'Default';
            $item   = $name[0];
        } else {
            $module = $name[0];
            $item   = $name[1];
        }

        return self::_getClass($module, $item, self::CONTROLLER);
    }

    /**
     * Load the class of a helper and return the name of the class.
     *
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method.
     *
     * @param string $module Name of the module.
     * @param string $model  Name of the class to be loaded.
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    public static function getHelperClassname($module, $model)
    {
        return self::_getClass($module, $model, self::HELPER);
    }

    /**
     * Load the library class and return the name of the class.
     *
     * Always use the returned name to instantiate a class.
     *
     * @param string $module Name of the module.
     * @param string $class  Class to be loaded.
     *
     * @see _getClass
     *
     * @throws Zend_Exception If class not found.
     *
     * @return string Identifier class name.
     */
    public static function getLibraryClassname($module, $class)
    {
        return self::_getClass($module, $class, self::LIBRARY);
    }

    /**
     * Load the class of a model and return an new instance of the class.
     *
     * A customized class name might be loaded and returned by this method.
     *
     * This method can take more than the two arguments.
     * Every other argument is passed to the constructor.
     *
     * The class is temporally cached in the Registry for the next calls.
     * Only is cached if don't have any arguments
     *
     * Be sure that the class have the correct "__clone" function defined
     * if it have some internal variables with other classes for prevent
     * the same point to the object.
     *
     * @param string $module Name of the module.
     * @param string $model  Name of the model.
     *
     * @return Object
     */
    public static function getModel($module, $model)
    {
        $name = self::getModelClassname($module, $model);
        $args = array_slice(func_get_args(), 2);

        if (empty($args) && Phprojekt::getInstance()->getConfig()->useCacheForClasses) {
            $registryName = 'getModel_'.$module.'_'.$model;
            if (!Zend_Registry::isRegistered($registryName)) {
                $object = self::_newInstance($name, $args);
                Zend_Registry::set($registryName, $object);
            } else {
                $object = clone(Zend_Registry::get($registryName));
            }
        } else {
            $object = self::_newInstance($name, $args);
        }

        return $object;
    }

    /**
     * Load a class from the library and return an new instance of the class.
     *
     * This method can take more than the two arguments.
     * Every other argument is passed to the constructor.
     *
     * The class is temporally cached in the Registry for the next calls.
     * Only is cached if don't have any arguments.
     *
     * Be sure that the class have the correct "__clone" function defined
     * if it have some internal variables with other classes for prevent
     * the same point to the object.
     *
     * @param string $name Name of the class.
     *
     * @return Object
     */
    public static function getLibraryClass($name)
    {
        $args = array_slice(func_get_args(), 2);

        if (empty($args) && Phprojekt::getInstance()->getConfig()->useCacheForClasses) {
            $registryName = 'getLibraryClass_' . $name;
            if (!Zend_Registry::isRegistered($registryName)) {
                $object = self::_newInstance($name, $args);
                Zend_Registry::set($registryName, $object);
            } else {
                $object = clone(Zend_Registry::get($registryName));
            }
        } else {
            $object = self::_newInstance($name, $args);
        }

        return $object;
    }

    /**
     * Returns the name of the model for a given object.
     *
     * @param Phprojekt_Model_Interface $object An active record.
     *
     * @return string|boolean
     */
    public static function getModelFromObject(Phprojekt_Model_Interface $object)
    {
        $match = null;
        $pattern = str_replace('_', '', self::CLASS_PATTERN);
        if (preg_match("@_(" . $pattern . ")$@", get_class($object), $match)) {
            return $match[1];
        }

        return false;
    }

    /**
     * Returns the name of the modul for a given object.
     *
     * @param Phprojekt_ActiveRecord_Abstract $object An active record.
     *
     * @return string|boolean
     */
    public static function getModuleFromObject(Phprojekt_Model_Interface $object)
    {
        $match = null;
        $pattern = str_replace('_', '', self::CLASS_PATTERN);
        if (preg_match("@^(" . $pattern . ")_@", get_class($object), $match)) {
            if ($match[1] == 'Phprojekt') {
                return 'Core';
            } else {
                return $match[1];
            }
        }

        return false;
    }

    /**
     * Load the class of a view and return an new instance of the class.
     *
     * Always use the returned name to instantiate a class, a customized
     * class name might be loaded and returned by this method
     *
     * @param string $module Name of the module
     * @param string $view   Name of the view
     *
     * @return Object
     */
    public static function getView($module, $view)
    {
        $name = self::getViewClassname($module, $view);
        $args = array_slice(func_get_args(), 2);

        return self::_newInstance($name, $args);
    }

    /**
     * Add the module path for load customs templates.
     *
     * @param Zend_View|null $view View class.
     *
     * @return void;
     */
    public static function loadViewScript($view = null)
    {
        $module = Zend_Controller_Front::getInstance()->getRequest()->getModuleName();
        if (null === $view) {
            $view = Phprojekt::getInstance()->getView();
        }
        $view->addScriptPath(PHPR_CORE_PATH . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR
            . self::VIEW . DIRECTORY_SEPARATOR . 'dojo' . DIRECTORY_SEPARATOR);
    }

    /**
     * Convert a class name to a filename
     *
     * @param string $class Name of the class.
     *
     * @return string Path of the file.
     */
    public static function classToFilename($class)
    {
        return str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    }
}
