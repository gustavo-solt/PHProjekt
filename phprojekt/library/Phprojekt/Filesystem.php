<?php
/**
 * WebDAV implementation for Filemanager module.
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
 * @since      File available since Release 6.0.3
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */

/**
 * WebDAV implementation for Filemanager module.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage Core
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0.3
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */
class Phprojekt_Filesystem extends Phprojekt_WebDav_Abstract
{
    /**
     * Root directory for WebDAV access
     *
     * Defaults to webserver document root (set by serveRequest)
     *
     * @var string
     */
    private $_base = '';

    /**
     * String used for separate title and fileName.
     *
     * @var string
     */
    private $_fileSeparator = ' --- ';

    /**
     * Serve a webdav request
     *
     * @param  string
     */
    public function serveRequest($base = false)
    {
        // Set root directory
        $this->_base = $this->slashify(realpath($base));

        // Let the base class do all the work
        parent::serveRequest();
    }

    /**
     * PROPFIND method handler.
     *
     * @return boolean True on success.
     */
    public function propfind()
    {
        // Get absolute fs path to requested resource
        $fspath = $this->_base . $this->options['path'];

        // Get the projectId
        $projectId = (int) $this->getProjectId($this->options['path']);
        if ($projectId == 0) {
            $projectId = 1;
        }

        $rows     = array();
        $db       = Phprojekt::getInstance()->getDb();
        $moduleId = Phprojekt_Module::getId('Filemanager');
        $userId   = Phprojekt_Auth::getUserId();

        // Get children projects
        $activeRecord = Phprojekt_Loader::getModel('Project', 'Project');
        $tree         = new Phprojekt_Tree_Node_Database($activeRecord, $projectId);
        $tree         = $tree->setup();
        $projects     = array();
        foreach ($tree as $node) {
            $projects[] = (int) $node->id;
        }

        // Acccess
        $joinWhere = sprintf('(i.item_id = f.id AND i.module_id = %d AND i.user_id = %d)', $moduleId, $userId);

        // Under a projects
        $where1 = sprintf('(f.project_id = %d OR f.project_id IN (%s))', $projectId, implode(',', $projects));

        // With any access
        $where2 = sprintf('((f.owner_id = %d OR f.owner_id IS NULL) OR (i.access > 0))', $userId);

        $select = $db->select()
                     ->from(array('f' => 'filemanager'), array('f.project_id', 'f.files', 'fileTitle' => 'f.title'))
                     ->joinInner(array('i' => 'item_rights'), $joinWhere)
                     ->joinLeft(array('p' => 'project'), 'f.project_id = p.id',
                        array('p.path'))
                     ->where($where1)
                     ->where($where2);
        $stmt = $db->query($select);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return '500 Internal Server Error';
        }

        $folders               = array();
        $this->options['path'] = $this->slashify($this->options['path']);

        $copyOfRows = $rows;
        foreach ($rows as $row) {
            // Current project
            if ($row['project_id'] == $projectId) {
                $dataFiles  = explode('||', $row['files']);
                $md5Name    = '';
                $fileName   = '';
                foreach ($dataFiles as $file) {
                    list($md5Name, $fileName) = explode('|', $file);
                    if ($fileName != $row['fileTitle']) {
                        $fileName = $row['fileTitle'] . $this->_fileSeparator . $fileName;
                    }
                    $this->files['files'][] = $this->fileinfo($this->options['path'] . $fileName, $md5Name);
                }
            }
        }

        // Show just the children folders with files
        foreach ($tree as $node) {
            if ($node->getDepth() == 1) {
                $folders[] = array('title' => $node->title,
                                   'path'  => $node->path,
                                   'id'    => $node->id);
            }
        }
        foreach ($folders as $folder) {
            $path = $folder['path'] . $folder['id'] . Phprojekt_Tree_Node_Database::NODE_SEPARATOR;
            foreach ($copyOfRows as $row) {
                if ((substr($row['path'], 0, strlen($path)) == $path) || ($row['project_id'] == $folder['id'])) {
                    $this->files['files'][] = $this->dirInfo($this->options['path'] . $folder['title']);
                    break;
                }
            }
        }

        // Ok, all done
        return true;
    }

    /**
     * GET method handler.
     *
     * @return boolean True on success.
     */
    public function get()
    {
        // Get the file data
        $data    = $this->_getDataFromPath($this->options['path']);
        $md5Name = $data['md5'];

        // GET by Web ?
        if ($md5Name == '-') {
            return $this->getDir($this->options);
        }

        // Get absolute fs path to requested resource
        $fspath = $this->_base . $md5Name;

        // Sanity check
        if (!file_exists($fspath)) {
            return false;
        }

        // Check access
        if (!$data['access']['download']) {
            return false;
        }

        // Detect resource type
        $this->options['mimetype'] = $this->_mimetype($fspath);

        // Detect modification time
        // see rfc2518, section 13.7
        // some clients seem to treat this as a reverse rule
        // requiering a Last-Modified header if the getlastmodified header was set
        $this->options['mtime'] = filemtime($fspath);

        // Detect resource size
        $this->options['size'] = filesize($fspath);

        // No need to check result here, it is handled by the base class
        $this->options['stream'] = fopen($fspath, 'r');

        return true;
    }

    /**
     * GET method handler for directories.
     *
     * This is a very simple mod_index lookalike.
     * See RFC 2518, Section 8.4 on GET/HEAD for collections
     *
     * @return void
     */
    function getDir()
    {
        $this->propfind();

        // Fixed width directory column format
        $format = "%15s  %-19s  %-s\n";
        echo "<html><head><title>Index of " . utf8_decode(urldecode($this->options['path'])) . "</title></head>\n";
        echo "<h1>Index of " . utf8_decode(urldecode($this->options['path'])) . "</h1>\n";
        echo "<pre>";
        printf($format, "Size", "Last modified", "Name");
        echo "<hr>";

        // Parent
        printf($format, '', '', "<a href='" . substr($this->uri, 0, strrpos($this->uri, '/'))
            . "'>Parent Directory</a>");

        uasort($this->files['files'], array("Phprojekt_Filesystem", "sortFiles"));

        $uri = $this->slashify($this->uri);
        foreach ($this->files['files'] as $file) {
            foreach ($file['props'] as $prop) {
                switch ($prop['name']) {
                    case 'getcontentlength':
                        $length = $prop['val'];
                        break;
                    case 'getlastmodified':
                        $modified = $prop['val'];
                        break;
                    case 'resourcetype':
                        $type = $prop['val'];
                        break;
                    case 'displayname':
                        $pos = (int) strrpos($file['path'], '/');
                        if ($pos == 0) {
                            $display = substr($file['path'], 1);
                        } else {
                            $display = substr($file['path'], $pos + 1);
                        }
                        $display = utf8_decode(urldecode($display));
                        break;
                }
            }
            if ($type == 'collection') {
                printf($format, '', '', "<a href='" . $uri . $display . "'>" . $display . "</a>");
            } else {
                printf($format, number_format($length), strftime("%Y-%m-%d %H:%M:%S", $modified),
                    "<a href='" . $uri . $display . "'>" . $display . "</a>");
            }
        }

        echo "</pre>";
        echo "</html>\n";

        exit;
    }

    /**
     * Sort function.
     *
     * @param array $file1 First array for sort.
     * @param array $file2 Second array for sort.
     *
     * @return integer
     */
    public static function sortFiles($file1, $file2)
    {
        foreach ($file1['props'] as $prop1) {
            switch ($prop1['name']) {
                case 'resourcetype':
                    $type1 = $prop1['val'];
                    break;
                case 'displayname':
                    $display1 = $prop1['val'];
                    break;
            }
        }

        foreach ($file2['props'] as $prop2) {
            switch ($prop2['name']) {
                case 'resourcetype':
                    $type2 = $prop2['val'];
                    break;
                case 'displayname':
                    $display2 = $prop2['val'];
                    break;
            }
        }

        if ($type1 == 'collection' && $type2 != 'collection') {
            return -1;
        } else if ($type1 != 'collection' && $type2 == 'collection') {
            return 1;
        } else {
            return strcmp($display1, $display2);
        }
    }

    /**
     * PUT method handler.
     *
     * @return boolean True on success.
     */
    public function put()
    {
        // Get the file data
        $data      = $this->_getDataFromPath($this->options['path']);
        $md5Name   = $data['md5'];
        $fileName  = $data['name'];
        $projectId = $data['projectId'];
        $title     = $data['title'];

        if ($md5Name == '-') {
            // New file
            $md5Name = md5(mt_srand());
            $model   = new Filemanager_Models_Filemanager();
            $params  = array(
                'files'            => $md5Name . '|' . $fileName,
                'projectId'        => $projectId,
                'sendNotification' => 0,
                'title'            => $title);

            Phprojekt::setCurrentProjectId($projectId);

            try {
                Default_Helpers_Save::save($model, $params);
            } catch (Phprojekt_PublishedException $error) {
                echo $error->getMessage();
                return false;
            }
        } else {
            // Check access
            if (!$data['access']['write']) {
                echo 'You do not have access to do this action';
                return false;
            }
        }

        // Get absolute fs path to requested resource
        $fspath = $this->_base . $md5Name;

        if (!@is_dir(dirname($fspath))) {
            return false;
        }

        $this->options['new'] = !file_exists($fspath);

        $fp = fopen($fspath, 'w');

        return $fp;
    }

    /**
     * PROPPATCH method handler.
     *
     * @return boolean True on success.
     */
    public function proppatch()
    {
        foreach ($this->options['props'] as $key => $prop) {
            if ($prop['ns'] == 'DAV:') {
                $this->options['props'][$key]['status'] = '403 Forbidden';
            }
        }

        return '';
    }

    /**
     * DELETE method handler.
     *
     * @return string Delete response.
     */
    public function delete()
    {
        // Get the file data
        $data      = $this->_getDataFromPath($this->options['path']);
        $projectId = $data['projectId'];
        $title     = $data['title'];
        $md5Name   = $data['md5'];
        $id        = $data['id'];
        $order     = $data['order'];

        // Check access
        if (!$data['access']['write']) {
            return '404 Not found';
        }

        // Get absolute fs path to requested resource
        $fspath = $this->_base . $md5Name;
        if ($md5Name == '-' || !file_exists($fspath)) {
            return '404 Not found';
        }

        $model = new Filemanager_Models_Filemanager();
        $model->find($id);

        $filesIn = explode('||', $model->files);

        // Delete the file name and md5 from the string
        $filesOut = '';
        $i        = 1;
        foreach ($filesIn as $file) {
            if ($i != $order) {
                if ($filesOut != '') {
                    $filesOut .= '||';
                }
                $filesOut .= $file;
            } else {
                // Delete the file from the server
                if (preg_match("/^[A-Fa-f0-9]{32,32}$/", $md5Name)) {
                    unlink($fspath);
                }
            }
            $i++;
        }


        if (empty($filesOut)) {
            // Don' have files? delete the item
            Default_Helpers_Delete::delete($model);
        } else {
            // Update the item withot the file
            $params = array(
                'files'            => $filesOut,
                'projectId'        => $projectId,
                'sendNotification' => 0,
                'title'            => $title);

            Phprojekt::setCurrentProjectId($projectId);
            Default_Helpers_Save::save($model, $params);
        }

        return '204 No Content';
    }

    /**
     * Get properties for a single file/resource.
     *
     * @param string $display Virtual path.
     * @param string $path    Real file name.
     *
     * @return array Resource properties.
     */
    public function fileinfo($display, $path)
    {
        // Fix display
        $display = $this->urlencode($display);

        // Map URI path to filesystem path
        $fspath = $this->_base . $path;

        // Create result array
        $info          = array();
        $info['path']  = $display;
        $info['props'] = array();

        // No special beautified displayname here ...
        $info['props'][] = $this->mkprop('displayname', $display);

        // Creation and modification time
        $info['props'][] = $this->mkprop('creationdate',    filectime($fspath));
        $info['props'][] = $this->mkprop('getlastmodified', filemtime($fspath));

        // Type and size (caller already made sure that path exists)
        if (is_dir($fspath)) {
            // Directory (WebDAV collection)
            $info['props'][] = $this->mkprop('resourcetype', 'collection');
            $info['props'][] = $this->mkprop('getcontenttype', 'httpd/unix-directory');
        } else {
            // Plain file (WebDAV resource)
            $info['props'][] = $this->mkprop('resourcetype', '');
            if (is_readable($fspath)) {
                $info['props'][] = $this->mkprop('getcontenttype', $this->_mimetype($fspath));
            } else {
                $info['props'][] = $this->mkprop('getcontenttype', 'application/x-non-readable');
            }
            $info['props'][] = $this->mkprop('getcontentlength', filesize($fspath));
        }

        return $info;
    }

    /**
     * Get properties for a virtual folder (project).
     *
     * @param string $display Virtual path.
     *
     * @return array Resource properties.
     */
    public function dirInfo($display)
    {
        // Fix display
        $display = $this->urlencode($display);

        $info            = array();
        $info['path']    = $display;
        $info['props'][] = $this->mkprop('displayname', $display);
        $info['props'][] = $this->mkprop('creationdate',    time());
        $info['props'][] = $this->mkprop('getlastmodified', time());
        $info['props'][] = $this->mkprop('getcontentlength', 0);
        $info['props'][] = $this->mkprop('resourcetype', 'collection');
        $info['props'][] = $this->mkprop('getcontenttype', 'httpd/unix-directory');
        $info['props'][] = $this->mkprop('principal-collection-set', $this->mkprop('href', $display));

        return $info;
    }

    /**
     * Try to detect the mime type of a file
     *
     * @param string $file File path.
     *
     * @return string Guessed mime type.
     */
    private function _mimetype($file)
    {
        if (class_exists('finfo', false)) {
            $const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
            $mime = new finfo($const);

            if ($mime !== false) {
                $result = $mime->file($file);
            }

            unset($mime);
        }

        if (empty($result) && (function_exists('mime_content_type') && ini_get('mime_magic.magicfile'))) {
            $result = mime_content_type($file);
        }

        if (empty($result)) {
            $result = 'application/octet-stream';
        }

        return $result;
    }

    /**
     * Get a projectId from a path with names.
     *
     * @param string  $path      The path with names.
     * @param integer $projectId The parent projectId.
     *
     * @return integer The projectId found.
     */
    public function getProjectId($path, $projectId = 1)
    {
        if ($path == '/') {
            return 1;
        }

        $names = explode('/', $this->unslashify($path));
        $db    = Phprojekt::getInstance()->getDb();
        foreach ($names as $name) {
            $name = array_shift($names);
            if (!empty($name)) {
                $select = $db->select()
                             ->from('project')
                             ->where('title = ?', $name)
                             ->where('project_id = ?', $projectId);
                $stmt = $db->query($select);
                $rows = $stmt->fetchAll();

                $projectId = $rows[0]['id'];
            }
        }

        return $projectId;
    }

    /**
     * Discover file data from the path.
     *
     * @param string $vPath Virtual path.
     *
     * @return array Arrya with P6 data about the file.
     */
    private function _getDataFromPath($vPath)
    {
        // Get the projectId and file
        $pos = (int) strrpos($vPath, '/');
        if ($pos == 0) {
            $projectId = 1;
            $file      = substr($vPath, 1);
        } else {
            $path      = substr($vPath, 0,  $pos);
            $projectId = (int) $this->getProjectId($path);
            $file      = substr($vPath, $pos + 1);
            if ($projectId == 0) {
                $projectId = 1;
            }
        }

        // Get title and fileName
        $pos = (int) strrpos($file, $this->_fileSeparator);
        if ($pos > 0) {
            $title    = (string) substr($file, 0, $pos);
            $fileName = (string) substr($file, $pos + strlen($this->_fileSeparator));
        } else {
            $title    = (string) $file;
            $fileName = (string) $file;
        }

        // Try to find the file
        $db       = Phprojekt::getInstance()->getDb();
        $userId   = Phprojekt_Auth::getUserId();
        $moduleId = Phprojekt_Module::getId('Filemanager');

        // Acccess
        $joinWhere = sprintf('(i.item_id = f.id AND i.module_id = %d AND i.user_id = %d)', $moduleId, $userId);

        // With any access
        $where = sprintf('((f.owner_id = %d OR f.owner_id IS NULL) OR (i.access > 0))', $userId);

        $select = $db->select()
                     ->from(array('f' => 'filemanager'), array('id', 'files'))
                     ->joinInner(array('i' => 'item_rights'), $joinWhere, array('access'))
                     ->where($where)
                     ->where('f.title = ?', $title)
                     ->where('f.project_id = ?', $projectId)
                     ->where('f.files LIKE ?', '%' . $fileName . '%');
        $stmt = $db->query($select);
        $rows = $stmt->fetchAll();

        $md5Name = '-';
        $id      = 0;
        $order   = 0;
        $access  = array();
        if (isset($rows[0]) && isset($rows[0]['files'])) {
            $files = explode('||', $rows[0]['files']);
            foreach ($files as $file) {
                $order++;
                list($foundMd5Name, $foundFileName) = explode('|', $file);
                if ($foundFileName == $fileName) {
                    $md5Name = $foundMd5Name;
                    $id      = $rows[0]['id'];
                    $access  = Phprojekt_Acl::convertBitmaskToArray($rows[0]['access']);
                    break;
                }
            }
        }

        return array('id'        => $id,
                     'title'     => $title,
                     'name'      => $fileName,
                     'projectId' => $projectId,
                     'md5'       => $md5Name,
                     'order'     => $order,
                     'access'    => $access);
    }
}
