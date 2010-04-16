<?php
/**
 * WebDAV server Class.
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
 * WebDAV server Class.
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
class Phprojekt_WebDav_Abstract
{
    /**
     * Copy of $_SERVER superglobal array.
     *
     * Derived classes may extend the constructor to modify its contents.
     *
     * @var array
     */
    public $_SERVER = array();

    /**
     * Realm string to be used in authentification popups.
     *
     * @var string
     */
    public $httpAuthRealm = 'PHP WebDAV';

    /**
     * Instance of Zend_Controller_Request_Abstract.
     *
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request = null;

    /**
     * Base URL.
     *
     * @var string
     */
    protected $_baseUrl = null;

    /**
     * HTTP response status/message.
     *
     * @var string
     */
    private $_httpStatus = '200 OK';

    /**
     * Remember parsed If: (RFC2518/9.4) header conditions.
     *
     * @var array
     */
    private $_ifHeaderUris = array();

    /**
     * Encoding of property values passed in.
     *
     * @var string
     */
    private $_propEncoding = 'utf-8';

    /**
     * Constructor.
     */
    public function __construct()
    {
        // PHP messages destroy XML output -> switch them off
        ini_set('display_errors', false);

        // Copy $_SERVER variables to local _SERVER array
        // so that derived classes can simply modify these
        $this->_SERVER = $_SERVER;

        // Instantiate default request object
        if (null === $this->_request) {
            $this->_request = new Zend_Controller_Request_Http();
        }
    }

    /**
     * Serve WebDAV HTTP request.
     *
     * Dispatch WebDAV HTTP request to the apropriate method handler.
     *
     * @return void
     */
    public function serveRequest()
    {
        // Prevent warning in litmus check 'delete_fragment'
        if (strstr($this->_SERVER['REQUEST_URI'], '#')) {
            $this->httpStatus('400 Bad Request');
            return;
        }

        $pathInfo = $this->_request->getPathInfo();
        if (empty($pathInfo)) {
            $pathInfo = '/';
        }

        $this->baseUri = $this->_request->getScheme() . '://' . $this->_request->getHttpHost()
            . $this->_request->getBasePath() . $this->_request->getBaseUrl();
        $this->uri  = $this->baseUri . $pathInfo;
        $this->path = $this->utf8Encode(urldecode($pathInfo));

        $method = $this->_request->getMethod();

        if (!strlen($this->path)) {
            if ($method == 'GET') {
                // Redirect clients that try to GET a collection
                // WebDAV clients should never try this while
                // regular HTTP clients might ...
                header('Location: ' . $this->baseUri . '/');
                return;
            } else {
                // If a WebDAV client didn't give a path we just assume '/'
                $this->path = '/';
            }
        }

        // Identify ourselves
        header('X-Dav-Powered-By: PHProjekt ' . Phprojekt::getInstance()->getVersion());

        // Check authentication.
        // For the motivation for not checking OPTIONS requests on / see
        // http://pear.php.net/bugs/bug.php?id=5363
        if ((!(($method == 'OPTIONS') && ($this->path == '/'))) && (!$this->_checkAuth())) {
            // RFC2518 says we must use Digest instead of Basic
            // but Microsoft Clients do not support Digest
            // and we don't support NTLM and Kerberos
            // so we are stuck with Basic here
            header('WWW-Authenticate: Basic realm="' . ($this->httpAuthRealm) . '"');

            // Windows seems to require this being the last header sent
            // (changed according to PECL bug #3138)
            $this->httpStatus('401 Unauthorized');

            return;
        }

        // Check
        if (!$this->_checkIfHeaderConditions()) {
            return;
        }

        // Detect requested method names
        $wrapper = 'http' . ucfirst(strtolower($method));

        // Activate HEAD emulation by GET if no HEAD method found
        if ($method == 'HEAD' && !method_exists($this, 'HEAD')) {
            $method = 'GET';
        }

        if (method_exists($this, $wrapper) && ($method == 'OPTIONS' || method_exists($this, strtolower($method)))) {
            // Call method by name
            $this->$wrapper();
        } else {
            // Method not found / implemented
            if ($method == 'LOCK') {
                $this->httpStatus('412 Precondition failed');
            } else {
                $this->httpStatus('405 Method not allowed');
                // Tell client what's allowed
                header('Allow: ' . join(', ', $this->_allow()));
            }
        }
    }

    /**
     * Checks wether the user is known to the system or not
     *
     * @param string $user Submitted username.
     * @param string $pw   Submitted password.
     *
     * @return bool
     */
    public function checkAuth($username, $password)
    {
        try {
            return Phprojekt_Auth::login($username, $password);
        } catch (Phprojekt_Auth_Exception $error) {
            return false;
        }
    }

    /**
     * OPTIONS method handler.
     *
     * The OPTIONS method handler creates a valid OPTIONS reply
     * including Dav: and Allowed: heaers
     * based on the implemented methods found in the actual instance.
     *
     * @return void
     */
    public function httpOptions()
    {
        // Microsoft clients default to the Frontpage protocol
        // unless we tell them to use WebDAV
        header('MS-Author-Via: DAV');

        // Get allowed methods
        $allow = $this->_allow();

        // Dav header
        // Assume we are always dav class 1 compliant
        $dav = array(1);
        if (isset($allow['LOCK'])) {
            // Dav class 2 requires that locking is supported
            $dav[] = 2;
        }

        // Tell clients what we found
        $this->httpStatus('200 OK');
        header('DAV: ' . join(', ', $dav));
        header('Allow: ' . join(', ', $allow));
        header('Content-length: 0');
    }

    /**
     * PROPFIND method handler.
     *
     * @return void
     */
    public function httpPropfind()
    {
        $options         = array();
        $files           = array();
        $options['path'] = $this->path;

        // Search depth from header (default is infinity)
        if (isset($this->_SERVER['HTTP_DEPTH'])) {
            $options['depth'] = $this->_SERVER['HTTP_DEPTH'];
        } else {
            $options['depth'] = 'infinity';
        }

        // Analyze request payload
        $propinfo = new Phprojekt_WebDav_Parse_Propfind("php://input");
        if (!$propinfo->success) {
            $this->httpStatus('400 Error');
            return;
        }
        $options['props'] = $propinfo->props;

        // Call user handler
        if (!$this->propfind($options, $files)) {
            $files = array('files' => array());
            if (method_exists($this, 'checkLock')) {
                // Is locked?
                $lock = $this->checkLock($this->path);

                if (is_array($lock) && count($lock)) {
                    $created          = isset($lock['created'])  ? $lock['created']  : time();
                    $modified         = isset($lock['modified']) ? $lock['modified'] : time();
                    $files['files'][] = array('path'  => $this->slashify($this->path),
                                              'props' => array($this->mkprop('displayname',      $this->path),
                                                               $this->mkprop('creationdate',     $created),
                                                               $this->mkprop('getlastmodified',  $modified),
                                                               $this->mkprop('resourcetype',     ''),
                                                               $this->mkprop('getcontenttype',   ''),
                                                               $this->mkprop('getcontentlength', 0)));
                }
            }

            if (empty($files['files'])) {
                $this->httpStatus('404 Not Found');
                return;
            }
        }

        // Collect namespaces here
        $nsHash = array();

        // Microsoft Clients need this special namespace for date and time values
        $nsDefs = 'xmlns:ns0="urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/"';

        // Now we loop over all returned file entries
        foreach ($files['files'] as $filekey => $file) {
            // Nothing to do if no properties were returend for a file
            if (!isset($file['props']) || !is_array($file['props'])) {
                continue;
            }

            // Now loop over all returned properties
            foreach ($file['props'] as $key => $prop) {
                // As a convenience feature we do not require that user handlers
                // restrict returned properties to the requested ones
                // here we strip all unrequested entries out of the response
                switch($options['props']) {
                    case 'all':
                        // Nothing to remove
                        break;
                    case 'names':
                        // Only the names of all existing properties were requested
                        // so we remove all values
                        unset($files['files'][$filekey]['props'][$key]['val']);
                        break;
                    default:
                        $found = false;
                        // Search property name in requested properties
                        foreach ((array)$options['props'] as $reqprop) {
                            if ($reqprop['name'] == $prop['name'] && @$reqprop['xmlns'] == $prop['ns']) {
                                $found = true;
                                break;
                            }
                        }

                        // Unset property and continue with next one if not found/requested
                        if (!$found) {
                            $files['files'][$filekey]['props'][$key] = '';
                            continue(2);
                        }
                        break;
                }

                // Namespace handling
                if (empty($prop['ns'])) {
                    // No namespace
                    continue;
                }
                $ns = $prop['ns'];
                if ($ns == 'DAV:') {
                    // Default namespace
                    continue;
                }
                if (isset($nsHash[$ns])) {
                    // Already known
                    continue;
                }

                // Register namespace
                $nsName      = 'ns' . (count($nsHash) + 1);
                $nsHash[$ns] = $nsName;
                $nsDefs     .= ' xmlns:' . $nsName . '="'. $ns . '"';
            }

            // We also need to add empty entries for properties that were requested
            // but for which no values where returned by the user handler
            if (is_array($options['props'])) {
                foreach ($options['props'] as $reqprop) {
                    if ($reqprop['name'] == '') {
                        // Skip empty entries
                        continue;
                    }

                    $found = false;

                    // Check if property exists in result
                    foreach ($file['props'] as $prop) {
                        if ($reqprop['name'] == $prop['name'] && @$reqprop['xmlns'] == $prop['ns']) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        if ($reqprop['xmlns'] === 'DAV:' && $reqprop['name'] === 'lockdiscovery') {
                            // lockdiscovery is handled by the base class
                            $files['files'][$filekey]['props'][] = $this->mkprop('DAV:', 'lockdiscovery',
                                $this->lockdiscovery($files['files'][$filekey]['path']));
                        } else {
                            // Add empty value for this property
                            $files['files'][$filekey]['noprops'][] = $this->mkprop($reqprop['xmlns'],
                                $reqprop['name'], '');

                            // Register property namespace if not known yet
                            if ($reqprop['xmlns'] != 'DAV:' && !isset($nsHash[$reqprop['xmlns']])) {
                                $nsName                    = 'ns' . (count($nsHash) + 1);
                                $nsHash[$reqprop['xmlns']] = $nsName;
                                $nsDefs                   .= ' xmlns:' . $nsName . '="' . $reqprop['xmlns'] . '"';
                            }
                        }
                    }
                }
            }
        }

        // Now we generate the reply header ...
        $this->httpStatus('207 Multi-Status');
        header('Content-Type: text/xml; charset="utf-8"');

        // ... and payload
        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        echo "<D:multistatus xmlns:D=\"DAV:\">\n";

        foreach ($files['files'] as $file) {
            // Ignore empty or incomplete entries
            if (!is_array($file) || empty($file) || !isset($file['path'])) {
                continue;
            }
            $path = $file['path'];
            if (!is_string($path) || $path === '') {
                continue;
            }

            echo " <D:response " . $nsDefs . ">\n";

            // TODO right now the user implementation has to make sure
            // collections end in a slash, this should be done in here
            // by checking the resource attribute
            $href = $this->_mergePathes($this->_SERVER['SCRIPT_NAME'], $path);

            echo "  <D:href>" . $href . "</D:href>\n";

            // Report all found properties and their values (if any)
            if (isset($file['props']) && is_array($file['props'])) {
                echo "   <D:propstat>\n";
                echo "    <D:prop>\n";

                foreach ($file['props'] as $key => $prop) {
                    if (!is_array($prop)) {
                        continue;
                    }
                    if (!isset($prop['name'])) {
                        continue;
                    }

                    if (!isset($prop['val']) || $prop['val'] === '' || $prop['val'] === false) {
                        // Empty properties (cannot use empty() for check as "0" is a legal value here)
                        if ($prop['ns'] == 'DAV:') {
                            echo "     <D:" . $prop['name'] . "/>\n";
                        } else if (!empty($prop['ns'])) {
                            echo "     <" . $nsHash[$prop['ns']] . ":" . $prop['name'] . "/>\n";
                        } else {
                            echo "     <" . $prop['name'] . " xmlns=\"\"/>";
                        }
                    } else if ($prop['ns'] == 'DAV:') {
                        // Some WebDAV properties need special treatment
                        switch ($prop['name']) {
                            case 'creationdate':
                                echo "     <D:creationdate ns0:dt=\"dateTime.tz\">"
                                    . gmdate("Y-m-d\\TH:i:s\\Z", $prop['val'])
                                    . "</D:creationdate>\n";
                                break;
                            case 'getlastmodified':
                                echo "     <D:getlastmodified ns0:dt=\"dateTime.rfc1123\">"
                                    . gmdate("D, d M Y H:i:s ", $prop['val'])
                                    . "GMT</D:getlastmodified>\n";
                                break;
                            case 'resourcetype':
                                echo "     <D:resourcetype><D:" . $prop['val'] . "/></D:resourcetype>\n";
                                break;
                            case 'supportedlock':
                                echo "     <D:supportedlock>" . $prop['val'] . "</D:supportedlock>\n";
                                break;
                            case 'lockdiscovery':
                                echo "     <D:lockdiscovery>\n";
                                echo $prop['val'];
                                echo "     </D:lockdiscovery>\n";
                                break;
                            default:
                                echo "     <D:" . $prop['name'] . ">"
                                    . $this->_propEncode(htmlspecialchars($prop['val']))
                                    .     "</D:" . $prop['name'] . ">\n";
                                break;
                        }
                    } else {
                        // Properties from namespaces != "DAV:" or without any namespace
                        if ($prop['ns']) {
                            echo "     <" . $nsHash[$prop['ns']] . ":" . $prop['name'] . ">"
                                . $this->_propEncode(htmlspecialchars($prop['val']))
                                . "</" . $nsHash[$prop['ns']] . ":" . $prop['name'] . ">\n";
                        } else {
                            echo "     <" . $prop['name'] . " xmlns=\"\">"
                                . $this->_propEncode(htmlspecialchars($prop['val']))
                                . "</" . $prop['name'] . ">\n";
                        }
                    }
                }

                echo "   </D:prop>\n";
                echo "   <D:status>HTTP/1.1 200 OK</D:status>\n";
                echo "  </D:propstat>\n";
            }

            // Now report all properties requested but not found
            if (isset($file['noprops'])) {
                echo "   <D:propstat>\n";
                echo "    <D:prop>\n";

                foreach ($file['noprops'] as $key => $prop) {
                    if ($prop['ns'] == 'DAV:') {
                        echo "     <D:" . $prop['name'] . "/>\n";
                    } else if ($prop['ns'] == "") {
                        echo "     <" . $prop['name'] . " xmlns=\"\"/>\n";
                    } else {
                        echo "     <" . $nsHash[$prop['ns']] . ":" . $prop['name'] . "/>\n";
                    }
                }

                echo "   </D:prop>\n";
                echo "   <D:status>HTTP/1.1 404 Not Found</D:status>\n";
                echo "  </D:propstat>\n";
            }

            echo " </D:response>\n";
        }

        echo "</D:multistatus>\n";
    }

    /**
     * GET method handler.
     *
     * @returns void
     */
    public function httpGet()
    {
        // TODO check for invalid stream
        $options         = array();
        $options['path'] = $this->path;

        $this->_getRanges($options);

        if (true === ($status = $this->get($options))) {
            if (!headers_sent()) {
                $status = '200 OK';
                if (!isset($options['mimetype'])) {
                    $options['mimetype'] = 'application/octet-stream';
                }
                header('Content-type: ' . $options['mimetype']);

                if (isset($options['mtime'])) {
                    header('Last-modified: ' . gmdate("D, d M Y H:i:s ", $options['mtime']) . 'GMT');
                }

                if (isset($options['stream'])) {
                    // GET handler returned a stream
                    if (!empty($options['ranges']) && (0 === fseek($options['stream'], 0, SEEK_SET))) {
                        // Partial request and stream is seekable
                        if (count($options['ranges']) === 1) {
                            $range = $options['ranges'][0];
                            if (isset($range['start'])) {
                                fseek($options['stream'], $range['start'], SEEK_SET);
                                if (feof($options['stream'])) {
                                    $this->httpStatus('416 Requested range not satisfiable');
                                    return;
                                }

                                if (isset($range['end'])) {
                                    $size = $range['end'] - $range['start'] + 1;
                                    $this->httpStatus('206 partial');
                                    header('Content-length: ' . $size);
                                    header('Content-range: ' . $range['start'] . '-' . $range['end'] . '/'
                                        . (isset($options['size']) ? $options['size'] : '*'));
                                    while ($size && !feof($options['stream'])) {
                                        $buffer = fread($options['stream'], 4096);
                                        $size  -= strlen($buffer);
                                        echo $buffer;
                                    }
                                } else {
                                    $this->httpStatus('206 partial');
                                    if (isset($options['size'])) {
                                        header('Content-length: ' . ($options['size'] - $range['start']));
                                        header('Content-range: ' . $range['start'] . '-' . $range['end'] . '/'
                                            . (isset($options['size']) ? $options['size'] : '*'));
                                    }
                                    fpassthru($options['stream']);
                                }
                            } else {
                                header('Content-length: ' . $range['last']);
                                fseek($options['stream'], -$range['last'], SEEK_END);
                                fpassthru($options['stream']);
                            }
                        } else {
                            // Init multipart
                            $this->_multipartByterangeHeader();
                            foreach ($options['ranges'] as $range) {
                                // TODO what if size unknown? 500?
                                if (isset($range['start'])) {
                                    $from = $range['start'];
                                    $to   = !empty($range['end']) ? $range['end'] : $options['size'] - 1;
                                } else {
                                    $from = $options['size'] - $range['last'] - 1;
                                    $to   = $options['size'] - 1;
                                }
                                $total = isset($options['size']) ? $options['size'] : '*';
                                $size  = $to - $from + 1;
                                $this->_multipartByterangeHeader($options['mimetype'], $from, $to, $total);

                                fseek($options['stream'], $from, SEEK_SET);
                                while ($size && !feof($options['stream'])) {
                                    $buffer = fread($options['stream'], 4096);
                                    $size  -= strlen($buffer);
                                    echo $buffer;
                                }
                            }
                            // End multipart
                            $this->_multipartByterangeHeader();
                        }
                    } else {
                        // Normal request or stream isn't seekable, return full content
                        if (isset($options['size'])) {
                            header('Content-length: ' . $options['size']);
                        }
                        fpassthru($options['stream']);
                        // No more headers
                        return;
                    }
                } elseif (isset($options['data'])) {
                    if (is_array($options['data'])) {
                        // Reply to partial request
                    } else {
                        header('Content-length: ' . strlen($options['data']));
                        echo $options['data'];
                    }
                }
            }
        }

        if (!headers_sent()) {
            if (false === $status) {
                $this->httpStatus('404 not found');
            } else {
                // TODO: check setting of headers in various code pathes above
                $this->httpStatus($status);
            }
        }
    }

    /**
     * HEAD method handler
     *
     * @return void
     */
    public function httpHead()
    {
        $status          = false;
        $options         = array();
        $options['path'] = $this->path;

        if (method_exists($this, 'head')) {
            $status = $this->head($options);
        } else if (method_exists($this, 'get')) {
            ob_start();
            $status = $this->get($options);
            if (!isset($options['size'])) {
                $options['size'] = ob_get_length();
            }
            ob_end_clean();
        }

        if (!isset($options['mimetype'])) {
            $options['mimetype'] = 'application/octet-stream';
        }
        header('Content-type: ' . $options['mimetype']);

        if (isset($options['mtime'])) {
            header('Last-modified: ' . gmdate("D, d M Y H:i:s ", $options['mtime']) . 'GMT');
        }

        if (isset($options['size'])) {
            header('Content-length: ' . $options['size']);
        }

        if ($status === true)  {
            $status = '200 OK';
        }

        if ($status === false) {
            $status = '404 Not found';
        }

        $this->httpStatus($status);
    }

    /**
     * PUT method handler.
     *
     * @return void
     */
    public function httpPut()
    {
        if ($this->_checkLockStatus($this->path)) {
            $options                  = array();
            $options['path']          = $this->path;
            $options['contentLength'] = $this->_SERVER['CONTENT_LENGTH'];

            // Get the Content-type
            if (isset($this->_SERVER['CONTENT_TYPE'])) {
                // For now we do not support any sort of multipart requests
                if (!strncmp($this->_SERVER['CONTENT_TYPE'], 'multipart/', 10)) {
                    $this->httpStatus('501 not implemented');
                    echo 'The service does not support mulipart PUT requests';
                    return;
                }
                $options['contentType'] = $this->_SERVER['CONTENT_TYPE'];
            } else {
                // Default content type if none given
                $options['contentType'] = 'application/octet-stream';
            }

            // RFC 2616 2.6 says: "The recipient of the entity MUST NOT
            // ignore any Content-* (e.g. Content-Range) headers that it
            // does not understand or implement and MUST return a 501
            // (Not Implemented) response in such cases."
            foreach ($this->_SERVER as $key => $val) {
                if (strncmp($key, 'HTTP_CONTENT', 11)) {
                    continue;
                }
                switch ($key) {
                    // RFC 2616 14.11
                    case 'HTTP_CONTENT_ENCODING':
                        // TODO support this if ext/zlib filters are available
                        $this->httpStatus('501 not implemented');
                        echo 'The service does not support "' . $val . '" content encoding.';
                        return;
                    // RFC 2616 14.12
                    case 'HTTP_CONTENT_LANGUAGE':
                        // We assume it is not critical if this one is ignored
                        // in the actual PUT implementation ...
                        $options['contentLanguage'] = $val;
                        break;
                    // RFC 2616 14.14
                    case 'HTTP_CONTENT_LOCATION':
                        // The meaning of the Content-Location header in PUT
                        // or POST requests is undefined; servers are free
                        // to ignore it in those cases.
                        break;
                    // RFC 2616 14.16
                    case 'HTTP_CONTENT_RANGE':
                        // Single byte range requests are supported
                        // the header format is also specified in RFC 2616 14.16
                        // TODO we have to ensure that implementations support this or send 501 instead
                        if (!preg_match('@bytes\s+(\d+)-(\d+)/((\d+)|\*)@', $val, $matches)) {
                            $this->httpStatus('400 bad request');
                            echo 'The service does only support single byte ranges.';
                            return;
                        }
                        $range = array(
                            'start' => $matches[1],
                            'end'   => $matches[2]
                        );
                        if (is_numeric($matches[3])) {
                            $range['totalLength'] = $matches[3];
                        }
                        $option['ranges'][] = $range;
                        // TODO make sure the implementation supports partial PUT
                        // this has to be done in advance to avoid data being overwritten
                        // on implementations that do not support this ...
                        break;
                    // RFC 2616 14.15
                    case 'HTTP_CONTENT_MD5':
                        // TODO: maybe we can just pretend here?
                        $this->httpStatus('501 not implemented');
                        echo 'The service does not support content MD5 checksum verification.';
                        return;
                    default:
                        // Any other unknown Content-* headers
                        $this->httpStatus('501 not implemented');
                        echo 'The service does not support "' . $key . '"';
                        return;
                }
            }

            $options['stream'] = fopen("php://input", 'r');
            $stat              = $this->put($options);
            if ($stat === false) {
                $stat = '403 Forbidden';
            } else if (is_resource($stat) && get_resource_type($stat) == 'stream') {
                $stream = $stat;
                $stat   = $options['new'] ? '201 Created' : '204 No Content';
                if (!empty($options['ranges'])) {
                    // TODO multipart support is missing (see also above)
                    if (0 == fseek($stream, $range[0]['start'], SEEK_SET)) {
                        $length = $range[0]['end'] - $range[0]['start'] + 1;
                        if (!fwrite($stream, fread($options['stream'], $length))) {
                            $stat = '403 Forbidden';
                        }
                    } else {
                        $stat = '403 Forbidden';
                    }
                } else {
                    while (!feof($options['stream'])) {
                        if (false === fwrite($stream, fread($options['stream'], 4096))) {
                            $stat = '403 Forbidden';
                            break;
                        }
                    }
                }
                fclose($stream);
            }
            $this->httpStatus($stat);
        } else {
            $this->httpStatus('423 Locked');
        }
    }

    /**
     * PROPPATCH method handler.
     *
     * @return void
     */
    public function httpProppatch()
    {
        if ($this->_checkLockStatus($this->path)) {
            $options         = array();
            $options['path'] = $this->path;

            $propinfo = new Phprojekt_WebDav_Parse_Proppatch("php://input");

            if (!$propinfo->success) {
                $this->httpStatus('400 Error');
                return;
            }

            $options['props'] = $propinfo->props;

            $responsedescr = $this->proppatch($options);

            $this->httpStatus('207 Multi-Status');
            header('Content-Type: text/xml; charset="utf-8"');

            echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

            echo "<D:multistatus xmlns:D=\"DAV:\">\n";
            echo " <D:response>\n";
            echo "  <D:href>" . $this->urlencode($this->_mergePathes($this->_SERVER['SCRIPT_NAME'], $this->path))
                . "</D:href>\n";

            foreach ($options['props'] as $prop) {
                echo "   <D:propstat>\n";
                echo "    <D:prop><" . $prop['name'] . " xmlns=\"" . $prop['ns'] . "\"/></D:prop>\n";
                echo "    <D:status>HTTP/1.1 " . $prop['status'] . "</D:status>\n";
                echo "   </D:propstat>\n";
            }

            if ($responsedescr) {
                echo "  <D:responsedescription>".
                    $this->_propEncode(htmlspecialchars($responsedescr)).
                    "</D:responsedescription>\n";
            }

            echo " </D:response>\n";
            echo "</D:multistatus>\n";
        } else {
            $this->httpStatus('423 Locked');
        }
    }

    /**
     * MKCOL method handler.
     *
     * @return void
     */
    public function httpMkcol()
    {
        $options         = array();
        $options['path'] = $this->path;

        $stat = $this->mkcol($options);

        $this->httpStatus($stat);
    }

    /**
     * DELETE method handler.
     *
     * @return void
     */
    public function httpDelete()
    {
        // Check RFC 2518 Section 9.2, last paragraph
        if (isset($this->_SERVER['HTTP_DEPTH'])) {
            if ($this->_SERVER['HTTP_DEPTH'] != 'infinity') {
                $this->httpStatus('400 Bad Request');
                return;
            }
        }

        // Check lock status
        if ($this->_checkLockStatus($this->path)) {
            // Ok, proceed
            $options         = array();
            $options['path'] = $this->path;

            $stat = $this->delete($options);

            $this->httpStatus($stat);
        } else {
            // Sorry, its locked
            $this->httpStatus('423 Locked');
        }
    }

    /**
     * COPY method handler.
     *
     * @return void
     */
    public function httpCopy()
    {
        // No need to check source lock status here
        // destination lock status is always checked by the helper method
        $this->_copymove('copy');
    }

    /**
     * MOVE method handler.
     *
     * @return void
     */
    public function httpMove()
    {
        if ($this->_checkLockStatus($this->path)) {
            // Destination lock status is always checked by the helper method
            $this->_copymove('move');
        } else {
            $this->httpStatus('423 Locked');
        }
    }

    /**
     * LOCK method handler.
     *
     * @return void
     */
    public function httpLock()
    {
        $options         = array();
        $options['path'] = $this->path;

        if (isset($this->_SERVER['HTTP_DEPTH'])) {
            $options['depth'] = $this->_SERVER['HTTP_DEPTH'];
        } else {
            $options['depth'] = 'infinity';
        }

        if (isset($this->_SERVER['HTTP_TIMEOUT'])) {
            $options['timeout'] = explode(',', $this->_SERVER['HTTP_TIMEOUT']);
        }

        if (empty($this->_SERVER['CONTENT_LENGTH']) && !empty($this->_SERVER['HTTP_IF'])) {
            // Check if locking is possible
            if (!$this->_checkLockStatus($this->path)) {
                $this->httpStatus('423 Locked');
                return;
            }

            // Refresh lock
            $options['locktoken'] = substr($this->_SERVER['HTTP_IF'], 2, -2);
            $options['update']    = $options['locktoken'];

            // Setting defaults for required fields, LOCK() SHOULD overwrite these
            $options['owner'] = 'unknown';
            $options['scope'] = 'exclusive';
            $options['type']  = 'write';

            $stat = $this->lock($options);
        } else {
            // Extract lock request information from request XML payload
            $lockinfo = new Phprojekt_WebDav_Parse_Lockinfo("php://input");
            if (!$lockinfo->success) {
                $this->httpStatus('400 bad request');
            }

            // Check if locking is possible
            if (!$this->_checkLockStatus($this->path, $lockinfo->lockscope === 'shared')) {
                $this->httpStatus('423 Locked');
                return;
            }

            // New lock
            $options['scope']     = $lockinfo->lockscope;
            $options['type']      = $lockinfo->locktype;
            $options['owner']     = $lockinfo->owner;
            $options['locktoken'] = $this->_newLocktoken();

            $stat = $this->lock($options);
        }

        if (is_bool($stat)) {
            $httpStat = $stat ? '200 OK' : '423 Locked';
        } else {
            $httpStat = $stat;
        }
        $this->httpStatus($httpStat);

        if ($httpStat{0} == 2) {
            // 2xx states are ok
            if ($options['timeout']) {
                // More than a million is considered an absolute timestamp
                // less is more likely a relative value
                if ($options['timeout'] > 1000000) {
                    $timeout = 'Second-' . ($options['timeout'] - time());
                } else {
                    $timeout = 'Second-' . $options['timeout'];
                }
            } else {
                $timeout = 'Infinite';
            }

            header('Content-Type: text/xml; charset="utf-8"');
            header('Lock-Token: <' . $options['locktoken'] . '>');
            echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
            echo "<D:prop xmlns:D=\"DAV:\">\n";
            echo " <D:lockdiscovery>\n";
            echo "  <D:activelock>\n";
            echo "   <D:lockscope><D:" . $options['scope'] . "/></D:lockscope>\n";
            echo "   <D:locktype><D:" . $options['type'] . "/></D:locktype>\n";
            echo "   <D:depth>" . $options['depth'] . "</D:depth>\n";
            echo "   <D:owner>" . $options['owner'] . "</D:owner>\n";
            echo "   <D:timeout>" . $timeout . "</D:timeout>\n";
            echo "   <D:locktoken><D:href>" . $options['locktoken'] . "</D:href></D:locktoken>\n";
            echo "  </D:activelock>\n";
            echo " </D:lockdiscovery>\n";
            echo "</D:prop>\n\n";
        }
    }

    /**
     * UNLOCK method handler.
     *
     * @return void
     */
    public function httpUnlock()
    {
        $options         = array();
        $options['path'] = $this->path;

        if (isset($this->_SERVER['HTTP_DEPTH'])) {
            $options['depth'] = $this->_SERVER['HTTP_DEPTH'];
        } else {
            $options['depth'] = 'infinity';
        }

        // Strip surrounding <>
        $options['token'] = substr(trim($this->_SERVER['HTTP_LOCK_TOKEN']), 1, -1);

        // Call user method
        $stat = $this->unlock($options);

        $this->httpStatus($stat);
    }

    /**
     * Check authentication if check is implemented.
     *
     * @return boolean True if authentication succeded or not necessary.
     */
    private function _checkAuth()
    {
        if (method_exists($this, 'checkAuth')) {
            return $this->checkAuth($this->_SERVER['PHP_AUTH_USER'], $this->_SERVER['PHP_AUTH_PW']);
        } else {
            // No method found -> no authentication required
            return true;
        }
    }

    /**
     * Set HTTP return status and mirror it in a private header.
     *
     * @param  string  status code and message.
     * @return void
     */
    public function httpStatus($status)
    {
        // Simplified success case
        if ($status === true) {
            $status = '200 OK';
        }

        // Remember status
        $this->_httpStatus = $status;

        // Generate HTTP status response
        header('HTTP/1.1 ' . $status);
        header('X-WebDAV-Status: ' . $status, true);
    }

    /**
     * Helper for property element creation.
     *
     * @param  string  XML namespace (optional).
     * @param  string  property name.
     * @param  string  property value.
     *
     * @return array   Property array
     */
    public function mkprop()
    {
        $args = func_get_args();
        if (count($args) == 3) {
            return array('ns'   => $args[0],
                         'name' => $args[1],
                         'val'  => $args[2]);
        } else {
            return array('ns'   => 'DAV:',
                         'name' => $args[0],
                         'val'  => $args[1]);
        }
    }

    /**
     * Make sure path ends in a slash.
     *
     * @param string $path Directory path
     *
     * @return string Directory path wiht trailing slash.
     */
    public function slashify($path)
    {
        if ($path[strlen($path) - 1] != '/') {
            $path = $path . '/';
        }

        return $path;
    }

    /**
     * Make sure path doesn't in a slash.
     *
     * @param string $path Directory path.
     *
     * @return string Directory path wihtout trailing slash.
     */
    public function unslashify($path)
    {
        if ($path[strlen($path) - 1] == '/') {
            $path = substr($path, 0, strlen($path) -1);
        }

        return $path;
    }

    /**
     * Generate lockdiscovery reply from checkLock() result.
     *
     * @param string  $path Resource path to check
     *
     * @return string lockdiscovery response.
     */
    public function lockdiscovery($path)
    {
        // No lock support without checkLock() method
        if (!method_exists($this, 'checkLock')) {
            return '';
        }

        // Collect response here
        $activelocks = '';

        // Get checkLock() reply
        $lock = $this->checkLock($path);

        // Generate <activelock> block for returned data
        if (is_array($lock) && count($lock)) {
            // Check for 'timeout' or 'expires'
            if (!empty($lock['expires'])) {
                $timeout = 'Second-' . ($lock['expires'] - time());
            } else if (!empty($lock['timeout'])) {
                $timeout = 'Second-' . $lock['timeout'];
            } else {
                $timeout = 'Infinite';
            }

            // Genreate response block
            $activelocks.= "
              <D:activelock>
               <D:lockscope><D:" . $lock['scope'] . "/></D:lockscope>
               <D:locktype><D:" . $lock['type'] . "/></D:locktype>
               <D:depth>" . $lock['depth'] . "</D:depth>
               <D:owner>" . $lock['owner'] . "</D:owner>
               <D:timeout>" . $timeout . "</D:timeout>
               <D:locktoken><D:href>" . $lock['token'] . "</D:href></D:locktoken>
              </D:activelock>
             ";
        }

        // Return generated response
        return $activelocks;
    }

    /**
     * Conver a string into UTF-8 ONLY if is nessesary.
     *
     * @param string $string String to convert.
     *
     * @return string UTF-8 string.
     */
    public function utf8Encode($string)
    {
        $isUtf     = 0;
        $ss        = array();
        $remainder = strlen($string) % 5000;
        $cycles    = ((strlen($string) - $remainder) / 5000) + (($remainder != 0) ? 1 : 0);
        for ($x = 0; $x < $cycles; $x++) {
            $ss[$x] = substr($string, ($x * 5000), 5000);
        }

        foreach($ss AS $s_) {
            if (preg_match('%^(?:
                [\x09\x0A\x0D\x20-\x7E]              # ASCII
                | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
                |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
                | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
                |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
                |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
                | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
                |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
                )*$%xs', $s_)) {
                $isUtf = 1;
            }
        }

        if (!$isUtf) {
            $string = utf8_encode($string);
        }

        return $string;
    }

    /**
     * Private minimalistic version of PHP urlencode().
     *
     * Only blanks and XML special chars must be encoded here
     * full urlencode() encoding confuses some clients ...
     *
     * @param string $url URL to encode.
     *
     * @return string Encoded URL.
     */
    public function urlencode($url)
    {
        return strtr($url, array(' ' => "%20",
                                 '&' => "%26",
                                 '<' => "%3C",
                                 '>' => "%3E"));
    }

    /**
     * Check if conditions from "If:" headers are meat
     *
     * The "If:" header is an extension to HTTP/1.1
     * defined in RFC 2518 section 9.4
     *
     * @return void
     */
    private function _checkIfHeaderConditions()
    {
        if (isset($this->_SERVER['HTTP_IF'])) {
            $this->_ifHeaderUris = $this->_ifHeaderParser($this->_SERVER['HTTP_IF']);

            foreach ($this->_ifHeaderUris as $uri => $conditions) {
                if ($uri == '') {
                    $uri = $this->uri;
                }
                // All must match
                $state = true;
                foreach ($conditions as $condition) {
                    // Lock tokens may be free form (RFC2518 6.3)
                    // but if opaquelocktokens are used (RFC2518 6.4)
                    // we have to check the format (litmus tests this)
                    if (!strncmp($condition, '<opaquelocktoken:', strlen('<opaquelocktoken'))) {
                        if (!preg_match('/^<opaquelocktoken:[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}>$/', $condition)) {
                            $this->httpStatus('423 Locked');
                            return false;
                        }
                    }
                    if (!$this->_checkUriCondition($uri, $condition)) {
                        $this->httpStatus('412 Precondition failed');
                        $state = false;
                        break;
                    }
                }

                // Any match is ok
                if ($state == true) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Parse If: header.
     *
     * @param  string Header string.
     *
     * @return array URIs and their conditions.
     */
    private function _ifHeaderParser($str)
    {
        $pos  = 0;
        $len  = strlen($str);
        $uris = array();

        // Parser loop
        while ($pos < $len) {
            // Get next token
            $token = $this->_ifHeaderLexer($str, $pos);

            // Check for URI
            if ($token[0] == 'URI') {
                // Remember URI
                $uri = $token[1];
                // Get next token
                $token = $this->_ifHeaderLexer($str, $pos);
            } else {
                $uri = '';
            }

            // Sanity check
            if ($token[0] != 'CHAR' || $token[1] != '(') {
                return false;
            }

            $list  = array();
            $level = 1;
            $not   = '';
            while ($level) {
                $token = $this->_ifHeaderLexer($str, $pos);
                if ($token[0] == 'NOT') {
                    $not = '!';
                    continue;
                }
                switch ($token[0]) {
                    case 'CHAR':
                        switch ($token[1]) {
                            case '(':
                                $level++;
                                break;
                            case ')':
                                $level--;
                                break;
                            default:
                                return false;
                        }
                        break;
                    case 'URI':
                        $list[] = $not . '<' . $token[1] . '>';
                        break;
                    case 'ETAG_WEAK':
                        $list[] = $not . "[W/'" . $token[1] . "']>";
                        break;
                    case 'ETAG_STRONG':
                        $list[] = $not . "['" . $token[1] . "']>";
                        break;
                    default:
                        return false;
                }
                $not = '';
            }

            if (@is_array($uris[$uri])) {
                $uris[$uri] = array_merge($uris[$uri], $list);
            } else {
                $uris[$uri] = $list;
            }
        }

        return $uris;
    }

    /**
     * Check a single URI condition parsed from an if-header.
     *
     * @param string $uri       URI to check.
     * @param string $condition Condition to check for this URI.
     *
     * @return boolean Condition check result.
     */
    private function _checkUriCondition($uri, $condition)
    {
        // Not really implemented here,
        // implementations must override

        // A lock token can never be from the DAV: scheme
        // litmus uses DAV:no-lock in some tests
        if (!strncmp('<DAV:', $condition, 5)) {
            return false;
        }

        return true;
    }

    /**
     * Header lexer.
     *
     * @param  string  Header string to parse.
     * @param  integer Current parsing position.
     *
     * @return array   Next token (type and value).
     */
    private function _ifHeaderLexer($string, &$pos)
    {
        // Skip whitespace
        while (ctype_space($string{$pos})) {
            ++$pos;
        }

        // Already at end of string?
        if (strlen($string) <= $pos) {
            return false;
        }

        // Get next character
        $c = $string{$pos++};

        // Now it depends on what we found
        switch ($c) {
            case '<':
                // URIs are enclosed in <...>
                $pos2 = strpos($string, '>', $pos);
                $uri  = substr($string, $pos, $pos2 - $pos);
                $pos  = $pos2 + 1;
                return array('URI', $uri);
                break;
            case '[':
                // Etags are enclosed in [...]
                if ($string{$pos} == 'W') {
                    $type = 'ETAG_WEAK';
                    $pos += 2;
                } else {
                    $type = 'ETAG_STRONG';
                }
                $pos2 = strpos($string, ']', $pos);
                $etag = substr($string, $pos + 1, $pos2 - $pos - 2);
                $pos  = $pos2 + 1;
                return array($type, $etag);
                break;
            case 'N':
                // 'N' indicates negation
                $pos += 2;
                return array('NOT', 'Not');
                break;
            default:
                // Anything else is passed verbatim char by char
                return array('CHAR', $c);
                break;
        }
    }

    /**
     * Check for implemented HTTP methods.
     *
     * @return array Allowed methods.
     */
    private function _allow()
    {
        // OPTIONS is always there
        $allow = array('OPTIONS' => 'OPTIONS');

        // All other METHODS need both a httpMethod() wrapper
        // and a method() implementation,
        // the base class supplies wrappers only
        foreach (get_class_methods($this) as $method) {
            if (!strncmp('http', $method, 4)) {
                $checkMethod = strtolower(substr($method, 4));
                if (method_exists($this, $checkMethod)) {
                    $allow[$method] = $method;
                }
            }
        }

        // We can emulate a missing HEAD implemetation using GET
        if (isset($allow['GET'])) {
            $allow['HEAD'] = 'HEAD';
        }

        // No LOCK without checkLock()
        if (!method_exists($this, 'checkLock')) {
            unset($allow['LOCK']);
            unset($allow['UNLOCK']);
        }

        return $allow;
    }

    /**
     * Merge two pathes, make sure there is exactly one slash between them.
     *
     * @param string $parent Parent path.
     * @param string $child Child path.
     *
     * @return string Merged path.
     */
    private function _mergePathes($parent, $child)
    {
        if ($child{0} == '/') {
            return $this->unslashify($parent) . $child;
        } else {
            return $this->slashify($parent) . $child;
        }
    }

    /**
     * UTF-8 encode property values if not already done so.
     *
     * @param string $text Text to encode.
     *
     * @return string Utf-8 encoded text.
     */
    private function _propEncode($text)
    {
        switch (strtolower($this->_propEncoding)) {
            case 'utf-8':
                return $text;
                break;
            case 'iso-8859-1':
            case 'iso-8859-15':
            case 'latin-1':
            default:
                return utf8_encode($text);
                break;
        }
    }

    /**
     * Parse HTTP Range: header.
     *
     * @param array $options Array to store result in.
     *
     * @return void
     */
    private function _getRanges(&$options)
    {
        // Process Range: header if present
        if (isset($this->_SERVER['HTTP_RANGE'])) {
            // We only support standard "bytes" range specifications for now
            if (preg_match('/bytes\s*=\s*(.+)/', $this->_SERVER['HTTP_RANGE'], $matches)) {
                $options['ranges'] = array();
                // Ranges are comma separated
                foreach (explode(',', $matches[1]) as $range) {
                    // Ranges are either from-to pairs or just end positions
                    list($start, $end) = explode('-', $range);
                    $options['ranges'][] = ($start === '') ? array('last' => $end)
                        : array('start' => $start,
                                'end'   => $end);
                }
            }
        }
    }

    /**
     * Generate separator headers for multipart response.
     *
     * First and last call happen without parameters to generate
     * the initial header and closing sequence, all calls inbetween
     * require content mimetype, start and end byte position and
     * optionaly the total byte length of the requested resource.
     *
     * @param  string  $mimetype Mymetyoe of the file.
     * @param  integer $from     Start byte position.
     * @param  integer $to       End   byte position.
     * @param  integer $total    Total resource byte size.
     *
     * @return void
     */
    private function _multipartByterangeHeader($mimetype = false, $from = false, $to = false, $total = false)
    {
        if ($mimetype === false) {
            if (!isset($this->multipartSeparator)) {
                // A little naive, this sequence *might* be part of the content
                // but it's really not likely and rather expensive to check
                $this->multipartSeparator = 'SEPARATOR_' . md5(microtime());

                // Generate HTTP header
                header('Content-type: multipart/byteranges; boundary=' . $this->multipartSeparator);
            } else {
                // Generate closing multipart sequence
                echo "\n--{$this->multipartSeparator}--";
            }
        } else {
            // Generate separator and header for next part
            echo "\n--{$this->multipartSeparator}\n";
            echo "Content-type: " . $mimetype . "\n";
            echo "Content-range: " . $from . "-" . $to . "/" . ($total === false ? "*" : $total);
            echo "\n\n";
        }
    }

    /**
     * Check the lock status.
     *
     * @param string $path Path of resource to check.
     *
     * @param  boolean Exclusive lock?
     */
    private function _checkLockStatus($path, $exclusive_only = false)
    {
        // FIXME depth -> ignored for now
        if (method_exists($this, 'checkLock')) {
            // is locked?
            $lock = $this->checkLock($path);

            // ... and lock is not owned?
            if (is_array($lock) && count($lock)) {
                // FIXME doesn't check uri restrictions yet
                if (!isset($this->_SERVER['HTTP_IF']) || !strstr($this->_SERVER['HTTP_IF'], $lock['token'])) {
                    if (!$exclusive_only || ($lock['scope'] !== 'shared')) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Move a resource.
     *
     * @param string $what 'copy' or 'move'.
     *
     * @return void
     */
    private function _copymove($what)
    {
        $options         = array();
        $options['path'] = $this->path;

        if (isset($this->_SERVER['HTTP_DEPTH'])) {
            $options['depth'] = $this->_SERVER['HTTP_DEPTH'];
        } else {
            $options['depth'] = 'infinity';
        }

        extract(parse_url($this->_SERVER['HTTP_DESTINATION']));
        $path     = urldecode($path);
        $httpHost = $host;
        if (isset($port) && $port != 80)
            $httpHost.= ':' . $port;

        $httpHeaderHost = preg_replace("/:80$/", '', $this->_SERVER['HTTP_HOST']);

        if ($httpHost == $httpHeaderHost &&
            !strncmp($this->_SERVER['SCRIPT_NAME'], $path, strlen($this->_SERVER['SCRIPT_NAME']))) {
            $options['dest'] = substr($path, strlen($this->_SERVER['SCRIPT_NAME']));
            if (!$this->_checkLockStatus($options['dest'])) {
                $this->httpStatus('423 Locked');
                return;
            }
        } else {
            $options['destUrl'] = $this->_SERVER['HTTP_DESTINATION'];
        }

        // See RFC 2518 Sections 9.6, 8.8.4 and 8.9.3
        if (isset($this->_SERVER['HTTP_OVERWRITE'])) {
            $options['overwrite'] = $this->_SERVER['HTTP_OVERWRITE'] == 'T';
        } else {
            $options['overwrite'] = true;
        }

        $stat = $this->$what($options);
        $this->httpStatus($stat);
    }

    /**
     * Create a new opaque lock token as defined in RFC2518
     *
     * @return string New RFC2518 opaque lock token.
     */
    private function _newLocktoken()
    {
        return 'opaquelocktoken:' . $this->_newUuid();
    }

    /**
     * Generate Unique Universal IDentifier for lock token.
     *
     * @return string A new UUID.
     */
    private function _newUuid()
    {
        // Use uuid extension from PECL if available
        if (function_exists('uuid_create')) {
            return uuid_create();
        }

        // Fallback
        $uuid = md5(microtime() . getmypid());

        // Set variant and version fields for 'true' random uuid
        $uuid{12} = '4';
        $n        = 8 + (ord($uuid{16}) & 3);
        $hex      = '0123456789abcdef';
        $uuid{16} = $hex{$n};

        // Return formated uuid
        return substr($uuid,  0, 8) . '-' .  substr($uuid,  8, 4) . '-' .  substr($uuid, 12, 4) . '-'
            .  substr($uuid, 16, 4) . '-' .  substr($uuid, 20);
    }
}