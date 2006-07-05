<?php

/**
 * Controls module manipulation
 *
 * Loads modules and their respective files.
 * @version $Id$
 * @author  Matt McNaney <matt at tux dot appstate dot edu>
 * @package Core
 */

if (!defined('FORCE_MOD_CONFIG')) {
    define('FORCE_MOD_CONFIG', TRUE);
 }

class PHPWS_Core {

    /**
     * Loads each module's /inc/init.php file
     */
    function initializeModules()
    {
        if (!$moduleList = PHPWS_Core::getModules()) {
            PHPWS_Error::log(PHPWS_NO_MODULES, 'core', 'initializeModules');
            PHPWS_Core::errorPage();
        }

        if (PEAR::isError($moduleList)) {
            PHPWS_Error::log($moduleList);
            PHPWS_Core::errorPage();
        }
    
        foreach ($moduleList as $mod){
            PHPWS_Core::setCurrentModule($mod['title']);

            /* Using include instead of require to prevent broken mods from hosing the site */
            $includeFile = PHPWS_SOURCE_DIR . 'mod/' . $mod['title'] . '/inc/init.php';

            if (is_file($includeFile)) {
                include($includeFile);
            }

            $GLOBALS['Modules'][$mod['title']] = $mod;
        }
    }

    /**
     * Loads each module's inc/close.php file
     */
    function closeModules()
    {
        if (!isset($GLOBALS['Modules'])) {
            PHPWS_Error::log(PHPWS_NO_MODULES, 'core', 'runtimeModules');
            PHPWS_Core::errorPage();
        }
    
        foreach ($GLOBALS['Modules'] as $mod){
            $includeFile = PHPWS_SOURCE_DIR . 'mod/' . $mod['title'] . '/inc/close.php';
            if (is_file($includeFile)) {
                include($includeFile);
            }
        }
    }

    /**
     * Gets all the modules from the module table
     */
    function getModules($active=TRUE, $just_title=FALSE)
    {
        $DB = & new PHPWS_DB('modules');
        if ($active == TRUE) {
            $DB->addWhere('active', 1);
        }
        $DB->addOrder('priority asc');

        if ($just_title==TRUE) {
            $DB->addColumn('title');
            return $DB->select('col');
        } else {
            return $DB->select();
        }
    }

    /**
     * Returns an associative array of all the modules in the
     * module table
     * Array is indexed with the module title. The value of each
     * row is the module's proper name
     */
    function &getModuleNames()
    {
        if (isset($GLOBALS['Core_Module_Names'])) {
            return $GLOBALS['Core_Module_Names'];
        }

        $db = & new PHPWS_DB('modules');
        $db->setIndexBy('title');
        $db->addOrder('proper_name');
        $db->addColumn('proper_name');
        $db->addColumn('title');
        $result = $db->select('col');
        if (PEAR::isError($result)) {
            PHPWS_Error::log($result);
            return NULL;
        }

        $GLOBALS['Core_Module_Names'] = $result;
        return $GLOBALS['Core_Module_Names'];
    }


    /**
     * Loads each module's inc/runtime.php file
     */
    function runtimeModules()
    {
        if (!isset($GLOBALS['Modules'])) {
            PHPWS_Error::log(PHPWS_NO_MODULES, 'core', 'runtimeModules');
            PHPWS_Core::errorPage();
        }

        foreach ($GLOBALS['Modules'] as $title=>$mod) {
            PHPWS_Core::setCurrentModule($title);
            $runtimeFile = PHPWS_SOURCE_DIR . 'mod/' . $mod['title'] . '/inc/runtime.php';
            is_file($runtimeFile) ? include_once $runtimeFile : NULL;
        }
    }

    /**
     * Loads the index.php file of the currently selected module
     */
    function runCurrentModule()
    {
        if (isset($_REQUEST['module'])) {
            PHPWS_Core::setCurrentModule($_REQUEST['module']);
            $modFile = PHPWS_SOURCE_DIR . 'mod/' . $_REQUEST['module'] . '/index.php';
            if (is_file($modFile)) {
                include $modFile;
            } else {
                PHPWS_Core::errorPage('404');
            }
        }
    }

    /**
     * Requires a module's class file once
     * Returns TRUE is successful, FALSE otherwise
     */
    function initModClass($module, $file)
    {
        $classFile = PHPWS_SOURCE_DIR . 'mod/' . $module . '/class/' . $file;
        if (is_file($classFile)) {
            require_once $classFile;
            return TRUE;
        }
        else {
            PHPWS_Error::log(PHPWS_FILE_NOT_FOUND, 'core', __CLASS__ . '::' .__FUNCTION__, "File: $classFile");
            return FALSE;
        }
    }


    /**
     * Requires a core class file once
     * Returns TRUE is successful, FALSE otherwise
     */
    function initCoreClass($file)
    {
        $classFile = PHPWS_SOURCE_DIR . 'core/class/' . $file;
        if (is_file($classFile)) {
            require_once $classFile;
            return TRUE;
        }
        else {
            PHPWS_Error::log(PHPWS_FILE_NOT_FOUND, 'core', 'initCoreClass', "File: $classFile");
            return FALSE;
        }
    }


    /**
     * Sets the last form post made to the website. 
     * Works with isPosted
     */
    function setLastPost()
    {
        if (!PHPWS_Core::isPosted()) {
            $key = PHPWS_Core::_getPostKey();
            $_SESSION['PHPWS_LastPost'][] = $key;
            if (count($_SESSION['PHPWS_LastPost']) > MAX_POST_TRACK) {
                array_shift($_SESSION['PHPWS_LastPost']);
            }
        }
    }

    /**
     * Makes a post key to track past posts
     * Works with setLastPost and isPosted
     */
    function _getPostKey()
    {
        $key = serialize($_POST);

        if (isset($_FILES)) {
            foreach ($_FILES as $file){
                extract($file);
                $key .= $name . $type . $size;
            }
        }

        return md5($key);
    }

    /**
     * Checks to see if the currently post is in the LastPost
     * session. If so, it returns TRUE. Function can be used to 
     * prevent double posts.
     */
    function isPosted()
    {
        if (!isset($_SESSION['PHPWS_LastPost']) || !isset($_POST)) {
            return FALSE;
        }
        $key = PHPWS_Core::_getPostKey();
        return in_array($key, $_SESSION['PHPWS_LastPost']);
    }
 
    function atHome()
    {
        if (empty($_SERVER['QUERY_STRING']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Returns the user browser to the referer (last web page)
     */
    function goBack()
    {
        PHPWS_Core::reroute($_SERVER['HTTP_REFERER']);
    }

    /**
     * Sends the user to the home page (index.php)
     */
    function home()
    {
        PHPWS_Core::reroute();
    }

    /**
     * Returns a url prefix dependent on the security
     */ 
    function getHttp()
    {
        if ( isset($_SERVER['HTTPS']) &&
             strtolower($_SERVER['HTTPS']) == 'on' ) {
            return 'https://';
        } else {
            return 'http://';
        }
    }

    /**
     * Sends a location header based on the relative link passed
     * to the function.
     */
    function reroute($address=NULL)
    {
        if (!preg_match('/^http/', $address)) {
            $http = PHPWS_Core::getHttp();

            $dirArray = explode('/', $_SERVER['PHP_SELF']);
            array_pop($dirArray);
            $dirArray[] = '';
      
            $directory = implode('/', $dirArray);
      
            $location = $http . $_SERVER['HTTP_HOST'] . $directory . $address;
        } else {
            $location = &$address;
        }

        $location = preg_replace('/&amp;/', '&', $location);
        header('Location: ' . $location);
        exit();
    }

    /**
     * Kills a current page session
     */
    function killSession($sess_name)
    {
        $_SESSION[$sess_name] = NULL;
        unset($_SESSION[$sess_name]);
    }

    /**
     * Kills all sessions currently loaded
     */
    function killAllSessions()
    {
        $_SESSION = array();
        unset($_SESSION);
        session_destroy();
    }// END FUNC killAllSessions()

    /**
     * Returns TRUE is a module is installed, FALSE otherwise
     */
    function moduleExists($module)
    {
        return isset($GLOBALS['Modules'][$module]);
    }

    /**
     * Returns the currently active module
     */
    function getCurrentModule()
    {
        return $GLOBALS['PHPWS_Current_Mod'];
    }

    /**
     * Sets the currently active module
     */
    function setCurrentModule($module)
    {
        $GLOBALS['PHPWS_Current_Mod'] = $module;
    }

    /**
     * Retrieves a module's config file path. If the file
     * does not exist, it returns FALSE instead.
     */
    function getConfigFile($module, $file=NULL)
    {
        if (empty($file)) {
            $file = 'config.php';
        }

        $file = preg_replace('/[^\-\w\.\\\\\/]/', '', $file);
        $module = preg_replace('/[^\w\.]/', '', $module);

        if ($module == 'core') {
            $altfile = PHPWS_SOURCE_DIR . 'config/core/' . $file;
            $file = './config/core/' . $file;
        }
        else {
            $altfile = PHPWS_SOURCE_DIR . 'mod/' . $module . '/conf/' . $file;
            $file = './config/' . $module . '/' . $file;
        }

        if (!is_file($file) || FORCE_MOD_CONFIG) {
            if (!is_file($altfile)) {
                return FALSE;
            } 
            else {
                $file = $altfile;
            }
        }

        return $file;
    }


    /**
     * Pseudoname of configRequireOnce
     */
    function requireConfig($module, $file=NULL, $exitOnError=TRUE)
    {
        return PHPWS_Core::configRequireOnce($module, $file, $exitOnError);
    }

    /**
     * Loads a config file via a require. If missing, shows error page.
     * If file is NULL, function assumes 'config.php'
     */
    function configRequireOnce($module, $file=NULL, $exitOnError=TRUE)
    {
        if (empty($file)) {
            $file = 'config.php';
        }
        $config_file = PHPWS_Core::getConfigFile($module, $file);

        if (empty($config_file) || !$config_file) {
            PHPWS_Error::log(PHPWS_FILE_NOT_FOUND, 'core', 'configRequireOnce', $file);
            if ($exitOnError) {
                PHPWS_Core::errorPage();
            }
            else {
                return $config_file;
            }
        } else {
            require_once $config_file;
        }

        return TRUE;
    }

    /**
     * Loads the core class as a fake module
     */
    function &loadAsMod()
    {
        PHPWS_Core::initCoreClass('Module.php');
    
        $core = & new PHPWS_Module;
        $core->setTitle('core');
        $core->setDirectory(PHPWS_SOURCE_DIR . 'core/');
        $file = PHPWS_Core::getConfigFile('core', 'version.php');
        if (PEAR::isError($file)) {
            return $file;
        } else {
            include $file;
        }

        $core->setVersion($version);
        $core->setRegister(FALSE);
        $core->setImportSQL(TRUE);
        $core->setProperName('Core');

        return $core;
    }

    /**
     * Uses the Pear log class to write a log file to the logs directory
     */
    function log($message, $filename, $type=NULL)
    {
        require_once 'Log.php';

        if (!is_writable(PHPWS_LOG_DIRECTORY)) {
            exit('Unable to write to log directory ' . PHPWS_LOG_DIRECTORY);
        }

        $conf = array('mode' => LOG_PERMISSION, 'timeFormat' => LOG_TIME_FORMAT);
        $log  = &Log::singleton('file', PHPWS_LOG_DIRECTORY . $filename, $type, $conf, PEAR_LOG_NOTICE);

        if (PEAR::isError($log)) {
            return;
        }


        $log->log($message, PEAR_LOG_NOTICE);
        $log->close();
    }

    /**
     * Routes the user to a HTML file. File depends on code passed to it.
     */
    function errorPage($code=NULL)
    {
        switch ($code) {
        case '400':
            include PHPWS_HOME_DIR . 'config/core/400.html';
            break;

        case '403':
            include PHPWS_HOME_DIR . 'config/core/403.html';
            break;

        case '404':
            include PHPWS_HOME_DIR . 'config/core/404.html';
            break;

        case 'overpost':
            include PHPWS_HOME_DIR . 'config/core/overpost.html';
            break;

        default:
            include PHPWS_HOME_DIR . 'config/core/error_page.html';
            break;
        }
        exit();
    }

    /**
     * Returns true if server OS is Windows
     */
    function isWindows()
    {
        if (isset($_SERVER['WINDIR']) ||
            preg_match('/(microsoft|win32)/i', $_SERVER['SERVER_SOFTWARE'])) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * If a file is posted beyond php's posting limits, it will drop the
     * POST without an error message. checkOverPost sends the user to an 
     * overpost error page.
     */
    function checkOverPost()
    {
        if (!isset($_GET['check_overpost'])) {
            return TRUE;
        } elseif (empty($_POST) && isset($_SERVER['CONTENT_LENGTH'])) {
            Security::log(_('User tried to post a file beyond server limits.'));
            PHPWS_Core::errorPage('overpost');
        }

        return TRUE;
    }

    /**
     * If security is enabled, phpwebsite will check the specific directories
     * to make sure they are not writable. These directories are often made writable
     * when a module is updated or installed
     */
    function checkSecurity()
    {
        if (CHECK_DIRECTORY_PERMISSIONS == TRUE) {
            if (is_writable('./config/') || is_writable('./templates/') || is_writable('./javascript/modules/')) {
                PHPWS_Error::log(PHPWS_DIR_NOT_SECURE, 'core');
                PHPWS_Core::errorPage();
            }
        }
    }

    /**
     * Returns an array of the core modules. Set from the core_modules.php file.
     */
    function coreModList()
    {
        static $core_modules = NULL;

        if (is_array($core_modules)) {
            return $core_modules;
        }

        $file = PHPWS_Core::getConfigFile('core', 'core_modules.php');
        if (PEAR::isError($file)) {
            return $file;
        }

        include $file;
        return $core_modules;
    }

    /**
     * Returns an array of all installed modules
     */
    function installModList()
    {
        $db = & new PHPWS_DB('modules');
        $db->addColumn('title');
        return $db->select('col');
    }

    /**
     * Returns an array with containing all the values of
     * the passed object.
     */
    function stripObjValues($object)
    {
        $className = get_class($object);
        $classVars = get_class_vars($className);
        $var_array = NULL;

        if(!is_array($classVars)) {
            return PHPWS_Error::get(PHPWS_CLASS_VARS, 'core',
                                    'PHPWS_Core::stripObjValues', $className);
        }

        foreach ($classVars as $key => $value) {
            if (isset($object->$key)) {
                $var_array[$key] = $object->$key;
            }
        }

        return $var_array;
    }
 
    /**
     * Plugs an array of $variables into the $object. The associative array
     * keys must be identical to the object's variable names.
     *
     * 5/17/06 Removed the code that prevent private variables from loading.
     */
    function plugObject(&$object, $variables)
    {
        $className = get_class($object);
        $classVars = get_class_vars($className);

        if(!is_array($classVars) || empty($classVars)) {
            return PHPWS_Error::get(PHPWS_CLASS_VARS, 'core', 'PHPWS_Core::plugObject', $className);
        }

        if (isset($variables) && !is_array($variables)) {
            return PHPWS_Error::get(PHPWS_WRONG_TYPE, 'core', __CLASS__ . '::' . __FUNCTION__, gettype($variables));
        }


        foreach($classVars as $key => $value) {
            $column = $key;
            if(isset($variables[$column])) {
                if (preg_match('/^[aO]:\d+:/', $variables[$column])) {
                    $object->$key = unserialize($variables[$column]);
                } else {
                    $object->$key = $variables[$column];
                }
            }
        }
        return TRUE;
    }

    /**
     * Returns the installation's home directory
     */
    function getHomeDir()
    {
        $address[] = $_SERVER['DOCUMENT_ROOT'];
        $address[] = dirname($_SERVER['PHP_SELF']);
        return implode('', $address) . '/';
    }

    /**
     * Returns the installations url address
     */
    function getHomeHttp($with_http=TRUE, $with_directory=TRUE, $with_slash=TRUE)
    {
        if ($with_http) {
            $address[] = PHPWS_Core::getHttp();
        }
        $address[] = $_SERVER['HTTP_HOST'];

        if ($with_directory) {
            $address[] = dirname($_SERVER['PHP_SELF']);
        }

        $url = implode('', $address);
        if ($with_slash) {
            $url .= '/';
        }
        return $url;
    }

    /**
     * I am tired of writing this over and over with the php
     * version differences.
     *
     * Returns true if the object is of the entered class.
     * The class name must be lower case. If it isn't well you should 
     * have known PHP 5 was going to change the rules, on get_class
     * shouldn't have ya? In other words, My_Class and my_class are 
     * the same as far as this function is concerned.
     * Mix up your class names.
     */
    function isClass(&$object, $class_name)
    {
        if (!is_object($object)) {
            return FALSE;
        }

        if (strtolower(get_class($object)) == strtolower($class_name)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Returns the core version.
     * 
     * @param boolean get_file  If true, uses the boost.php file, if false
     *                          uses the database version.
     */
    function getVersionInfo($get_file=TRUE)
    {

        $file = PHPWS_SOURCE_DIR . 'core/boost/boost.php';
        include $file;

        if (!$get_file) {
            if (!PHPWS_DB::isTable('core_version')) {
                $version = '1.0.0';
            } else {
                $db = & new PHPWS_DB('core_version');
                $db->addColumn('version');
                $version = $db->select('one');
            }
        }

        return array('proper_name'  => $proper_name,
                     'version'      => $version,
                     'version_http' => $version_http);
    }


    /**
     * Returns the url of the current page
     */
    function getCurrentUrl($relative=TRUE, $use_redirect=TRUE)
    {
        if (!$relative) {
            $address[] = PHPWS_Core::getHomeHttp();
        } 

        if (isset($_SERVER['REDIRECT_URL']) && $use_redirect) {
            $address[] = str_ireplace(dirname($_SERVER['PHP_SELF']) . '/', '', $_SERVER['REDIRECT_URL']);
            return implode('', $address);
        } else {
            $url = $_SERVER['PHP_SELF'];
        }

        $address[] = str_ireplace(dirname($_SERVER['PHP_SELF']) . '/', '', $_SERVER['PHP_SELF']);

        if (!empty($_SERVER['QUERY_STRING'])) {
            $address[] = '?';
            $address[] = $_SERVER['QUERY_STRING'];
        }

        return implode('', $address);
    }

    /**
     * Returns TRUE if the site is a hub or if the site is
     * an allowed branch. If FALSE is returned, the index file 
     * drops the user to an error page. Also sets the Is_Branch GLOBAL
     */
    function checkBranch()
    {
        if (str_ireplace('index.php', '', $_SERVER['SCRIPT_FILENAME']) == PHPWS_SOURCE_DIR) {
            $GLOBALS['Is_Branch'] = FALSE;
            return TRUE;
        } else {
            if (!PHPWS_Core::initModClass('branch', 'Branch.php')) {
                PHPWS_Error::log(PHPWS_HUB_IDENTITY, 'core', 'PHPWS_Core::checkBranch');
                return FALSE;
            }

            if (Branch::checkCurrentBranch()) {
                $GLOBALS['Is_Branch'] = TRUE;
                return TRUE;
            } else {
                PHPWS_Error::log(PHPWS_HUB_IDENTITY, 'core', 'PHPWS_Core::checkBranch');
                return FALSE;
            }
        }
    }

    function isBranch()
    {
        return $GLOBALS['Is_Branch'];
    }

}// End of core class

?>