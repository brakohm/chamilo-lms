<?php
/** For licensing terms, see /license.txt */

/**
 * This is a bootstrap file that loads all Chamilo dependencies including:
 *
 * - Chamilo settings config/configuration.yml or config/configuration.php (in this order, using what if finds first)
 * - Database (Using Doctrine DBAL/ORM)
 * - Templates (Using Twig)
 * - Loading language files (Using Symfony component)
 * - Loading mail settings (Using SwiftMailer smtp/sendmail/mail)
 * - Debug (Using Monolog)
 *
 * ALL Chamilo scripts must include this file in order to have the $app container
 * This script returns a $app Application instance so you have access to all the services.
 *
 * @package chamilo.include
 *
 */

// Fix bug in IIS that doesn't fill the $_SERVER['REQUEST_URI'].
// @todo not sure if we need this
// api_request_uri();
// This is for compatibility with MAC computers.
//ini_set('auto_detect_line_endings', '1');

// Composer auto loader.
require_once __DIR__.'../../../vendor/autoload.php';

use Silex\Application;
use \ChamiloSession as Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Parser;

// Determine the directory path for this file.
$includePath = dirname(__FILE__);

// Start Silex.
$app = new Application();
// @todo add a helper to read the configuration file once!

// Include the main Chamilo platform configuration file.
// @todo use a service provider to load configuration files:
/*
    $app->register(new Igorw\Silex\ConfigServiceProvider($settingsFile));
*/

/** Reading configuration files */
// Reading configuration file from main/inc/conf/configuration.php or app/config/configuration.yml
$configurationFilePath = $includePath.'/conf/configuration.php';
$configurationYMLFile = $includePath.'/../../config/configuration.yml';
$configurationFileAppPath = $includePath.'/../../config/configuration.php';

$alreadyInstalled = false;
if (file_exists($configurationFilePath) || file_exists($configurationYMLFile)  || file_exists($configurationFileAppPath)) {
    if (file_exists($configurationFilePath)) {
        require_once $configurationFilePath;
    }

    if (file_exists($configurationFileAppPath)) {
        $configurationFilePath = $configurationFileAppPath;
        require_once $configurationFileAppPath;
    }
    $alreadyInstalled = true;
} else {
    $_configuration = array();
}

// Overwriting $_configuration
if (file_exists($configurationYMLFile)) {
    $yaml = new Parser();
    $configurationYML = $yaml->parse(file_get_contents($configurationYMLFile));
    if (is_array($configurationYML) && !empty($configurationYML)) {
        if (isset($_configuration)) {
            $_configuration = array_merge($_configuration, $configurationYML);
        } else {
            $_configuration = $configurationYML;
        }
    }
}

/** Setting Chamilo paths */

$app['root_sys'] = isset($_configuration['root_sys']) ? $_configuration['root_sys'] : dirname(dirname(__DIR__)).'/';
$app['sys_root'] = $app['root_sys'];
$app['sys_data_path'] = isset($_configuration['sys_data_path']) ? $_configuration['sys_data_path'] : $app['root_sys'].'data/';
$app['sys_config_path'] = isset($_configuration['sys_config_path']) ? $_configuration['sys_config_path'] : $app['root_sys'].'config/';
$app['sys_course_path'] = isset($_configuration['sys_course_path']) ? $_configuration['sys_course_path'] : $app['sys_data_path'].'/courses/';
$app['sys_temp_path'] = isset($_configuration['sys_temp_path']) ? $_configuration['sys_temp_path'] : $app['sys_data_path'].'temp/';
$app['sys_log_path'] = isset($_configuration['sys_log_path']) ? $_configuration['sys_log_path'] : $app['root_sys'].'logs/';

/** Loading config files (mail, auth, profile) */

if ($alreadyInstalled) {

    $configPath = $app['sys_config_path'];

    $confFiles = array(
        'auth.conf.php',
        'events.conf.php',
        'mail.conf.php',
        'portfolio.conf.php',
        'profile.conf.php'
    );

    foreach ($confFiles as $confFile) {
        if (file_exists($configPath.$confFile)) {
            require_once $configPath.$confFile;
        }
    }

    // Fixing $_configuration array

    // Fixes bug in Chamilo 1.8.7.1 array was not set
    $administrator['email'] = isset($administrator['email']) ? $administrator['email'] : 'admin@example.com';
    $administrator['name'] = isset($administrator['name']) ? $administrator['name'] : 'Admin';

    // Code for transitional purposes, it can be removed right before the 1.8.7 release.
    /*if (empty($_configuration['system_version'])) {
        $_configuration['system_version'] = (!empty($_configuration['dokeos_version']) ? $_configuration['dokeos_version'] : '');
        $_configuration['system_stable'] = (!empty($_configuration['dokeos_stable']) ? $_configuration['dokeos_stable'] : '');
        $_configuration['software_url'] = 'http://www.chamilo.org/';
    }*/

    // For backward compatibility.
    $_configuration['dokeos_version'] = $_configuration['system_version'];
    //$_configuration['dokeos_stable'] = $_configuration['system_stable'];
    $userPasswordCrypted = (!empty($_configuration['password_encryption']) ? $_configuration['password_encryption'] : 'sha1');
}

/** End loading config files */

/** Including legacy libs */
require_once $includePath.'/lib/api.lib.php';

// Setting $_configuration['url_append']
$urlInfo = isset($_configuration['root_web']) ? parse_url($_configuration['root_web']) : null;
$_configuration['url_append'] = null;
if (isset($urlInfo['path'])) {
    $_configuration['url_append'] = '/'.basename($urlInfo['path']);
}

$libPath = $includePath.'/lib/';
$langPath = api_get_path(SYS_LANG_PATH);

// Database constants
require_once $libPath.'database.constants.inc.php';

// @todo Rewrite the events.lib.inc.php in a class
require_once $libPath.'events.lib.inc.php';

// Load allowed tag definitions for kses and/or HTMLPurifier.
require_once $libPath.'formvalidator/Rule/allowed_tags.inc.php';

// Ensure that _configuration is in the global scope before loading
// api.lib.php. This is particularly helpful for unit tests
// @todo do not use $GLOBALS
/*if (!isset($GLOBALS['_configuration'])) {
    $GLOBALS['_configuration'] = $_configuration;
}*/

// Add the path to the pear packages to the include path
ini_set('include_path', api_create_include_path_setting());

$app['configuration_file'] = $configurationFilePath;
$app['configuration_yml_file'] = $configurationYMLFile;
$app['languages_file'] = array();
$app['installed'] = $alreadyInstalled;
$app['app.theme'] = 'chamilo';

// Developer options relies in the configuration.php file

$app['debug'] = isset($_configuration['debug']) ? $_configuration['debug'] : false;
$app['show_profiler'] = isset($_configuration['show_profiler']) ? $_configuration['show_profiler'] : false;

// Enables assetic in order to load 1 compressed stylesheet or split files
//$app['assetic.enabled'] = $app['debug'];
// Harcoded to false by default. Implementation is not finished yet.
$app['assetic.enabled'] = false;

// Dumps assets
$app['assetic.auto_dump_assets'] = false;

// Loading $app settings depending of the debug option
if ($app['debug']) {
    require_once __DIR__.'/../../src/ChamiloLMS/Resources/config/dev.php';
} else {
    require_once __DIR__.'/../../src/ChamiloLMS/Resources/config/prod.php';
}

// Classic way of render pages or the Controller approach
$app['classic_layout'] = false;
$app['full_width'] = false;
$app['breadcrumb'] = array();

// The script is allowed? This setting is modified when calling api_is_not_allowed()
$app['allowed'] = true;

$app->register(new Silex\Provider\SessionServiceProvider());

// Session settings
$app['session.storage.options'] = array(
    'name' => 'chamilo_session',
    //'cookie_lifetime' => 30, //Cookie lifetime
    //'cookie_path' => null, //Cookie path
    //'cookie_domain' => null, //Cookie domain
    //'cookie_secure' => null, //Cookie secure (HTTPS)
    'cookie_httponly' => true //Whether the cookie is http only
);

// Loading chamilo settings
/* @todo create a service provider to load plugins.
   Check how bolt add extensions (including twig templates, config with yml)*/

// Template settings loaded in template.lib.php
$app['template.show_header'] = true;
$app['template.show_footer'] = true;
$app['template.show_learnpath'] = false;
$app['template.hide_global_chat'] = true;
$app['template.load_plugins'] = true;
$app['configuration'] = $_configuration;

$_plugins = array();
if ($alreadyInstalled) {


    /** Including service providers */
    require_once 'services.php';

    // Setting the static database class
    $database = $app['database'];

    // Retrieving all the chamilo config settings for multiple URLs feature
    $_configuration['access_url'] = 1;

    if (api_get_multiple_access_url()) {
        $access_urls = api_get_access_urls();
        $protocol = ((!empty($_SERVER['HTTPS']) && strtoupper($_SERVER['HTTPS']) != 'OFF') ? 'https' : 'http').'://';
        $request_url1 = $protocol.$_SERVER['SERVER_NAME'].'/';
        $request_url2 = $protocol.$_SERVER['HTTP_HOST'].'/';

        foreach ($access_urls as & $details) {
            if ($request_url1 == $details['url'] or $request_url2 == $details['url']) {
                $_configuration['access_url'] = $details['id'];
            }
        }
    }
}

$charset = 'UTF-8';

// Manage Chamilo error messages
$app->error(
    function (\Exception $e, $code) use ($app) {
        if ($app['debug']) {
            //return;
        }
        $message = null;
        if (isset($code)) {
            switch ($code) {
                case 401:
                    $message = 'Unauthorized';
                    break;
                case 404: // not found
                    $message = 'The requested page could not be found.';
                    break;
                default:
                    //$message = 'We are sorry, but something went terribly wrong.';
                    $message = $e->getMessage();
            }
        } else {
            $code = null;
        }
        //$code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
        // It seems that error() is executed first than the before() middleware
        // @ŧodo check this one
        $templateStyle = api_get_setting('template');

        $templateStyle = isset($templateStyle) && !empty($templateStyle) ? $templateStyle : 'default';

        if (!is_dir($app['sys_root'].'main/template/'.$templateStyle)) {
            $templateStyle = 'default';
        }
        $app['template_style'] = $templateStyle;

        // Default layout.
        $app['default_layout'] = $app['template_style'].'/layout/layout_1_col.tpl';
        $app['template']->assign('error', array('code' => $code, 'message' => $message));
        $response = $app['template']->render_layout('error.tpl');

        return new Response($response);
    }
);

// Preserving the value of the global variable $charset.
$charset_initial_value = $charset;

// Section (tabs in the main Chamilo menu)
$app['this_section'] = SECTION_GLOBAL;

// Inclusion of internationalization libraries
require_once $libPath.'internationalization.lib.php';
// Functions for internal use behind this API
require_once $libPath.'internationalization_internal.lib.php';

// Checking if we have a valid language. If not we set it to the platform language.
$cidReset = null;

if ($alreadyInstalled) {

    // Initialization of the internationalization library.
    //api_initialize_internationalization();

    // Initialization of the default encoding that will be used by the multibyte string routines in the internationalization library.
    //api_set_internationalization_default_encoding($charset);

    // require $includePath.'/local.inc.php';


    /**	Loading languages and sublanguages **/
    // @todo improve the language loading

    // if we use the javascript version (without go button) we receive a get
    // if we use the non-javascript version (with the go button) we receive a post

    // Include all files (first english and then current interface language)
    //$app['this_script'] = isset($this_script) ? $this_script : null;

    // Sometimes the variable $language_interface is changed
    // temporarily for achieving translation in different language.
    // We need to save the genuine value of this variable and
    // to use it within the function get_lang(...).
    //$language_interface_initial_value = $language_interface;

    //$this_script = $app['this_script'];

    /* This will only work if we are in the page to edit a sub_language */
    /*
    if (isset($this_script) && $this_script == 'sub_language') {
        require_once api_get_path(SYS_CODE_PATH).'admin/sub_language.class.php';
        // getting the arrays of files i.e notification, trad4all, etc
        $language_files_to_load = SubLanguageManager:: get_lang_folder_files_list(
            api_get_path(SYS_LANG_PATH).'english',
            true
        );
        //getting parent info
        $languageId = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
        $parent_language = SubLanguageManager::get_all_information_of_language($languageId);

        $subLanguageId = isset($_REQUEST['sub_language_id']) ? $_REQUEST['sub_language_id'] : null;

        //getting sub language info
        $sub_language = SubLanguageManager::get_all_information_of_language($subLanguageId);

        $english_language_array = $parent_language_array = $sub_language_array = array();

        if (!empty($language_files_to_load)) {
            foreach ($language_files_to_load as $language_file_item) {
                $lang_list_pre = array_keys($GLOBALS);
                //loading english
                $path = $langPath.'english/'.$language_file_item.'.inc.php';
                if (file_exists($path)) {
                    include $path;
                }

                $lang_list_post = array_keys($GLOBALS);
                $lang_list_result = array_diff($lang_list_post, $lang_list_pre);
                unset($lang_list_pre);

                //  english language array
                $english_language_array[$language_file_item] = compact($lang_list_result);

                //cleaning the variables
                foreach ($lang_list_result as $item) {
                    unset(${$item});
                }
                $parent_file = $langPath.$parent_language['dokeos_folder'].'/'.$language_file_item.'.inc.php';

                if (file_exists($parent_file) && is_file($parent_file)) {
                    include_once $parent_file;
                }
                //  parent language array
                $parent_language_array[$language_file_item] = compact($lang_list_result);

                //cleaning the variables
                foreach ($lang_list_result as $item) {
                    unset(${$item});
                }
                if (!empty($sub_language)) {
                    $sub_file = $langPath.$sub_language['dokeos_folder'].'/'.$language_file_item.'.inc.php';
                    if (file_exists($sub_file) && is_file($sub_file)) {
                        include $sub_file;
                    }
                }

                //  sub language array
                $sub_language_array[$language_file_item] = compact($lang_list_result);

                //cleaning the variables
                foreach ($lang_list_result as $item) {
                    unset(${$item});
                }
            }
        }
    }*/
} else {
    $app['language_interface'] = $language_interface = $language_interface_initial_value = 'english';
}

/**
 * Include all necessary language files
 * - trad4all
 * - notification
 * - custom tool language files
 */
/*
$language_files = array();
$language_files[] = 'trad4all';
$language_files[] = 'notification';
$language_files[] = 'accessibility';

// @todo Added because userportal and index are loaded by a controller should be fixed when a $app['translator'] is configured
$language_files[] = 'index';
$language_files[] = 'courses';
$language_files[] = 'course_home';
$language_files[] = 'exercice';

if (isset($language_file)) {
    if (!is_array($language_file)) {
        $language_files[] = $language_file;
    } else {
        $language_files = array_merge($language_files, $language_file);
    }
}

if (isset($app['languages_file'])) {
    $language_files = array_merge($language_files, $app['languages_file']);
}

// if a set of language files has been properly defined
if (is_array($language_files)) {
    // if the sub-language feature is on
    if (api_get_setting('allow_use_sub_language') == 'true') {
        require_once api_get_path(SYS_CODE_PATH).'admin/sub_language.class.php';
        $parent_path = SubLanguageManager::get_parent_language_path($language_interface);
        foreach ($language_files as $index => $language_file) {
            // include English
            include $langPath.'english/'.$language_file.'.inc.php';
            // prepare string for current language and its parent
            $lang_file = $langPath.$language_interface.'/'.$language_file.'.inc.php';
            $parent_lang_file = $langPath.$parent_path.'/'.$language_file.'.inc.php';
            // load the parent language file first
            if (file_exists($parent_lang_file)) {
                include $parent_lang_file;
            }
            // overwrite the parent language translations if there is a child
            if (file_exists($lang_file)) {
                include $lang_file;
            }
        }
    } else {
        // if the sub-languages feature is not on, then just load the
        // set language interface
        foreach ($language_files as $index => $language_file) {
            // include English
            include $langPath.'english/'.$language_file.'.inc.php';
            // prepare string for current language
            $langFile = $langPath.$language_interface.'/'.$language_file.'.inc.php';

            if (file_exists($langFile)) {
                include $langFile;
            }
        }
    }
}*/

// End loading languages


/** Silex Middlewares. */

/** A "before" middleware allows you to tweak the Request before the controller is executed. */

use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Finder\Finder;

$app->before(

    function () use ($app) {
         /** @var Request $request */
        $request = $app['request'];

        // Checking configuration file. If does not exists redirect to the install folder.
        if (!file_exists($app['configuration_file']) && !file_exists($app['configuration_yml_file'])) {
            $url = str_replace('web', 'main/install', $request->getBasePath());
            return new RedirectResponse($url);
        }

        // Check the PHP version.
        if (api_check_php_version() == false) {
            $app->abort(500, "Incorrect PHP version.");
        }

        // Check data folder

        if (!is_writable(api_get_path(SYS_DATA_PATH))) {
            $app->abort(500, "data folder must be writable.");
        }

        // Checks temp folder permissions.
        if (!is_writable(api_get_path(SYS_ARCHIVE_PATH))) {
            $app->abort(500, "data/temp folder must be writable.");
        }

        // Checking that configuration is loaded
        if (!isset($app['configuration'])) {
            $app->abort(500, '$configuration array must be set in the configuration.php file.');
        }

        if (!isset($app['configuration']['root_web'])) {
            $app->abort(500, '$configuration[root_web] must be set in the configuration.php file.');
        }

        // Starting the session for more info see: http://silex.sensiolabs.org/doc/providers/session.html
        $request->getSession()->start();

        /** @var ChamiloLMS\Component\DataFilesystem\DataFilesystem $filesystem */
        $filesystem = $app['chamilo.filesystem'];

        if ($app['debug']) {
            // Creates data/temp folders for every request if debug is on.
            $filesystem->createFolders($app['temp.paths']->folders);
        }

        // If Assetic is enabled copy folders from theme inside "web/"
        if ($app['assetic.auto_dump_assets']) {
            $filesystem->copyFolders($app['temp.paths']->copyFolders);
        }

        // Check and modify the date of user in the track.e.online table
        Online::loginCheck(api_get_user_id());

        // Setting access_url id (multiple url feature)

        if (api_get_multiple_access_url()) {
            //for some reason $app['configuration'] doesn't work. Use $_config
            global $_configuration;
            Session::write('url_id', $_configuration['access_url']);
            Session::write('url_info', api_get_current_access_url_info($_configuration['access_url']));
        } else {
            Session::write('url_id', 1);
        }

        // Loading portal settings from DB.
        $settings_refresh_info = api_get_settings_params_simple(array('variable = ?' => 'settings_latest_update'));
        $settings_latest_update = $settings_refresh_info ? $settings_refresh_info['selected_value'] : null;

        $_setting = Session::read('_setting');

        if (empty($_setting)) {
            api_set_settings_and_plugins();
        } else {
            if (isset($_setting['settings_latest_update']) && $_setting['settings_latest_update'] != $settings_latest_update) {
                api_set_settings_and_plugins();
            }
        }

        $app['plugins'] = Session::read('_plugins');

        // Default template style.
        $templateStyle = api_get_setting('template');
        $templateStyle = isset($templateStyle) && !empty($templateStyle) ? $templateStyle : 'default';
        if (!is_dir($app['sys_root'].'main/template/'.$templateStyle)) {
            $templateStyle = 'default';
        }
        $app['template_style'] = $templateStyle;

        // Default layout.
        $app['default_layout'] = $app['template_style'].'/layout/layout_1_col.tpl';

        // Setting languages.
        $app['api_get_languages'] = api_get_languages();
        $app['language_interface'] = $language_interface = api_get_language_interface();

        // Reconfigure template now that we know the user.
        $app['template.hide_global_chat'] = !api_is_global_chat_enabled();

        /** Setting the course quota */
        // Default quota for the course documents folder
        $default_quota = api_get_setting('default_document_quotum');
        // Just in case the setting is not correctly set
        if (empty($default_quota)) {
            $default_quota = 100000000;
        }

        define('DEFAULT_DOCUMENT_QUOTA', $default_quota);

        // Specification for usernames:
        // 1. ASCII-letters, digits, "." (dot), "_" (underscore) are acceptable, 40 characters maximum length.
        // 2. Empty username is formally valid, but it is reserved for the anonymous user.
        // 3. Checking the login_is_email portal setting in order to accept 100 chars maximum

        $default_username_length = 40;
        if (api_get_setting('login_is_email') == 'true') {
            $default_username_length = 100;
        }

        define('USERNAME_MAX_LENGTH', $default_username_length);

        $user = null;

        /** Security component. */
        if ($app['security']->isGranted('IS_AUTHENTICATED_FULLY')) {

            // Checking token in order to get the current user.
            $token = $app['security']->getToken();
            if (null !== $token) {
                /** @var Entity\User $user */
                $user = $token->getUser();
            }

            // For backward compatibility.
            $userInfo = api_get_user_info($user->getUserId());
            $userInfo['is_anonymous'] = false;

            Session::write('_user', $userInfo);
            $app['current_user'] = $userInfo;

            // Setting admin permissions.
            if ($app['security']->isGranted('ROLE_ADMIN')) {
                Session::write('is_platformAdmin', true);
            }

            // Setting teachers permissions.
            if ($app['security']->isGranted('ROLE_TEACHER')) {
                Session::write('is_allowedCreateCourse', true);
            }

        } else {
            Session::erase('_user');
            Session::erase('is_platformAdmin');
            Session::erase('is_allowedCreateCourse');
        }

        /** Translator component. */
        // Platform lang

        $language = api_get_setting('platformLanguage');
        $iso = api_get_language_isocode($language);
        $app['translator']->setLocale($iso);

        // From the login page
        $language = $request->get('language');

        if (!empty($language)) {
            $iso = api_get_language_isocode($language);
            $app['translator']->setLocale($iso);
        }

        // From the user
        if ($user && $userInfo) {
            // @todo check why this does not works
            //$language = $user->getLanguage();
            $language = $userInfo['language'];
            $iso = api_get_language_isocode($language);
            $app['translator']->setLocale($iso);
        }

        // From the course
        $courseInfo = api_get_course_info();
        if ($courseInfo && !empty($courseInfo)) {
            $iso = api_get_language_isocode($courseInfo['language']);
            $app['translator']->setLocale($iso);
        }

        $file = $request->get('file');
        $section = null;
        if (!empty($file)) {
            $info = pathinfo($file);
            $section = $info['dirname'];
        }

        $app['translator.cache.enabled'] = false;

        $app['translator'] = $app->share($app->extend('translator', function ($translator, $app) {

            $locale = $translator->getLocale();

            /** @var Symfony\Component\Translation\Translator $translator  */
            if ($app['translator.cache.enabled']) {

                //$phpFileDumper = new Symfony\Component\Translation\Dumper\PhpFileDumper();
                $dumper = new Symfony\Component\Translation\Dumper\MoFileDumper();
                $catalogue = new Symfony\Component\Translation\MessageCatalogue($locale);
                $catalogue->add(array('foo' => 'bar'));
                $dumper->dump($catalogue, array('path' => $app['sys_temp_path']));

            } else {
                $translator->addLoader('pofile', new PoFileLoader());

                $filesToLoad = array(
                    api_get_path(SYS_PATH).'main/locale/'.$locale.'.po',
                    api_get_path(SYS_PATH).'main/locale/'.$locale.'.custom.po'
                );

                foreach ($filesToLoad as $file) {
                    if (file_exists($file)) {
                        $translator->addResource('pofile', $file, $locale);
                    }
                }

                /*$translator->addLoader('mofile', new MoFileLoader());
                $filePath = api_get_path(SYS_PATH).'main/locale/'.$locale.'.mo';
                if (!file_exists($filePath)) {
                    $filePath = api_get_path(SYS_PATH).'main/locale/en.mo';
                }
                $translator->addResource('mofile', $filePath, $locale);*/
                return $translator;
            }
        }));

        // Check if we are inside a Chamilo course tool
        $isCourseTool = (strpos($request->getPathInfo(), 'courses/') === false) ? false : true;

        // Setting course entity for controllers and templates
        if ($isCourseTool) {
            // The course parameter is loaded
            $course = $request->get('course');

            // Converting /courses/XXX/ to a Entity/Course object
            $course = $app['orm.em']->getRepository('Entity\Course')->findOneByCode($course);
            $app['course'] = $course;
            $app['template']->assign('course', $course);

            $sessionId = $request->get('id_session');
            $session = $app['orm.em']->getRepository('Entity\Session')->findOneById($sessionId);
            $app['course_session'] = $session;

            $app['template']->assign('course_session', $session);
        }
    }
);

/** An after application middleware allows you to tweak the Response before it is sent to the client */
$app->after(
    function (Request $request, Response $response) {

    }
);

/** A "finish" application middleware allows you to execute tasks after the Response has been sent to
 * the client (like sending emails or logging) */
$app->finish(
    function (Request $request) use ($app) {

    }
);
// End Silex Middlewares

// The global variable $charset has been defined in a language file too (trad4all.inc.php), this is legacy situation.
// So, we have to reassign this variable again in order to keep its value right.
$charset = $charset_initial_value;

// The global variable $text_dir has been defined in the language file trad4all.inc.php.
// For determing text direction correspondent to the current language we use now information from the internationalization library.
$text_dir = api_get_text_direction();

/** "Login as user" custom script */
// @todo move this code in a controller
if (!isset($_SESSION['login_as']) && isset($_user)) {
    // if $_SESSION['login_as'] is set, then the user is an admin logged as the user

    $tbl_track_login = Database :: get_main_table(TABLE_STATISTIC_TRACK_E_LOGIN);
    $sql_last_connection = "SELECT login_id, login_date FROM $tbl_track_login
                            WHERE login_user_id = '".api_get_user_id()."'
                            ORDER BY login_date DESC LIMIT 0,1";

    $q_last_connection = Database::query($sql_last_connection);

    if (Database::num_rows($q_last_connection) > 0) {
        $i_id_last_connection = Database::result($q_last_connection, 0, 'login_id');

        // is the latest logout_date still relevant?
        $sql_logout_date = "SELECT logout_date FROM $tbl_track_login WHERE login_id = $i_id_last_connection";
        $q_logout_date = Database::query($sql_logout_date);
        $res_logout_date = api_convert_sql_date(Database::result($q_logout_date, 0, 'logout_date'));

        if ($res_logout_date < time() - $app['configuration']['session_lifetime']) {
            // now that it's created, we can get its ID and carry on
            $q_last_connection = Database::query($sql_last_connection);
            $i_id_last_connection = Database::result($q_last_connection, 0, 'login_id');
        }
        $now = api_get_utc_datetime();
        $s_sql_update_logout_date = "UPDATE $tbl_track_login SET logout_date = '$now' WHERE login_id = $i_id_last_connection";
        Database::query($s_sql_update_logout_date);
    }
}

// Add language_measure_frequency to your main/inc/conf/configuration.php in
// order to generate language variables frequency measurements (you can then
// see them through main/cron/lang/langstats.php)
// The langstat object will then be used in the get_lang() function.
// This block can be removed to speed things up a bit as it should only ever
// be used in development versions.
// @todo create a service provider to load this
if (isset($app['configuration']['language_measure_frequency']) && $app['configuration']['language_measure_frequency'] == 1) {
    require_once api_get_path(SYS_CODE_PATH).'/cron/lang/langstats.class.php';
    $langstats = new langstats();
}


/** Setting the is_admin key */
$app['is_admin'] = false;

/** Including routes */
require_once 'routes.php';

// Setting doctrine2 extensions

if (isset($app['configuration']['main_database']) && isset($app['db.event_manager'])) {

    // @todo improvement do not create every time this objects
    $sortableGroup = new Gedmo\Mapping\Annotation\SortableGroup(array());
    $sortablePosition = new Gedmo\Mapping\Annotation\SortablePosition(array());
    $tree = new Gedmo\Mapping\Annotation\Tree(array());
    $tree = new Gedmo\Mapping\Annotation\TreeParent(array());
    $tree = new Gedmo\Mapping\Annotation\TreeLeft(array());
    $tree = new Gedmo\Mapping\Annotation\TreeRight(array());
    $tree = new Gedmo\Mapping\Annotation\TreeRoot(array());
    $tree = new Gedmo\Mapping\Annotation\TreeLevel(array());
    $tree = new Gedmo\Mapping\Annotation\Versioned(array());
    $tree = new Gedmo\Mapping\Annotation\Loggable(array());
    $tree = new Gedmo\Loggable\Entity\LogEntry();

    // Setting Doctrine2 extensions
    $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
    // $app['db.event_manager']->addEventSubscriber($timestampableListener);
    $app['dbs.event_manager']['db_read']->addEventSubscriber($timestampableListener);
    $app['dbs.event_manager']['db_write']->addEventSubscriber($timestampableListener);

    $sluggableListener = new \Gedmo\Sluggable\SluggableListener();
    // $app['db.event_manager']->addEventSubscriber($sluggableListener);
    $app['dbs.event_manager']['db_read']->addEventSubscriber($sluggableListener);
    $app['dbs.event_manager']['db_write']->addEventSubscriber($sluggableListener);

    $sortableListener = new Gedmo\Sortable\SortableListener();
    // $app['db.event_manager']->addEventSubscriber($sortableListener);
    $app['dbs.event_manager']['db_read']->addEventSubscriber($sortableListener);
    $app['dbs.event_manager']['db_write']->addEventSubscriber($sortableListener);

    $treeListener = new \Gedmo\Tree\TreeListener();
    //$treeListener->setAnnotationReader($cachedAnnotationReader);
    // $app['db.event_manager']->addEventSubscriber($treeListener);
    $app['dbs.event_manager']['db_read']->addEventSubscriber($treeListener);
    $app['dbs.event_manager']['db_write']->addEventSubscriber($treeListener);

    $loggableListener = new \Gedmo\Loggable\LoggableListener();
    if (PHP_SAPI != 'cli') {
        //$userInfo = api_get_user_info();

        if (isset($userInfo) && !empty($userInfo['username'])) {
            //$loggableListener->setUsername($userInfo['username']);
        }
    }
    $app['dbs.event_manager']['db_read']->addEventSubscriber($loggableListener);
    $app['dbs.event_manager']['db_write']->addEventSubscriber($loggableListener);
}

// Fixes uses of $_course in the scripts.
$_course = api_get_course_info();
$_cid = api_get_course_id();

return $app;
