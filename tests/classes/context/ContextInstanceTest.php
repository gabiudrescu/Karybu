<?php

if(!defined('__KARYBU__')) require dirname(__FILE__).'/../../Bootstrap.php';

require_once _KARYBU_PATH_.'classes/context/Context.class.php';
require_once _KARYBU_PATH_.'classes/handler/Handler.class.php';
require_once _KARYBU_PATH_.'classes/xml/XmlParser.class.php';

if(!class_exists('FrontendFileHandler')){
    class FrontendFileHandler {}
}
if(!class_exists('FileHandler')){
    class FileHandler {}
}
if(!class_exists('Validator')){
    class Validator {}
}

class ContextInstanceTest extends PHPUnit_Framework_TestCase
{
    public function testRequestMethod_Default()
    {
        $context = new ContextInstance();
        $this->assertEquals('GET', $context->getRequestMethod());
    }

    public function testRequestMethod_POST()
    {
        $context = new ContextInstance();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertEquals('POST', $context->getRequestMethod());
    }

    public function testRequestMethod_XMLRPC()
    {
        $context = new ContextInstance();
        $GLOBALS['HTTP_RAW_POST_DATA'] = 'abcde';
        $this->assertEquals('XMLRPC', $context->getRequestMethod());
    }

    public function testRequestMethod_JSON()
    {
        $context = new ContextInstance();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertEquals('JSON', $context->getRequestMethod());
    }

    public function testRequestMethod_ManuallySet()
    {
        $context = new ContextInstance();
        $context->setRequestMethod('POST');
        $this->assertEquals('POST', $context->getRequestMethod());
    }

    public function testResponseMethod_Default()
    {
        $context = new ContextInstance();
        $this->assertEquals('HTML', $context->getResponseMethod());
    }

    public function testReponseMethod_WhenRequestIs_JSON()
    {
        $context = new ContextInstance();
        $context->setRequestMethod('JSON');
        $this->assertEquals('JSON', $context->getResponseMethod());
    }

    public function testResponseMethod_WhenUserManuallySetsInvalidData()
    {
        $context = new ContextInstance();
        $context->setResponseMethod('WRONG_TYPE');
        $this->assertEquals('HTML', $context->getResponseMethod());
    }

    public function testResponseMethod_WhenUserManuallySetsValidData()
    {
        $context = new ContextInstance();
        $context->setResponseMethod('XMLRPC');
        $this->assertEquals('XMLRPC', $context->getResponseMethod());
        $context->setResponseMethod('HTML');
        $this->assertEquals('HTML', $context->getResponseMethod());
    }

    /**
     * Test that request arguments are propely initialized when
     * Request type is XMLRPC
     *
     * Data sent:
     *    <?xml version="1.0" encoding="utf-8" ?>
     *    <methodCall>
     *        <params>
     *            <module><![CDATA[admin]]></module>
     *            <act><![CDATA[procAdminRecompileCacheFile]]></act>
     *        </params>
     *    </methodCall>
     *
     * Sample data taken from the "Re-create cache file" button in Karybu Admin Dashboard footer
     */
    public function testSetArguments_XMLRPC()
    {
        // Set up object that will be returned after parsing input XML
        $module = new Xml_Node_();
        $module->node_name = "module";
        $module->body = "admin";

        $act = new Xml_Node_();
        $act->node_name = "act";
        $act->body = "procAdminRecompileCacheFile";

        $params = new Xml_Node_();
        $params->module = $module;
        $params->act = $act;

        $methodcall = new Xml_Node_();
        $methodcall->params = $params;

        $xml_obj = new stdClass();
        $xml_obj->methodcall = $methodcall;

        $parser = $this->getMock('XmlParser', array('parse'));
        $parser
            ->expects($this->any())
            ->method('parse')
            ->will($this->returnValue($xml_obj));

        $context = new ContextInstance();
        $context->setRequestMethod('XMLRPC');

        $context->_setXmlRpcArgument($parser);

        $data = new stdClass();
        $data->module = 'admin';
        $data->act = 'procAdminRecompileCacheFile';

        $arguments = $context->getRequestVars();
        $this->assertEquals($data, $arguments);
        $arguments = $context->getAll();
        $this->assertEquals($data, $arguments);
    }

    /**
     * Test that request arguments are properly initialized when
     * Request type is JSON
     *
     * Data sent:
     *  domain=&module=admin&act=getSiteAllList
     *
     * Sample data taken from Admin, General settings page, the Select Default Module textbox
     * $.exec_json('module.procModuleAdminGetList', {site_srl:$this.data('site_srl')}, on_complete);
     */
    public function testSetArguments_JSON()
    {
        $context = new ContextInstance();
        $context->setRequestMethod('JSON');

        $GLOBALS['HTTP_RAW_POST_DATA'] = "domain=&module=admin&act=getSiteAllList";
        $context->_setJSONRequestArgument();

        $data = new stdClass();
        $data->module = 'admin';
        $data->act = 'getSiteAllList';

        $arguments = $context->getRequestVars();
        $this->assertEquals($data, $arguments);

        $data->domain = "";
        $arguments = $context->getAll();
        $this->assertEquals($data, $arguments);

    }


    /**
     * $_REQUEST holds all data in $_GET, $_POST and $_COOKIE
     * Only what is in $_GET and $_POST should be set in the Request vars
     */
    public function testSetArguments_REQUEST()
    {
        $_GET = array("module" => "admin", "act" => "dispLayoutAdminAllInstanceList");
        $_POST = array();
        $_COOKIE = array("XDEBUG_SESSION_START" => "1234");

        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $data = new stdClass();
        $data->module = 'admin';
        $data->act = 'dispLayoutAdminAllInstanceList';

        $arguments = $context->getRequestVars();
        $this->assertEquals($data, $arguments);

        $data->XDEBUG_SESSION_START = "1234";
        $arguments = $context->getAll();
        $this->assertEquals($data, $arguments);
    }

    private function getContextMockForFileUploads($is_uploaded_file = true, $multiple_files = false)
    {
        if(!$multiple_files)
        {
            $myFiles = array(
                "product_image" => array(
                    "name" => "400.png",
                    "type" => "image/png",
                    "tmp_name" => "/tmp/abcdef",
                    "error" => 0,
                    "size" => 15726
                ));
        }
        else
        {
            $myFiles = array(
                "product_image" => array(
                    "name" => array("400.png"),
                    "type" => array("image/png"),
                    "tmp_name" => array("/tmp/abcdef"),
                    "error" => array(0),
                    "size" => array(15726)
                ));
        }

        // Mock just the isUploadedFile, getFiles and getRequestContentType methods
        $context = $this->getMock('ContextInstance', array('is_uploaded_file', 'getFiles', 'getRequestContentType'));
        $context
            ->expects($this->any())
            ->method('is_uploaded_file')
            ->will($this->returnValue($is_uploaded_file));

        $context
            ->expects($this->any())
            ->method('getFiles')
            ->will($this->returnValue($myFiles));

        $context
            ->expects($this->any())
            ->method('getRequestContentType')
            ->will($this->returnValue('multipart/form-data'));

        return $context;
    }


    /**
     * If $_FILES has data, but request type is GET, nothing should happen
     */
    public function testSetArguments_FileUpload_GET_Request()
    {
        $context = new ContextInstance();
        $context->setRequestMethod('GET');

        $context->_setUploadedArgument();

        $this->assertEquals(false, $context->isUploaded());
        $this->assertEquals(new stdClass(), $context->getRequestVars());
    }

    /**
     * If request method is POST, but data sent is not multipart/form-data
     * nothing should happen
     */
    public function testSetArguments_FileUpload_NotMultipartFormData()
    {
        $context = $this->getMock('ContextInstance', array('getRequestContentType'));

        $context
            ->expects($this->any())
            ->method('getRequestContentType')
            ->will($this->returnValue('text/html'));

        $context->setRequestMethod('POST');
        $context->_setUploadedArgument();

        $this->assertEquals(false, $context->isUploaded());
        $this->assertEquals(new stdClass(), $context->getRequestVars());
    }

    /**
     * If $_FILES is empty, do nothing
     */
    public function testSetArguments_FileUpload_EmptyFILES()
    {
        $context = $this->getMock('ContextInstance', array('getFiles'));

        $context
            ->expects($this->any())
            ->method('getFiles')
            ->will($this->returnValue(null));

        $context->setRequestMethod('POST');
        $context->_setUploadedArgument();

        $this->assertEquals(false, $context->isUploaded());
        $this->assertEquals(new stdClass(), $context->getRequestVars());
    }

    /**
     * Test that arguments are properly intialized one just one file
     * was uploaded
     */
    public function testSetArguments_FileUpload_JustOneFile()
    {
        $context = $this->getContextMockForFileUploads();
        $context->setRequestMethod("POST");

        $context->_setUploadedArgument();

        $this->assertEquals(true, $context->isUploaded());

        $data = new stdClass();
        $data->product_image = array(
            "name" => "400.png",
            "type" => "image/png",
            "tmp_name" => "/tmp/abcdef",
            "error" => 0,
            "size" => 15726
        );

        $arguments = $context->getRequestVars();
        $this->assertEquals($data, $arguments);


        $arguments = $context->getAll();
        $this->assertEquals($data, $arguments);
    }

    /**
     * Test that arguments are not initialized when just one file
     * was uploaded, but it is invalid
     */
    public function testSetArguments_FileUpload_JustOneFile_FakeUpload()
    {
        $context = $this->getContextMockForFileUploads(false);

        $context->setRequestMethod("POST");

        $context->_setUploadedArgument();

        $this->assertEquals(false, $context->isUploaded());
        $this->assertEquals(new stdClass(), $context->getRequestVars());
    }

    /**
     * Test that arguments were properly initialized when more than one file was uploaded
     */
    public function testSetArguments_FileUpload_Array()
    {
        $context = $this->getContextMockForFileUploads(true, true);
        $context->setRequestMethod("POST");

        $context->_setUploadedArgument();

        $data = new stdClass();
        $data->product_image = array(
            array(
                "name" => "400.png",
                "type" => "image/png",
                "tmp_name" => "/tmp/abcdef",
                "error" => 0,
                "size" => 15726
            ));

        $arguments = $context->getRequestVars();
        $this->assertEquals($data, $arguments);

        $arguments = $context->getAll();
        $this->assertEquals($data, $arguments);
    }

    /**
     * Test that the is_uploaded property is updated for multiple file uploads
     */
    public function testSetArguments_FileUpload_Array_SetsIsUploaded()
    {
        $context = $this->getContextMockForFileUploads(true, true);
        $context->setRequestMethod("POST");

        $context->_setUploadedArgument();

        $data = new stdClass();
        $data->product_image = array(
            array(
                "name" => "400.png",
                "type" => "image/png",
                "tmp_name" => "/tmp/abcdef",
                "error" => 0,
                "size" => 15726
            ));

        $this->assertEquals(true, $context->isUploaded());
    }

    /**
     * Test that arguments are not initialized if this was a fake upload
     * (check using is_uploaded_file php function)
     */
    public function testSetArguments_FileUpload_Array_FakeUpload()
    {
        $context = $this->getContextMockForFileUploads(false, true);

        $context->setRequestMethod("POST");

        $context->_setUploadedArgument();

        $this->assertEquals(false, $context->isUploaded());
        $this->assertEquals(new stdClass(), $context->getRequestVars());
    }

    private function getContextMockForDbInfoLoading($db_info, $site_module_info = null)
    {
        $context = $this->getMock('ContextInstance', array('loadDbInfoFromConfigFile', 'isInstalled', 'getSiteModuleInfo'));
        $context
            ->expects($this->any())
            ->method('isInstalled')
            ->will($this->returnValue(true));

        if(!isset($db_info->master_db)) $db_info->master_db = null;
        if(!isset($db_info->slave_db)) $db_info->slave_db = null;
        if(!isset($db_info->default_url)) $db_info->default_url = null;
        if(!isset($db_info->lang_type))  $db_info->lang_type = null;
        if(!isset($db_info->use_rewrite)) $db_info->use_rewrite = null;
        if(!isset($db_info->time_zone))$db_info->time_zone = null;
        if(!isset($db_info->use_prepared_statements)) $db_info->use_prepared_statements = null;
        if(!isset($db_info->qmail_compatibility)) $db_info->qmail_compatibility = null;
        if(!isset($db_info->use_db_session)) $db_info->use_db_session = null;
        if(!isset($db_info->use_ssl)) $db_info->use_ssl = null;
        if(!isset($db_info->default_language)) $db_info->default_language = null;
        if(!isset($db_info->http_port)) $db_info->http_port = null;
        if(!isset($db_info->https_port)) $db_info->https_port = null;

        $context
            ->expects($this->any())
            ->method('loadDbInfoFromConfigFile')
            ->will($this->returnValue($db_info));

        if($site_module_info == null) {
            $site_module_info = new stdClass();
            $site_module_info->site_srl = null;
            $site_module_info->domain = null;
            $site_module_info->default_language = null;
        }
        $context
            ->expects($this->any())
            ->method('getSiteModuleInfo')
            ->will($this->returnValue($site_module_info));

        $context->initializeDatabaseSettings();

        return $context;
    }

    /**
     * Test app configuration
     */
    public function testLoadDbInfo_DefaultValues()
    {
        $db_info = new stdClass();
        $db_info->master_db = array('db_type' => 'mysql','db_port' => '3306','db_hostname' => 'localhost','db_userid' => 'root','db_password' => 'password','db_database' => 'globalcms','db_table_prefix' => 'xe_');
        $db_info->slave_db = array(array('db_type' => 'mysql','db_port' => '3306','db_hostname' => 'localhost','db_userid' => 'root','db_password' => 'password','db_database' => 'globalcms','db_table_prefix' => 'xe_'));
        $db_info->default_url = 'http://globalcms/';
        $db_info->lang_type = 'en';
        $db_info->use_rewrite = 'Y';
        $db_info->time_zone = '+0200';
        $db_info->use_prepared_statements = null;
        $db_info->qmail_compatibility = null;
        $db_info->use_db_session = null;
        $db_info->use_ssl = null;
        $db_info->default_language = null;
        $db_info->http_port = null;
        $db_info->https_port = null;

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);
        $expected_db_info->time_zone = '+0200';
        $expected_db_info->use_prepared_statements = 'Y';
        $expected_db_info->qmail_compatibility = 'N';
        $expected_db_info->use_db_session = 'N';
        $expected_db_info->use_ssl = 'none';

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info, $actual_db_info);
    }

    /**
     * Test app configuration - prepared statements
     */
    public function testLoadDbInfo_PreparedStatements()
    {
        // Test that the default value for this is Y
        $db_info = new stdClass();
        $db_info->master_db = array('something');

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);
        $expected_db_info->use_prepared_statements = 'Y';

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->use_prepared_statements, $actual_db_info->use_prepared_statements);

        // Test that when value is manually set, it is not overridden
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->use_prepared_statements = 'N';

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->use_prepared_statements, $actual_db_info->use_prepared_statements);
    }

    /**
     * Test app configuration - time zone
     */
    public function testLoadDbInfo_TimeZone()
    {
        // Test that the default value for this is date('0')
        $db_info = new stdClass();
        $db_info->master_db = array('something');

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);
        $expected_db_info->time_zone = date('O');

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->time_zone, $actual_db_info->time_zone);

        // Test that when value is already set in db.config.php, it is not overridden
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->time_zone = '+0200';

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->time_zone, $actual_db_info->time_zone);

        // Make sure time_zone is available in Globals
        $this->assertEquals($context->getGlobals('_time_zone'), $actual_db_info->time_zone);
    }

    /**
     * Test app configuration - Qmail compatibility
     */
    public function testLoadDbInfo_QmailCompatibility()
    {
        // Test that the default value for this is date('0')
        $db_info = new stdClass();
        $db_info->master_db = array('something');

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);
        $expected_db_info->qmail_compatibility = 'N';

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->qmail_compatibility, $actual_db_info->qmail_compatibility);

        // Test that when value is already set in db.config.php, it is not overridden
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->qmail_compatibility = 'Y';

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->qmail_compatibility, $actual_db_info->qmail_compatibility);

        // Make sure time_zone is available in Globals
        $this->assertEquals($context->getGlobals('_qmail_compatibility'), $actual_db_info->qmail_compatibility);
    }

    /**
     * Test app configuration - use db session
     */
    public function testLoadDbInfo_UseDbSession()
    {
        // Test that the default value for this is 'N'
        $db_info = new stdClass();
        $db_info->master_db = array('something');

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);
        $expected_db_info->use_db_session = 'N';

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->use_db_session, $actual_db_info->use_db_session);

        // Test that when value is already set in db.config.php, it is not overridden
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->use_db_session = 'Y';

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->use_db_session, $actual_db_info->use_db_session);
    }

    /**
     * Test app configuration - use SSL
     *
     * The available values for this are: none, optional and always
     * (look for 'ssl_options' in project files)
     */
    public function testLoadDbInfo_UseSSL()
    {
        // Test that the default value for this is date('0')
        $db_info = new stdClass();
        $db_info->master_db = array('something');

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);
        $expected_db_info->use_ssl = 'none';

        $context->initializeAppSettingsAndCurrentSiteInfo();

        $this->assertEquals($expected_db_info->use_ssl, $context->getSslStatus());

        // Test that when value is already set in db.config.php, it is not overridden
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->use_ssl = 'always';

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();

        $this->assertEquals($expected_db_info->use_ssl, $context->getSslStatus());

        // Make sure time_zone is available in Context
        $this->assertEquals($context->get('_use_ssl'), $context->getSslStatus());
    }

    /**
     * Test app configuration - HTTP and HTTPS port
     */
    public function testLoadDbInfo_HTTPS_Port()
    {
        // Test that the default value is to skip these attributes
        $db_info = new stdClass();
        $db_info->master_db = array('something');

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals(null, $actual_db_info->http_port);
        $this->assertEquals(null, $actual_db_info->https_port);

        // Test that when value is already set in db.config.php, it is not overridden
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->http_port = '80';
        $db_info->https_port = '25';

        $context = $this->getContextMockForDbInfoLoading($db_info);

        $expected_db_info = clone($db_info);

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->http_port, $actual_db_info->http_port);
        $this->assertEquals($expected_db_info->https_port, $actual_db_info->https_port);
        $this->assertEquals($actual_db_info->http_port, $context->get('_http_port'));
        $this->assertEquals($actual_db_info->https_port, $context->get('_https_port'));
    }


    /**
     * Test app configuration - missing master_db
     *
     * This is used for legacy apps - that had the db connection string
     * directly as attributes to $db_info, instead of as two arrays
     * (before XE 1.5)
     */
    public function testLoadDbInfo_MasterDbMissing()
    {
        // Test that the default value for this is date('0')
        $db_info = new stdClass();
        $db_info->db_type = 'mysql';
        $db_info->db_port = '3306';
        $db_info->db_hostname = 'localhost';
        $db_info->db_userid = 'root';
        $db_info->db_password = 'password';
        $db_info->db_database = 'globalcms';
        $db_info->db_table_prefix = 'xe_';
        $db_info->default_url = null;
        $db_info->use_prepared_statements = null;
        $db_info->use_db_session = null;
        $db_info->http_port = null;
        $db_info->https_port = null;
        $db_info->time_zone = null;
        $db_info->qmail_compatibility = null;
        $db_info->use_ssl = null;
        $db_info->use_rewrite = null;

        $context = $this->getMock('ContextInstance'
            , array('loadDbInfoFromConfigFile', 'isInstalled', 'getInstallController', 'getSiteModuleInfo'));

        $context
            ->expects($this->any())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context
            ->expects($this->any())
            ->method('loadDbInfoFromConfigFile')
            ->will($this->returnValue($db_info));

        $site_module_info = new stdClass();
        $site_module_info->site_srl = null;
        $site_module_info->domain = null;
        $site_module_info->default_language = null;
        $context
            ->expects($this->any())
            ->method('getSiteModuleInfo')
            ->will($this->returnValue($site_module_info));

        $installController = $this->getMock('installController', array('makeConfigFile'));
        $installController
            ->expects($this->once())
            ->method('makeConfigFile');

        $context
            ->expects($this->once())
            ->method('getInstallController')
            ->will($this->returnValue($installController));

        $context->initializeDatabaseSettings();

        $expected_db_info = new stdClass();
        $expected_db_info->master_db = array('db_type' => 'mysql','db_port' => '3306','db_hostname' => 'localhost','db_userid' => 'root','db_password' => 'password','db_database' => 'globalcms','db_table_prefix' => 'xe_');
        $expected_db_info->slave_db = array(array('db_type' => 'mysql','db_port' => '3306','db_hostname' => 'localhost','db_userid' => 'root','db_password' => 'password','db_database' => 'globalcms','db_table_prefix' => 'xe_'));

        $context->initializeAppSettingsAndCurrentSiteInfo();
        $actual_db_info = $context->getDbInfo();

        $this->assertEquals($expected_db_info->master_db, $actual_db_info->master_db);
        $this->assertEquals($expected_db_info->slave_db, $actual_db_info->slave_db);
    }

    /**
     * Check that current site info is properly set
     * And that if we are on a virtual site, the vid is also initialized
     */
    public function testInitializeCurrentSiteInfo_SetSiteModuleInfoInContext()
    {
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->default_url = 'http://www.karybu.org';

        $site_module_info = new stdClass();
        $site_module_info->site_srl = 0;
        $site_module_info->domain = 'http://www.karybu.org';
        $site_module_info->default_language = null;

        $context = $this->getContextMockForDbInfoLoading($db_info, $site_module_info);
        $context->initializeAppSettingsAndCurrentSiteInfo();

        $expected_module_info = clone($site_module_info);
        $actual_site_module_info = $context->get('site_module_info');

        $this->assertEquals($expected_module_info, $actual_site_module_info);
    }

    /**
     * Check that current site info is properly set
     * And that if we are on a virtual site, the vid is also initialized
     */
    public function testInitializeCurrentSiteInfo_SetDefaultLanguage()
    {
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->default_url = 'http://www.karybu.org';

        $site_module_info = new stdClass();
        $site_module_info->site_srl = 0;
        $site_module_info->domain = 'http://www.karybu.org';
        $site_module_info->default_language = null;

        // Test that default language is 'en', when nothing else is set
        $context = $this->getContextMockForDbInfoLoading($db_info, $site_module_info);
        $context->initializeAppSettingsAndCurrentSiteInfo();

        $db_info = $context->getDbinfo();

        $this->assertEquals('en', $db_info->lang_type);

        // Test that default language persists when manually set
        $site_module_info->default_language = 'ro';

        $context = $this->getContextMockForDbInfoLoading($db_info, $site_module_info);
        $context->initializeAppSettingsAndCurrentSiteInfo();

        $db_info = $context->getDbinfo();

        $this->assertEquals('ro', $db_info->lang_type);
    }

    /**
     * Check that current site info is properly set
     * And that if we are on a virtual site, the vid is also initialized
     */
    public function testInitializeCurrentSiteInfo_DifferentDefaultUrl()
    {
        // 1. Arrange
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->default_url = 'http://demo.karybu.org';

        $site_module_info = new stdClass();
        $site_module_info->site_srl = 0;
        $site_module_info->domain = 'http://www.karybu.org';
        $site_module_info->default_language = null;

        $context = $this->getContextMockForDbInfoLoading($db_info, $site_module_info);

        // 2. Act
        $context->initializeAppSettingsAndCurrentSiteInfo();

        // 3. Assert
        // Make sure the default_url defined in db.config.php has precedence
        $actual_site_module_info = $context->get('site_module_info');
        $this->assertEquals('http://demo.karybu.org', $actual_site_module_info->domain);
    }

    /**
     * Check that current site info is properly set
     * And that if we are on a virtual site, the vid is also initialized
     */
    public function testInitializeCurrentSiteInfo_VirtualSite()
    {
        // 1. Arrange
        $db_info = new stdClass();
        $db_info->master_db = array('something');
        $db_info->default_url = 'http://demo.karybu.org';
        $db_info->default_url = null;
        $db_info->use_prepared_statements = null;
        $db_info->use_db_session = null;
        $db_info->http_port = null;
        $db_info->https_port = null;
        $db_info->time_zone = null;
        $db_info->qmail_compatibility = null;
        $db_info->use_ssl = null;
        $db_info->use_rewrite = null;


        $site_module_info = new stdClass();
        $site_module_info->site_srl = 123;
        $site_module_info->domain = 'mysite';
        $site_module_info->default_language = null;

        $context = $this->getMock('ContextInstance', array('loadDbInfoFromConfigFile', 'isInstalled', 'isSiteID', 'getSiteModuleInfo'));
        $context
            ->expects($this->any())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context
            ->expects($this->any())
            ->method('loadDbInfoFromConfigFile')
            ->will($this->returnValue($db_info));
        $context
            ->expects($this->any())
            ->method('getSiteModuleInfo')
            ->will($this->returnValue($site_module_info));
        $context
            ->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $context->initializeDatabaseSettings();

        // 2. Act
        $context->initializeAppSettingsAndCurrentSiteInfo();

        // 3. Assert
        $vid = $context->get('vid');
        $this->assertEquals($site_module_info->domain, $vid);
    }

    /**
     * Tests retrieving enabled languages for a site when
     * the needed file exists and is correct
     */
    public function testLoadEnabledLanguages_LangFileExists()
    {
        $file_handler = $this->getMock('FileHandlerInstance', array('hasContent', 'moveFile', 'readFile', 'writeFile', 'readFileAsArray'));
        $file_handler->expects($this->any())
            ->method('hasContent')
            ->will($this->returnValue(true));
        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array('en,English', 'ro,Romana')));

        $context = new ContextInstance($file_handler);
        $enabled_languages = $context->loadLangSelected();

        $expected = array("en" => "English", "ro" => "Romana");
        $this->assertEquals($expected, $enabled_languages);

    }

    /**
     * Tests retrieving enabled languages for a site when
     * the needed is in an old format (inside ./files/cache instead of ./files/config/..)
     */
    public function testLoadEnabledLanguages_OldFile()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('hasContent', 'moveFile', 'readFile', 'writeFile', 'readFileAsArray'));

        // First time it checks for file contents, it will see it's empty
        // Second time it will be true, since the file was moved from the old location
        $file_handler->expects($this->any())
            ->method('hasContent')
            ->will($this->returnCallback(function() {
                    static $visits = 0;
                    if($visits == 0) {
                        $visits++;
                        return false;
                    }
                    return true;
                }));

        // We make sure the 'move' method was called once
        $file_handler->expects($this->once())
            ->method('moveFile');

        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array('en,English', 'ro,Romana')));

        // 2. Act
        $context = new ContextInstance($file_handler);
        $enabled_languages = $context->loadLangSelected();

        // 3. Assert
        $expected = array("en" => "English", "ro" => "Romana");
        $this->assertEquals($expected, $enabled_languages);
    }

    /**
     * Tests retrieving enabled languages for a site when
     * the needed is in an old format (inside ./files/cache instead of ./files/config/..)
     */
    public function testLoadEnabledLanguages_LangFileDoesNotExist()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('hasContent', 'moveFile', 'readFile', 'writeFile', 'readFileAsArray'));
        $context = $this->getMock('ContextInstance'
            , array('loadLangSupported')
            , array($file_handler));

        // First time it checks for file contents, it will see it's empty
        // Second time it will also be false, since the file was not found even in the old location
        $file_handler->expects($this->exactly(2))
            ->method('hasContent')
            ->will($this->returnValue(false));

        // Will try to move the file, so we make sure the 'move' method was called once
        $file_handler->expects($this->once())
            ->method('moveFile');

        // Since the file was still not found, it will return all available languages as enabled
        $available_languages = array('en' => 'English', 'ro' => 'Romana', 'fr' => 'Francais');
        $context->expects($this->once())
            ->method('loadLangSupported')
            ->will($this->returnValue($available_languages));

        // 2. Act
        $enabled_languages = $context->loadLangSelected();

        // 3. Assert
        $this->assertEquals($available_languages, $enabled_languages);
    }

    /**
     * If the current language is not set, no language file should be loaded
     */
    public function testLoadLang_WhenCurrentLanguageIsNotSet()
    {
        // 1. Arrange
        $context = new ContextInstance();

        // 2. Act
        $context->loadLang('/path/to/my_module');

        // 3. Assert
        $this->assertEquals(0, count($context->loaded_lang_files));
    }

    /**
     * Test that loading works when languages are in the new XML format
     */
    public function testLoadLang_WhenLanguageFileIsXmlFormat()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getXmlLangParser', 'is_readable', 'includeLanguageFile'));

        $xml_lang_parser = $this->getMock('XmlLangParser', array('compile'), array('filename', 'lang'));
        $xml_lang_parser->expects($this->once())
            ->method('compile')
            ->will($this->returnValue("compiled.php"));

        $context->expects($this->once())
            ->method('getXmlLangParser')
            ->will($this->returnValue($xml_lang_parser));
        $context->expects($this->any())
            ->method('is_readable')
            ->will($this->returnValue(true));

        $context->lang_type = 'en';

        // 2. Act
        $context->loadLang('/path/to/my_module');

        // 3. Assert
        $this->assertEquals(1, count($context->loaded_lang_files));
        $this->assertEquals("compiled.php", $context->loaded_lang_files[0]);
    }

    /**
     * Test that loading works when languages are in the old PHP format
     */
    public function testLoadLang_WhenLanguageFileIsPhpFormat()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getXmlLangParser', 'is_readable', 'includeLanguageFile'));

        $xml_lang_parser = $this->getMock('XmlLangParser', array('compile'), array('filename', 'lang'));
        $xml_lang_parser->expects($this->once())
            ->method('compile')
            ->will($this->returnValue(false));

        $context->expects($this->once())
            ->method('getXmlLangParser')
            ->will($this->returnValue($xml_lang_parser));
        $context->expects($this->any())
            ->method('is_readable')
            ->will($this->returnValue(true));

        $context->lang_type = 'en';

        // 2. Act
        $context->loadLang('/path/to/my_module');

        // 3. Assert
        $this->assertEquals(1, count($context->loaded_lang_files));
        $this->assertEquals("/path/to/my_module/en.lang.php", $context->loaded_lang_files[0]);
    }

    /**
     * Test that if a language file was already loaded, it will not be loaded again
     */
    public function testLoadLang_WhenLanguageFileWasAlreadyLoaded()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getXmlLangParser', 'is_readable'));

        $xml_lang_parser = $this->getMock('XmlLangParser', array('compile'), array('filename', 'lang'));
        $xml_lang_parser->expects($this->once())
            ->method('compile')
            ->will($this->returnValue("compiled.php"));

        $context->expects($this->once())
            ->method('getXmlLangParser')
            ->will($this->returnValue($xml_lang_parser));
        $context->expects($this->any())
            ->method('is_readable')
            ->will($this->returnValue(true));

        $context->lang_type = 'en';
        $context->loaded_lang_files = array("compiled.php");

        // 2. Act
        $context->loadLang('/path/to/my_module');

        // 3. Assert
        $this->assertEquals(1, count($context->loaded_lang_files));
        $this->assertEquals("compiled.php", $context->loaded_lang_files[0]);
    }

    /**
     * When using XML files for lang, Karybu compiles them to .php to make loading faster
     * However, it is possible for PHP to not have the right to write to disk, so then
     * the compiled result is not written to disk, but just used as is
     */
    public function testLoadLang_WhenThereAreNoPermissionToWriteCompiledXmlFile()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getXmlLangParser', 'is_readable', 'evaluateLanguageFileContent'));

        $xml_lang_parser = $this->getMock('XmlLangParser', array('compile', 'getCompileContent'), array('filename', 'lang'));
        $xml_lang_parser->expects($this->any())
            ->method('compile')
            ->will($this->returnValue("compiled.php"));
        $xml_lang_parser->expects($this->any())
            ->method('getCompileContent')
            ->will($this->returnValue("compiled_content"));

        $context->expects($this->any())
            ->method('getXmlLangParser')
            ->will($this->returnValue($xml_lang_parser));
        $context->expects($this->any())
            ->method('is_readable')
            ->will($this->returnValue(false));

        $context->lang_type = 'en';

        // 2. Act
        $context->loadLang('/path/to/my_module');

        // 3. Assert
        $this->assertEquals(1, count($context->loaded_lang_files));
        $this->assertEquals("eval:///path/to/my_module", $context->loaded_lang_files[0]);

        // Also test that if called again, it won't load the content again
        $context->loadLang('/path/to/my_module');
        $this->assertEquals(1, count($context->loaded_lang_files));
        $this->assertEquals("eval:///path/to/my_module", $context->loaded_lang_files[0]);

    }

    public function testGetCurrentUrl_Not_GET_Request()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestUri'));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('here_is_some_dummy_request_uri'));

        $context->setRequestMethod('POST');

        // 2. Act
        $current_url = $context->getCurrentUrl();

        // 3. Assert
        $this->assertEquals($context->getRequestUri(), $current_url);
    }

    public function testGetCurrentUrl_GET_WithoutParams()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getUrl'));
        $context->expects($this->any())
            ->method('getUrl')
            ->will($this->returnValue('here_is_some_dummy_url'));
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // 2. Act
        $current_url = $context->getCurrentUrl();

        // 3. Assert
        $this->assertEquals($context->getUrl(), $current_url);
    }


    public function testGetCurrentUrl_GET_WithParams()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestUri'));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('here_is_some_dummy_url'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $context->set('param1', 'value1', true);
        $context->set('param2', 'value2', true);

        // 2. Act
        $current_url = $context->getCurrentUrl();

        // 3. Assert
        $this->assertEquals($context->getRequestUri() . '?param1=value1&param2=value2', $current_url);
    }

    public function testGetCurrentUrl_GET_WithParams_Array()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestUri'));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('here_is_some_dummy_url'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $context->set('param1', 'value1', true);
        $context->set('param2', array('green', 'blue', 'yellow'), true);

        // 2. Act
        $current_url = $context->getCurrentUrl();

        // 3. Assert
        $this->assertEquals($context->getRequestUri() . '?param1=value1&param2[0]=green&param2[1]=blue&param2[2]=yellow', $current_url);
    }

    public function testGetRequestURI_HTTP_Protocol_Missing()
    {
        // 1. Arrange
        $context = new ContextInstance();

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals(null, $url);
    }

    public function testGetRequestURI_DefaultValues()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('http://www.karybu.org/', $url);
    }

    public function testGetRequestURI_DefaultValues_SecondCallShouldNotRecalculateUrl()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // 2-3. Act and assert
        // At first, url should be empty
        $this->assertEmpty($context->url);

        // After first requesting the Request URI, the url should be saved in the 'url' property
        $context->getRequestUri();
        $url_at_first_call = $context->url;
        $this->assertEquals('http://www.karybu.org/', $context->url[0]['default']);

        // After second request, the 'url' property should be unchanged
        // This does not necessarily mean the url wasn't recalculated, but i have no idea how to test this otherwise
        $context->getRequestUri();
        $this->assertEquals($url_at_first_call, $context->url);
    }

    public function testGetRequestURI_EnforceSSL()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $context->set('_use_ssl', 'always');

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('https://www.karybu.org/', $url);
    }

    public function testGetRequestURI_SkipDefaultSSLPort()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $context->set('_use_ssl', 'always');
        $context->set('_https_port', '443');

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('https://www.karybu.org/', $url);
    }

    public function testGetRequestURI_SkipDefaultSSLPort2()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org:443';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTPS'] = 'on';

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('https://www.karybu.org/', $url);
    }

    public function testGetRequestURI_SSLPort()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $context->set('_use_ssl', 'always');
        $context->set('_https_port', '1234');

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('https://www.karybu.org:1234/', $url);
    }

    public function testGetRequestURI_SkipDefaultPort80()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $context->set('_http_port', '80');

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('http://www.karybu.org/', $url);
    }


    public function testGetRequestURI_SkipDefaultPort80_2()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org:80';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('http://www.karybu.org/', $url);
    }

    public function testGetRequestURI_HTTP_Port()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $context->set('_http_port', '1234');

        // 2. Act
        $url = $context->getRequestUri();

        // 3. Assert
        $this->assertEquals('http://www.karybu.org:1234/', $url);
    }

    public function testGetRequestURI_WithDomain()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // 2. Act
        $domain = 'demo.karybu.org';
        $url = $context->getRequestUri(FOLLOW_REQUEST_SSL, $domain);

        // 3. Assert
        $this->assertEquals('http://demo.karybu.org/', $url);
    }

    public function testGetRequestURI_ReleaseSSL()
    {
        // 1. Arrange
        $context = new ContextInstance();

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTPS'] = 'on';

        // 2. Act
        $url = $context->getRequestUri(RELEASE_SSL);

        // 3. Assert
        $this->assertEquals('http://www.karybu.org/', $url);
    }

    public function testGetUrl_Default()
    {
        $context = $this->getMock('ContextInstance', array('getRequestURI'));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';

        $url = $context->getUrl();

        $this->assertEquals('/some_folder/xe/', $url);
    }

    public function testGetUrl_WithGetParametersSetButNoOtherVariables_ShouldReturnSameUrl()
    {
        $context = $this->getMock('ContextInstance', array('getRequestURI'));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';
        $context->set('module', 'admin', true);
        $context->set('act', 'dispDashboard', true);

        $url = $context->getUrl();

        $this->assertEquals('/some_folder/xe/index.php?module=admin&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_WithGetParametersSetAndOtherVariables_ShouldAddVariablesToExistingParams()
    {
        $context = $this->getMock('ContextInstance', array('getRequestURI'));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';
        $context->set('module', 'admin', true);
        $context->set('act', 'dispDashboard', true);

        $url = $context->getUrl(2, array('category_srl', '1234'));

        $this->assertEquals('/some_folder/xe/index.php?module=admin&amp;act=dispDashboard&amp;category_srl=1234', $url);
    }

    public function testGetUrl_EmptyParamsShouldBeIgnored()
    {
        $context = $this->getMock('ContextInstance', array('getRequestURI'));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';

        $url = $context->getUrl(2, array('module', 'admin', 'domain', '', 'act', 'dispDashboard'));

        $this->assertEquals('/some_folder/xe/index.php?module=admin&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_ArrayParameter()
    {
        $context = $this->getMock('ContextInstance', array('getRequestURI'));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';

        $url = $context->getUrl(2, array('module', 'admin', 'color', array('red', 'blue', 'green'), 'act', 'dispDashboard'));

        $this->assertEquals('/some_folder/xe/index.php?module=admin&amp;color[0]=red&amp;color[1]=blue&amp;color[2]=green&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_Default_WithDomain()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));

        $site_module_info = new stdClass();
        $site_module_info->domain = 'shop';
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';

        // 1. Test when allow rewrite is enabled
        $url = $context->getUrl();
        $this->assertEquals('/some_folder/xe/index.php?vid=shop', $url);

        // 2. Test when allow rewrite is disabled
        $context->allow_rewrite = true;
        $url = $context->getUrl();
        $this->assertEquals('/some_folder/xe/shop', $url);
    }

    public function testGetUrl_MainWebsite_TwoParams()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'act', 'dispDashboard'));

        // 3. Assert
        $this->assertEquals('/index.php?module=admin&amp;act=dispDashboard', $url);
    }


    public function testGetUrl_MainWebsite_ParamsShouldBeUrlEncoded()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(4, array('mid', 'wiki', 'entry', 'Hello World!', 'category_srl', '1234'));

        // 3. Assert
        $this->assertEquals('/index.php?mid=wiki&amp;entry=Hello+World%21&amp;category_srl=1234', $url);
    }

    public function testGetUrl_MainWebsite_TwoParams_NotEncoded()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'act', 'dispDashboard'), null, false);

        // 3. Assert
        $this->assertEquals('/index.php?module=admin&act=dispDashboard', $url);
    }


    public function testGetUrl_MainWebsite_TwoParams_AutoEncoded_WhenParamsNotEncoded()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'entry', 'Hello World! Or should I say?'), null, true, true);

        // 3. Assert
        $this->assertEquals('/index.php?module=admin&amp;entry=Hello World! Or should I say?', $url);
    }

    public function testGetUrl_MainWebsite_TwoParams_AutoEncoded_WhenParamsEncoded()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'entry', 'Hello World! Or should I say &#33;?'), null, true, true);

        // 3. Assert
        $this->assertEquals('/index.php?module=admin&amp;entry=Hello+World%21+Or+should+I+say+%26%2333%3B%3F', $url);
    }


    public function testGetUrl_MainWebsite_VidParamIsIgnored()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(2, array('vid', 'my_blog'));

        // 3. Assert
        $this->assertEquals('/', $url);
    }

    public function testGetUrl_Domain_IgnoresVidIfManuallySpecified()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;
        $site_module_info = new stdClass();
        $site_module_info->domain = 'shop';
        $context->set('site_module_info', $site_module_info);

        // 2. Act
        $url = $context->getUrl(2, array('vid', 'my_blog'));

        // 3. Assert
        $this->assertEquals('/shop', $url);
    }

    public function testGetUrl_Domain_WithParamsAndUrlRewrite_PrettifiesMostCommonParams()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;
        $site_module_info = new stdClass();
        $site_module_info->domain = 'shop';
        $context->set('site_module_info', $site_module_info);

        // 2-3. Act / assert
        $url = $context->getUrl(2, array('mid', 'my_blog'));
        $this->assertEquals('/shop/my_blog', $url);

        $url = $context->getUrl(2, array('mid', 'my_blog', 'entry', 'hello-world'));
        $this->assertEquals('/shop/my_blog/entry/hello-world', $url);

        $url = $context->getUrl(2, array('document_srl', '1234'));
        $this->assertEquals('/shop/1234', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'document_srl', '1234'));
        $this->assertEquals('/shop/forum/1234', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'dispLatestPosts'));
        $this->assertEquals('/index.php?mid=forum&amp;act=dispLatestPosts&amp;vid=shop', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'rss'));
        $this->assertEquals('/shop/forum/rss', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'atom'));
        $this->assertEquals('/shop/forum/atom', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'api'));
        $this->assertEquals('/shop/forum/api', $url);

        $url = $context->getUrl(6, array('act', 'doSomething', 'document_srl', '1234', 'key', 'some-key'));
        $this->assertEquals('/index.php?act=doSomething&amp;document_srl=1234&amp;key=some-key&amp;vid=shop', $url);

        $url = $context->getUrl(6, array('act', 'trackback', 'document_srl', '1234', 'key', 'some-key'));
        $this->assertEquals('/shop/1234/some-key/trackback', $url);

        $url = $context->getUrl(6, array('mid', 'forum', 'act', 'trackback', 'document_srl', '1234', 'key', 'some-key'));
        $this->assertEquals('/shop/forum/1234/some-key/trackback', $url);

    }

    public function testGetUrl_MainWebsite_WithParamsAndUrlRewrite_PrettifiesMostCommonParams()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;

        // 2-3. Act / assert
        $url = $context->getUrl(2, array('mid', 'my_blog'));
        $this->assertEquals('/my_blog', $url);

        $url = $context->getUrl(4, array('mid', 'my_blog', 'entry', 'hello-world'));
        $this->assertEquals('/my_blog/entry/hello-world', $url);

        $url = $context->getUrl(2, array('document_srl', '1234'));
        $this->assertEquals('/1234', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'document_srl', '1234'));
        $this->assertEquals('/forum/1234', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'dispLatestPosts'));
        $this->assertEquals('/index.php?mid=forum&amp;act=dispLatestPosts', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'rss'));
        $this->assertEquals('/forum/rss', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'atom'));
        $this->assertEquals('/forum/atom', $url);

        $url = $context->getUrl(4, array('mid', 'forum', 'act', 'api'));
        $this->assertEquals('/forum/api', $url);

        $url = $context->getUrl(6, array('act', 'doSomething', 'document_srl', '1234', 'key', 'some-key'));
        $this->assertEquals('/index.php?act=doSomething&amp;document_srl=1234&amp;key=some-key', $url);

        $url = $context->getUrl(6, array('act', 'trackback', 'document_srl', '1234', 'key', 'some-key'));
        $this->assertEquals('/1234/some-key/trackback', $url);

        $url = $context->getUrl(6, array('mid', 'forum', 'act', 'trackback', 'document_srl', '1234', 'key', 'some-key'));
        $this->assertEquals('/forum/1234/some-key/trackback', $url);
    }


    public function testGetUrl_WithGetParametersSetAndOtherVariables_StartNewWhenFirstParamsIsEmptyString()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI'));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/some_folder/xe/index.php';
        $context->set('module', 'admin', true);
        $context->set('act', 'dispDashboard', true);

        $url = $context->getUrl(2, array('', 'category_srl', '1234'));

        $this->assertEquals('/some_folder/xe/index.php?category_srl=1234', $url);
    }

    public function testGetUrl_WithDomain_SiteID()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = 'shop';
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $url = $context->getUrl(2, array('module', 'admin'), 'shop');

        $this->assertEquals('/index.php?module=admin&amp;vid=shop', $url);
    }

    public function testGetUrl_WithDomain_Subdomain_DifferentHost()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->once())
            ->method('getRequestURI')
            ->with($this->equalTo(FOLLOW_REQUEST_SSL), $this->equalTo('shop.karybu.org/'))
            ->will($this->returnValue('http://shop.karybu.org/'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(false));
        $site_module_info = new stdClass();
        $site_module_info->domain = 'http://shop.karybu.org';
        $context->set('site_module_info', $site_module_info);

        $_SERVER['HTTP_HOST'] = 'www.karybu.org';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $url = $context->getUrl(2, array('module', 'admin'), 'http://shop.karybu.org');

        $this->assertEquals('http://shop.karybu.org/index.php?module=admin', $url);
    }

    public function testGetUrl_WithDomain_Subdomain_SameAsHost()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(false));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['HTTP_HOST'] = 'shop.karybu.org';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $url = $context->getUrl(2, array('module', 'admin'), 'http://shop.karybu.org/');

        $this->assertEquals('/index.php?module=admin', $url);
    }


    public function testGetUrl_MainWebsite_UseSSL_Always()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        // We expect that getRequestURI will be called with ENFORCE_SLL on and no subdomain
        $context->expects($this->once())
            ->method('getRequestURI')
            ->with($this->equalTo(ENFORCE_SSL), $this->equalTo(null))
            ->will($this->returnValue('https://www.karybu.org/'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;
        $context->set('_use_ssl', 'always');

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'act', 'dispDashboard'));

        // 3. Assert
        $this->assertEquals('https://www.karybu.org/index.php?module=admin&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_MainWebsite_UseSSLFalse_HttpsOn()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'));
        // We expect that getRequestURI will be called with ENFORCE_SLL on and no subdomain
        $context->expects($this->once())
            ->method('getRequestURI')
            ->with($this->equalTo(ENFORCE_SSL), $this->equalTo(null))
            ->will($this->returnValue('https://www.karybu.org/'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTPS'] = 'on';
        $context->allow_rewrite = true;

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'act', 'dispDashboard'));

        // 3. Assert
        $this->assertEquals('https://www.karybu.org/index.php?module=admin&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_MainWebsite_UseSSL_Optional_SSLActionDoesNotExist()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID', 'isExistsSSLAction'));

        $context->expects($this->once())
            ->method('isExistsSSLAction')
            ->with($this->equalTo('dispDashboard'))
            ->will($this->returnValue(false));
        // We expect that getRequestURI will be called with ENFORCE_SLL on and no subdomain
        $context->expects($this->once())
            ->method('getRequestURI')
            ->with($this->equalTo(RELEASE_SSL), $this->equalTo(null))
            ->will($this->returnValue('http://www.karybu.org/'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(false));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;
        $context->set('_use_ssl', 'optional');

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'act', 'dispDashboard'));

        // 3. Assert
        $this->assertEquals('http://www.karybu.org/index.php?module=admin&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_MainWebsite_UseSSL_Optional_SSLActionExists()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID', 'isExistsSSLAction'));

        $context->expects($this->once())
            ->method('isExistsSSLAction')
            ->with($this->equalTo('dispDashboard'))
            ->will($this->returnValue(true));
        // We expect that getRequestURI will be called with ENFORCE_SLL on and no subdomain
        $context->expects($this->once())
            ->method('getRequestURI')
            ->with($this->equalTo(ENFORCE_SSL), $this->equalTo(null))
            ->will($this->returnValue('https://www.karybu.org/'));
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(false));
        $site_module_info = new stdClass();
        $site_module_info->domain = null;
        $context->set('site_module_info', $site_module_info);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $context->allow_rewrite = true;
        $context->set('_use_ssl', 'optional');

        // 2. Act
        $url = $context->getUrl(4, array('module', 'admin', 'act', 'dispDashboard'));

        // 3. Assert
        $this->assertEquals('https://www.karybu.org/index.php?module=admin&amp;act=dispDashboard', $url);
    }

    public function testGetUrl_RouteBased_WithGetParams(){
        $router = $this->getMock('Karybu\Routing\Router', array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            ));
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($this->getRoutes()));

        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID'), array(null, null, null, $router));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;
        $context->allow_rewrite = true;
        $_SERVER['SCRIPT_NAME'] = '/';
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));


        $url = $context->getUrl(4, array("p1", "v1", "p2", "v2"));
        $this->assertEquals('/?p1=v1&amp;p2=v2', $url);

        $url = $context->getUrl(4, array("act", "act_value", "p2", "v2"));
        $this->assertEquals('/admin/act_value?p2=v2', $url);

        $url = $context->getUrl(4, array("act", "act_value", "mid", "mid_value"));
        $this->assertEquals('/admin/act_value/mid_value', $url);

        $url = $context->getUrl(4, array("document_srl", "document_srl_value"));
        $this->assertEquals('/document_srl_value', $url);

        $url = $context->getUrl(4, array("mid", "mid_value"));
        $this->assertEquals('/mid_value', $url);

        $url = $context->getUrl(4, array("mid", "mid_value", "document_srl", "document_srl_value"));
        $this->assertEquals('/mid_value/document_srl_value', $url);

        $url = $context->getUrl(4, array("mid", "mid_value", "vid", "vid_value"), "domain");
        $this->assertEquals('/domain/mid_value', $url);

        $url = $context->getUrl(4, array("mid", "mid_value", "vid", "vid_value", "document_srl", "document_srl_value"), "domain");
        $this->assertEquals('/domain/mid_value/document_srl_value', $url);

        $url = $context->getUrl(4, array("mid", "mid_value", "entry", "entry_value"));
        $this->assertEquals('/mid_value/entry/entry_value', $url);

        $url = $context->getUrl(4, array("mid", "mid_value", "entry", "entry_value"), "domain");
        $this->assertEquals('/domain/mid_value/entry/entry_value', $url);

        $url = $context->getUrl(4, array("type", "type_value", "identifier", "identifier_value"), "domain");
        $this->assertEquals('/domain/type_value/identifier_value', $url);

    }

    public function testGetUrl_RouteBased_ModuleAndAct()
    {
        $router = $this->getMock('Karybu\Routing\Router', array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            ));
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($this->getRoutes()));
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID')
            , array(null, null, null, $router));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;
        $context->allow_rewrite = true;
        $_SERVER['SCRIPT_NAME'] = '/';

        $url = $context->getUrl(4, array("module", "admin", "act", "hello"));
        $this->assertEquals('/admin/hello', $url);
    }

    /**
     * Note: I fixed this bug temporarily (or not) by changing the order of the routes: admin3 is now the last one
     */
    public function testGetUrl_RouteBased_MidVidAndAct()
    {
        $router = $this->getMock('Karybu\Routing\Router', array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            ));
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($this->getRoutes()));
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID')
            , array(null, null, null, $router));
        $_SERVER['SCRIPT_NAME'] = '/';
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $context->allow_rewrite = true;

        $url = $context->getUrl(4, array("mid", "shop", "act", "dispShopToolLogin"), 'magazin');
        $this->assertEquals('/magazin/shop?act=dispShopToolLogin', $url);
    }

    public function testGetUrl_RouteBased_Module_Is_Admin()
    {
        $router = $this->getMock('Karybu\Routing\Router', array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            ));
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($this->getRoutes()));
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID')
            , array(null, null, null, $router));
        $_SERVER['SCRIPT_NAME'] = '/';
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;
        $context->allow_rewrite = true;

        $url = $context->getUrl(4, array("module", "admin"));
        $this->assertEquals('/admin', $url);
    }

    public function testGetUrl_RouteBased_Module_Is_Admin_And_Act()
    {
        $router = $this->getMock(
            'Karybu\Routing\Router',
            array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            )
        );
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($this->getRoutes()));
        $context = $this->getMock(
            'ContextInstance',
            array('getRequestURI', 'isSiteID')
            ,
            array(null, null, null, $router)
        );
        $_SERVER['SCRIPT_NAME'] = '/';
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;
        $context->allow_rewrite = true;

        $url = $context->getUrl(0, array("module", "admin", "act", "dispMenuAdminSiteMap"));
        $this->assertEquals('/admin/dispMenuAdminSiteMap', $url);
    }

    public function testGetUrl_RouteBased_NoRewrite_Module_Is_Admin_And_Act()
    {
        $router = $this->getMock(
            'Karybu\Routing\Router',
            array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            )
        );
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($this->getRoutes()));
        $context = $this->getMock(
            'ContextInstance',
            array('getRequestURI', 'isSiteID')
            ,
            array(null, null, null, $router)
        );
        $_SERVER['SCRIPT_NAME'] = '/';
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;
        $context->allow_rewrite = false;

        $url = $context->getUrl(0, array("module", "admin", "act", "dispMenuAdminSiteMap"),
            null, false);
        $this->assertEquals('/index.php?module=admin&act=dispMenuAdminSiteMap', $url);
    }

    public function test_GetUrl_RouteBased_Fall2NextValidRequirements(){
        $routes = new \Symfony\Component\Routing\RouteCollection();
        $routes->add("mid_act", new \Symfony\Component\Routing\Route('/{mid}/{act}', array(), array("act"=>"[a-zA-Z0-9]+")));
        $routes->add("act_mid", new \Symfony\Component\Routing\Route('/{act}/{mid}'));
        $router = $this->getMock('Karybu\Routing\Router', array('getRouteCollection'),
            array(
                $this->getMock('Symfony\Component\Config\Loader\LoaderInterface'),
                $this->getMock('Symfony\Component\Routing\RequestContext'),
                $this->getMock('Psr\Log\LoggerInterface'),
                false
            ));
        $router->expects($this->any())->method('getRouteCollection')->will($this->returnValue($routes));
        $context = $this->getMock('ContextInstance', array('getRequestURI', 'isSiteID')
            , array(null, null, null, $router));
        $_SERVER['SCRIPT_NAME'] = '/';
        $context->expects($this->any())
            ->method('isSiteID')
            ->will($this->returnValue(true));
        $context->site_module_info = new stdClass();
        $context->site_module_info->domain = null;
        $context->allow_rewrite = true;

        $url = $context->getUrl(4, array("mid", "shop", "act", "dispShopToolLogin_"));
        $this->assertEquals('/dispShopToolLogin_/shop', $url);
    }

    private function getRoutes(){
        $routes = new \Symfony\Component\Routing\RouteCollection();
        $routes->add("homepage", new \Symfony\Component\Routing\Route('/'));
        $routes->add("admin", new \Symfony\Component\Routing\Route('/admin'));
        $routes->add("admin2", new \Symfony\Component\Routing\Route('/admin/{act}'));
        $routes->add("module_admin", new \Symfony\Component\Routing\Route("/{module}", array(), array("module" =>"admin")));
        $routes->add("module_admin_act_whatever", new \Symfony\Component\Routing\Route("/{module}/{act}", array(), array("module" =>"admin")));
        $routes->add("doc", new \Symfony\Component\Routing\Route('/{document_srl}'));
        $routes->add("doc_slash", new \Symfony\Component\Routing\Route('/{document_srl}/'));
        $routes->add("mid", new \Symfony\Component\Routing\Route('/{mid}'));
        $routes->add("mid_slash", new \Symfony\Component\Routing\Route('/admin/{act}'));
        $routes->add("page", new \Symfony\Component\Routing\Route('/show/{mid}'));
        $routes->add("mid_doc", new \Symfony\Component\Routing\Route('/{mid}/{document_srl}'));
        $routes->add("vid_mid", new \Symfony\Component\Routing\Route('/{vid}/{mid}'));
        $routes->add("vid_mid_doc", new \Symfony\Component\Routing\Route('/{vid}/{mid}/{document_srl}'));
        $routes->add("mid_entry", new \Symfony\Component\Routing\Route('/{mid}/entry/{entry}'));
        $routes->add("vid_mid_entry", new \Symfony\Component\Routing\Route('/{vid}/{mid}/entry/{entry}'));
        $routes->add("shop_product", new \Symfony\Component\Routing\Route('/{vid}/{type}/{identifier}'));
        $routes->add("admin3", new \Symfony\Component\Routing\Route('/admin/{act}/{mid}'));
        return $routes;
    }

    public function testCheckSSO_WhenSSODisabled()
    {
        // 1. Arrange
        $context = new ContextInstance();
        $context->db_info = new stdClass();
        $context->db_info->use_sso = null;
        $context->db_info->default_url = null;

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result);
    }


    public function testCheckSSO_WhenVisitorIsCrawler()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(true));
        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = null;

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result);
    }

    public function testCheckSSO_WhenRequestMethodIsNotGET()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = null;

        $context->request_method = 'POST';

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result);
    }

    public function testCheckSSO_WhenNotInstalled()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->once())
            ->method('isInstalled')
            ->will($this->returnValue(false));
        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = null;

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result);
    }

    public function testCheckSSO_WhenShowingRSSorATOM()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled'));
        $context->expects($this->any())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->any())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = null;

        $context->set('act', 'rss', true);
        $result = $context->checkSSO();
        $this->assertTrue($result);

        $context->set('act', 'atom', true);
        $result = $context->checkSSO();
        $this->assertTrue($result);
    }

    public function testCheckSSO_WhenDefaultUrlNotSet()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->once())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = null;

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result);
    }

    /**
     * STEP 1
     * This represents the first step in the Single Sign On process
     * The user browses a virtual site with SSO enabled (shop.karybu.org)
     * and is redirected to the main site for the session id to be retrieved (www.karybu.org)
     *
     * When the redirect is made, an extra parameter 'default_url' is passed, to know
     * where to bring the user back after login. This default_url is the base64 encoded
     * original url (shop.karybu.org/hello/world) - including GET parameters
     */
    public function testCheckSSO_RequestSSO()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled', 'getRequestUri', 'setCookie', 'setRedirectResponseTo'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->once())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('http://shop.karybu.org'));
        $context->expects($this->once())
            ->method('setCookie')
            ->with(
            $this->equalTo('sso'),
            $this->equalTo(md5('http://shop.karybu.org')) ,
            $this->equalTo(0),
            $this->equalTo('/')
        );

        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = 'http://www.karybu.org';

        // 2. Act
        /** @var $result \Symfony\Component\HttpFoundation\RedirectResponse */
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result instanceof \Symfony\Component\HttpFoundation\RedirectResponse);
        $expected_url = 'http://www.karybu.org/?default_url=' . base64_encode($context->getRequestUrl());
        $this->assertEquals($expected_url, $result->getTargetUrl());
    }

    /**
     * STEP 2
     * The user just got on the main site (www.karybu.org) from a subdomain
     * (shop.karybu.org). His session id from the main site will be sent back to
     * the subdomain in the SSOID parameter (by doing another redirect)
     */
    public function testCheckSSO_RetrieveSessionId()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled','getRequestUri', 'getSessionId', 'setRedirectResponseTo'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->once())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('http://www.karybu.org/'));
        $context->expects($this->once())
            ->method('getSessionId')
            ->will($this->returnValue('here-is-my-session-id'));

        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = 'http://www.karybu.org';
        $context->set('default_url', base64_encode('http://shop.karybu.org/'));

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result instanceof \Symfony\Component\HttpFoundation\RedirectResponse);
        $expected_url = 'http://shop.karybu.org/?SSOID=here-is-my-session-id';
        $this->assertEquals($expected_url, $result->getTargetUrl());
    }

    /**
     * STEP 2a
     * The user is still on the subsite
     */
    public function testCheckSSO_SkipSSO()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled','getRequestUri', 'getGlobalCookie'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->once())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('http://shop.karybu.org/'));
        $context->expects($this->once())
            ->method('getGlobalCookie')
            ->will($this->returnValue(md5('http://shop.karybu.org/')));

        $context->db_info = new stdClass();
        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = 'http://www.karybu.org';

        // 2. Act
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result);
    }

    /**
     * STEP 3
     * User is back on the url he originally asked for (shop.karybu.org)
     * only now he knows the session id on the main website,
     * so the session name is updated on this subsite to the same as the main
     * and then he is redirected to the actual original url (shop.karybu.org) without SSOID set
     */
    public function testCheckSSO_UpdateSessionId()
    {
        // 1. Arrange
        $context = $this->getMock('ContextInstance', array('isCrawler', 'isInstalled','getRequestUri', 'getSessionName','setCookie','setRedirectResponseTo'));
        $context->expects($this->once())
            ->method('isCrawler')
            ->will($this->returnValue(false));
        $context->expects($this->once())
            ->method('isInstalled')
            ->will($this->returnValue(true));
        $context->expects($this->any())
            ->method('getRequestUri')
            ->will($this->returnValue('http://shop.karybu.org/'));
        $context->expects($this->any())
            ->method('getSessionName')
            ->will($this->returnValue('PHPSESSID'));
        $context->expects($this->once())
            ->method('setCookie')
            ->with(
            $this->equalTo($context->getSessionName()),
            $this->equalTo('here-is-my-session-id')
        );

        $context->db_info = new stdClass();
        $context->db_info->use_sso = 'Y';
        $context->db_info->default_url = 'http://www.karybu.org';
        $context->set('SSOID', 'here-is-my-session-id', true);

        // 2. Act
        /** @var $result \Symfony\Component\HttpFoundation\RedirectResponse */
        $result = $context->checkSSO();

        // 3. Assert
        $this->assertTrue($result instanceof \Symfony\Component\HttpFoundation\RedirectResponse);
        $url = 'http://shop.karybu.org/';
        $this->assertEquals($url, $result->getTargetUrl());
    }

    public function testLoadJavascriptPlugin_JsFile()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array(
                    'filebox.js'
                )));
        $context = $this->getMock('ContextInstance', array('loadFile', 'pluginConfigFileExistsAndIsReadable'), array($file_handler));
        $context->expects($this->once())
            ->method('loadFile')
            ->with(array('./common/js/plugins/filebox/filebox.js', 'body', '', 0), true);
        $context->expects($this->once())
            ->method('pluginConfigFileExistsAndIsReadable')
            ->will($this->returnValue(true));

        $context->loadJavascriptPlugin('filebox');

        $this->assertTrue($context->loaded_javascript_plugins['filebox']);
    }

    public function testLoadJavascriptPlugin_CssFile()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array(
                    'filebox.css'
                )));
        $context = $this->getMock('ContextInstance', array('loadFile', 'pluginConfigFileExistsAndIsReadable'), array($file_handler));
        $context->expects($this->once())
            ->method('loadFile')
            ->with(array('./common/js/plugins/filebox/filebox.css', 'all', '', 0), true);
        $context->expects($this->once())
            ->method('pluginConfigFileExistsAndIsReadable')
            ->will($this->returnValue(true));

        $context->loadJavascriptPlugin('filebox');

        $this->assertTrue($context->loaded_javascript_plugins['filebox']);
    }


    public function testLoadJavascriptPlugin_SkipsEmptyRows_SkipsRelativePath()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array(
                    './filebox.css',
                    '   '
                )));
        $context = $this->getMock('ContextInstance', array('loadFile', 'pluginConfigFileExistsAndIsReadable'), array($file_handler));
        $context->expects($this->once())
            ->method('loadFile')
            ->with(array('./common/js/plugins/filebox/filebox.css', 'all', '', 0), true);
        $context->expects($this->once())
            ->method('pluginConfigFileExistsAndIsReadable')
            ->will($this->returnValue(true));

        $context->loadJavascriptPlugin('filebox');

        $this->assertTrue($context->loaded_javascript_plugins['filebox']);
    }

    public function testLoadJavascriptPlugin_JsFile_ShouldNotBeLoadedTwice()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array(
                    'filebox.js'
                )));
        $context = $this->getMock('ContextInstance', array('loadFile', 'pluginConfigFileExistsAndIsReadable'), array($file_handler));
        $context->expects($this->once())
            ->method('loadFile')
            ->with(array('./common/js/plugins/filebox/filebox.js', 'body', '', 0), true);
        $context->expects($this->once())
            ->method('pluginConfigFileExistsAndIsReadable')
            ->will($this->returnValue(true));

        $context->loadJavascriptPlugin('filebox');
        $this->assertTrue($context->loaded_javascript_plugins['filebox']);
        $context->loadJavascriptPlugin('filebox'); // Will fail since "loadFile" method of mock should only called once
    }

    public function testLoadJavascriptPlugin_JsFile_IsntLoadedIfConfigFileUnavailable()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->never())
            ->method('readFileAsArray');

        $context = $this->getMock('ContextInstance', array('pluginConfigFileExistsAndIsReadable'), array($file_handler));
        $context->expects($this->once())
            ->method('pluginConfigFileExistsAndIsReadable')
            ->will($this->returnValue(false));

        // 2. Act
        $context->loadJavascriptPlugin('filebox');

        // 3. Assert
        $this->assertTrue($context->loaded_javascript_plugins['filebox']);
    }

    public function testLoadJavascriptPlugin_JsFile_WithLanguages()
    {
        // 1. Arrange
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->once())
            ->method('readFileAsArray')
            ->will($this->returnValue(array(
                    'filebox.js'
                )));
        $context = $this->getMock('ContextInstance', array('loadFile', 'pluginConfigFileExistsAndIsReadable', 'pluginUsesLocalization', 'loadLang'), array($file_handler));
        $context->expects($this->once())
            ->method('loadFile')
            ->with(array('./common/js/plugins/filebox/filebox.js', 'body', '', 0), true);
        $context->expects($this->once())
            ->method('pluginConfigFileExistsAndIsReadable')
            ->will($this->returnValue(true));
        $context->expects($this->once())
            ->method('pluginUsesLocalization')
            ->will($this->returnValue(true));
        $context->expects($this->once())
            ->method('loadLang')
            ->with('./common/js/plugins/filebox/lang');

        $context->loadJavascriptPlugin('filebox');

        $this->assertTrue($context->loaded_javascript_plugins['filebox']);
    }

    private function getContextMockForUsingGetBrowserTitle()
    {
        $context = $this->getMock('ContextInstance', array('getModuleController'));
        $module_controller = $this->getMock('moduleController', array('replaceDefinedLangCode'));
        $module_controller->expects($this->atLeastOnce())
            ->method('replaceDefinedLangCode');
        $context->expects($this->atLeastOnce())
            ->method('getModuleController')
            ->will($this->returnValue($module_controller));
        return $context;
    }

    public function testGetBrowserTitle()
    {
        // Arrange
        $context = $this->getContextMockForUsingGetBrowserTitle();

        // Test that default value is empty string
        $this->assertEquals("", $context->getBrowserTitle());

        // Test that it properly gets the default value set
        $context->site_title = 'Hello World';
        $this->assertEquals('Hello World', $context->getBrowserTitle());
    }

    public function testSetBrowserTitle()
    {
        // Arrange
        $context = $this->getContextMockForUsingGetBrowserTitle();

        $context->setBrowserTitle('Hello World');
        $this->assertEquals('Hello World', $context->getBrowserTitle());

        $context->setBrowserTitle('');
        $this->assertEquals('Hello World', $context->getBrowserTitle());
    }

    public function testAddBrowserTitle()
    {
        // Arrange
        $context = $this->getContextMockForUsingGetBrowserTitle();

        $context->addBrowserTitle('Hello');
        $this->assertEquals('Hello', $context->getBrowserTitle());

        $context->addBrowserTitle('Joe');
        $this->assertEquals('Hello - Joe', $context->getBrowserTitle());
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testLoadFile_WithoutCDN()
    {
        $args = array('filename');
        $useCdn = false;
        $cdnPrefix = '';
        $cdnVersion = '';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('loadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('loadFile')
            ->with($args, $useCdn, $cdnPrefix, $cdnVersion);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->loadFile($args, $useCdn, $cdnPrefix, $cdnVersion);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testLoadFile_WithCDN_DefaultValues()
    {
        define('__KARYBU_CDN_PREFIX__', 'http://static.karybu.org/core/');
        define('__KARYBU_CDN_VERSION__', '%__KARYBU_CDN_VERSION__%');

        $args = array('filename');
        $useCdn = true;
        $cdnPrefix = '';
        $cdnVersion = '';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('loadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('loadFile')
            ->with($args, $useCdn, __KARYBU_CDN_PREFIX__, __KARYBU_CDN_VERSION__);
        $context = new ContextInstance(null, $frontend_file_handler);

        $context->loadFile($args, $useCdn, $cdnPrefix, $cdnVersion);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testLoadFile_WithCDN_CustomValues()
    {
        if(!defined('__KARYBU_CDN_PREFIX__'))
            define('__KARYBU_CDN_PREFIX__', 'http://static.karybu.org/core/');
        if(!defined('__KARYBU_CDN_VERSION__'))
            define('__KARYBU_CDN_VERSION__', '%__KARYBU_CDN_VERSION__%');

        $args = array('filename');
        $useCdn = 'Y';
        $cdnPrefix = '//ajax.googleapis.com';
        $cdnVersion = '1.6';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('loadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('loadFile')
            ->with($args, $useCdn, $cdnPrefix, $cdnVersion);
        $context = new ContextInstance(null, $frontend_file_handler);

        $context->loadFile($args, $useCdn, $cdnPrefix, $cdnVersion);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testUnloadFile()
    {
        $file = 'somefile';
        $targetIe = '123';
        $media = 'some';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('unloadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('unloadFile')
            ->with($file, $targetIe, $media);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->unloadFile($file, $targetIe, $media);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testUnloadAllFiles()
    {
        $type = 'some';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('unloadAllFiles'));
        $frontend_file_handler->expects($this->once())
            ->method('unloadAllFiles')
            ->with($type);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->unloadAllFiles($type);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testUnloadJsFile()
    {
        $file = 'filename.js';
        $optimized = true;
        $targetie = '123';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('unloadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('unloadFile')
            ->with($file, $targetie);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->unloadJsFile($file, $optimized, $targetie);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testUnloadAllJsFiles()
    {
        $type = 'js';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('unloadAllFiles'));
        $frontend_file_handler->expects($this->once())
            ->method('unloadAllFiles')
            ->with($type);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->unloadAllJsFiles();
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testGetJsFile()
    {
        $type = 'body';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('getJsFileList'));
        $frontend_file_handler->expects($this->once())
            ->method('getJsFileList')
            ->with($type);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->getJsFile($type);
    }


    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testUnloadCssFile()
    {
        $file = 'filename.css';
        $optimized = false;
        $media = 'some';
        $targetie = '123';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('unloadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('unloadFile')
            ->with($file, $targetie, $media);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->unloadCssFile($file, $optimized, $media, $targetie);
    }


    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testUnloadAllCssFiles()
    {
        $type = 'css';

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('unloadAllFiles'));
        $frontend_file_handler->expects($this->once())
            ->method('unloadAllFiles')
            ->with($type);
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->unloadAllCssFiles();
    }


    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testGetCssFile()
    {
        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('getCssFileList'));
        $frontend_file_handler->expects($this->once())
            ->method('getCssFileList');
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->getCSSFile();
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testAddJsFile_Basic()
    {
        $file = 'somefile.js';
        $optimized = false;
        $targetie = '123';
        $index=0;
        $type='body';
        $isRuleset = false;
        $autoPath = null;

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('loadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('loadFile')
            ->with($this->equalTo(array($file, $type, $targetie, $index)));

        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->addJsFile($file, $optimized, $targetie, $index, $type, $isRuleset, $autoPath);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testAddJsFile_Ruleset()
    {
        $file = 'somefile.js';
        $optimized = false;
        $targetie = '123';
        $index=0;
        $type='body';
        $isRuleset = true;
        $autoPath = null;

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('loadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('loadFile')
            ->with($this->equalTo(array('ruleset_cache_file.js', $type, $targetie, $index)));
        $validator = $this->getMock('Validator', array('setCacheDir', 'setRulesetPath', 'getJsPath'));
        $validator->expects($this->once())
            ->method('setRulesetPath')
            ->with($file);
        $validator->expects($this->once())
            ->method('setCacheDir');
        $validator->expects($this->once())
            ->method('getJsPath')
            ->will($this->returnValue("ruleset_cache_file.js"));

        $context = new ContextInstance(null, $frontend_file_handler, $validator);

        // 2. Act
        $context->addJsFile($file, $optimized, $targetie, $index, $type, $isRuleset, $autoPath);
    }

    /**
     * Make sure Context delegates the call to FrontendFileHandler
     */
    public function testAddCssFile()
    {
        $file = 'style.css';
        $optimized=true;
        $media='some';
        $targetie='123';
        $index=7;

        $frontend_file_handler = $this->getMock('FrontendFileHandler', array('loadFile'));
        $frontend_file_handler->expects($this->once())
            ->method('loadFile')
            ->with(array($file, $media, $targetie, $index));
        $context = new ContextInstance(null, $frontend_file_handler);

        // 2. Act
        $context->addCSSFile($file, $optimized, $media, $targetie, $index);
    }

    public function testGetCurrentLanguage_BasicBehaviour()
    {
        $context = new ContextInstance();

        $lang = $context->getCurrentLanguage(array('en' => 'English', 'ro' => 'Romana'), 'ro');

        $this->assertEquals('ro', $lang);
    }

    public function testGetCurrentLanguage_WhenLanguageNotEnabled()
    {
        $context = new ContextInstance();

        $lang = $context->getCurrentLanguage(array('en' => 'English'), 'ro');

        $this->assertEquals('en', $lang);
    }

    public function testGetCurrentLanguage_DefaultLanguageNotSet()
    {
        $context = new ContextInstance();

        $lang = $context->getCurrentLanguage(array('en' => 'English'), '');

        $this->assertEquals('en', $lang);
    }

    public function testGetCurrentLanguage_LanguageGivenInQueryString()
    {
        $context = $this->getMock('ContextInstance', array('setCookie', 'getGlobalCookie'));
        $context->expects($this->once())
            ->method('setCookie');
        $context->set('l', 'ro', true);

        $lang = $context->getCurrentLanguage(array('en' => 'English', 'ro' => 'Romana'), 'en');

        $this->assertEquals('ro', $lang);
    }

    public function testGetCurrentLanguage_LanguageTakenFromCookie()
    {
        $context = $this->getMock('ContextInstance', array('getGlobalCookie'));
        $context->expects($this->any())
            ->method('getGlobalCookie')
            ->will($this->returnValue('ro'));

        $lang = $context->getCurrentLanguage(array('en' => 'English', 'ro' => 'Romana'), 'en');

        $this->assertEquals('ro', $lang);
    }

    public function testSetAuthenticationInfoInContextAndSession_NotInstalled()
    {
        $context = $this->getMock('ContextInstance', array('isInstalled'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(false));

        $context->setAuthenticationInfoInContextAndSession();

        $this->assertEquals(null, $context->get('is_logged'));
        $this->assertEquals(null, $context->get('logged_info'));
    }

    public function testSetAuthenticationInfoInContextAndSession_UserNotLoggedIn()
    {
        $context = $this->getMock('ContextInstance', array('isInstalled', 'getMemberModel', 'getMemberController'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(true));
        $memberModel = $this->getMock('memberModel', array('isLogged', 'getLoggedInfo'));
        $memberModel->expects($this->any())->method('isLogged')->will($this->returnValue(false));
        $memberModel->expects($this->any())->method('getLoggedInfo')->will($this->returnValue("logged-info-object-here"));

        $memberController = $this->getMock('memberController');
        $context->expects($this->any())->method('getMemberModel')->will($this->returnValue($memberModel));
        $context->expects($this->any())->method('getMemberController')->will($this->returnValue($memberController));

        $context->setAuthenticationInfoInContextAndSession();

        $this->assertEquals(false, $context->get('is_logged'));
        $this->assertEquals("logged-info-object-here", $context->get('logged_info'));
    }

    public function testSetAuthenticationInfoInContextAndSession_UserNotLoggedIn_AutoSignInEnabled()
    {
        $context = $this->getMock('ContextInstance', array('isInstalled', 'getGlobalCookie',  'getMemberModel', 'getMemberController'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(true));
        $context->expects($this->any())->method('getGlobalCookie')->with($this->equalTo('xeak'))->will($this->returnValue(true));

        $memberModel = $this->getMock('memberModel', array('isLogged', 'getLoggedInfo'));
        $memberModel->expects($this->any())->method('isLogged')->will($this->returnValue(false));

        $memberController = $this->getMock('memberController', array('doAutologin'));
        $memberController->expects($this->once())->method('doAutologin');

        $context->expects($this->any())->method('getMemberModel')->will($this->returnValue($memberModel));
        $context->expects($this->any())->method('getMemberController')->will($this->returnValue($memberController));

        $context->setAuthenticationInfoInContextAndSession();
    }

    public function testSetAuthenticationInfoInContextAndSession_UserLoggedIn()
    {
        $context = $this->getMock('ContextInstance', array('isInstalled', 'getMemberModel', 'getMemberController'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(true));
        $memberModel = $this->getMock('memberModel', array('isLogged', 'getLoggedInfo'));
        $memberModel->expects($this->any())->method('isLogged')->will($this->returnValue(true));
        $memberModel->expects($this->any())->method('getLoggedInfo')->will($this->returnValue("logged-info-object-here"));

        $memberController = $this->getMock('memberController', array('setSessionInfo'));
        $memberController->expects($this->once())->method('setSessionInfo');

        $context->expects($this->any())->method('getMemberModel')->will($this->returnValue($memberModel));
        $context->expects($this->any())->method('getMemberController')->will($this->returnValue($memberController));

        $context->setAuthenticationInfoInContextAndSession();

        $this->assertEquals(true, $context->get('is_logged'));
        $this->assertEquals("logged-info-object-here", $context->get('logged_info'));
    }

    public function testStartSession_AppNotInstalledYet()
    {
        $context = $this->getMock('ContextInstance', array('startPHPSession', 'isInstalled'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(false));

        $context->expects($this->never())->method('setSessionSaveHandler');
        $context->expects($this->once())->method('startPHPSession');

        $context->startSession();
    }


    public function testStartSession_SessionDisabled()
    {
        $context = $this->getMock('ContextInstance', array('startPHPSession', 'isInstalled'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(true));
        $context->db_info = new stdClass();
        $context->db_info->use_db_session = null;

        $context->expects($this->never())->method('setSessionSaveHandler');
        $context->expects($this->once())->method('startPHPSession');

        $context->startSession();
    }

    public function testStartSession_SessionEnabled()
    {
        $context = $this->getMock('ContextInstance', array('startPHPSession', 'isInstalled', 'getSessionController', 'getSessionModel', 'setSessionSaveHandler'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(true));
        $context->db_info = new stdClass();
        $context->db_info->use_db_session = 'Y';

        $context->expects($this->once())->method('setSessionSaveHandler');
        $context->expects($this->once())->method('startPHPSession');

        $context->startSession();
    }


    public function testStartSession_SessionEnabled_SessionNamePosted()
    {
        $context = $this->getMock('ContextInstance', array('startPHPSession', 'isInstalled'
            , 'getSessionController', 'getSessionModel'
            , 'setSessionSaveHandler', 'getSessionName', 'getPOSTArgument', 'setSessionId'));
        $context->expects($this->any())->method('isInstalled')->will($this->returnValue(true));
        $context->db_info = new stdClass();
        $context->db_info->use_db_session = 'Y';

        $context->expects($this->once())->method('setSessionSaveHandler');
        $context->expects($this->once())->method('startPHPSession');
        $context->expects($this->any())->method('getSessionName')->will($this->returnValue('PHPSESSID'));
        $context->expects($this->any())->method('getPOSTArgument')
            ->with($this->equalTo($context->getSessionName()))
            ->will($this->returnValue('my-session'));
        $context->expects($this->once())->method('setSessionId')
            ->with($this->equalTo('my-session'));

        $context->startSession();
    }

    public function testGetRequestUrl_NoParams()
    {
        $url = 'http://www.karybu.org/';

        $context = $this->getMock('ContextInstance', array('getRequestUri'));
        $context->expects($this->any())->method('getRequestUri')->will($this->returnValue($url));

        $this->assertEquals($url, $context->getRequestUrl());
    }

    public function testGetRequestUrl_WithParams()
    {
        $url = 'http://www.karybu.org/';

        $context = $this->getMock('ContextInstance', array('getRequestUri', 'getArgumentsForGETRequest', 'convertEncodingStr'));
        $context->expects($this->any())->method('getRequestUri')->will($this->returnValue($url));
        $context->expects($this->any())->method('getArgumentsForGETRequest')->will($this->returnValue(
                array("color" => 'green', "sky" => "blue")
            ));
        $context->expects($this->atLeastOnce())->method('convertEncodingStr')
            ->will($this->returnCallback(function($string){ return $string; }));

        $this->assertEquals($url . '?color=green&sky=blue', $context->getRequestUrl());
    }

    public function testLoadLangSupported()
    {
        $supported_languages = array("en" => "English", "ro" => "Romana");
        $file_handler = $this->getMock('FileHandlerInstance', array('readFileAsArray'));
        $file_handler->expects($this->any())->method('readFileAsArray')
            ->will($this->returnValue(array("en,English", "ro,   Romana")));

        $context = new ContextInstance($file_handler);

        $context->loadLangSupported();

        $this->assertEquals($supported_languages, $context->lang_supported);
    }

    public function testRecursiveCheckVar_NoErrors()
    {
        $_GET = array("module" => "admin"
            , "act" => "dispLayoutAdminAllInstanceList");
        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(true, $context->isSuccessInit);
    }

    public function testRecursiveCheckVar_ClassicPHPTags()
    {
        $_GET = array("module" => "admin"
                , "act" => "dispLayout<?php echo 'Gotcha' ?>AdminAllInstanceList");
        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(false, $context->isSuccessInit);
    }

    public function testRecursiveCheckVar_ShortPHPTags()
    {
        $_GET = array("module" => "admin"
            , "act" => "dispLayout<? echo 'Gotcha' ?>AdminAllInstanceList");
        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(false, $context->isSuccessInit);
    }

    public function testRecursiveCheckVar_ScriptPHPTags()
    {
        $_GET = array("module" => "admin"
            , "act" => "dispLayout<script language='php'> echo 'Gotcha' </script>AdminAllInstanceList");
        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(false, $context->isSuccessInit);
    }

    public function testRecursiveCheckVar_ASPPHPTags()
    {
        $_GET = array("module" => "admin"
            , "act" => "dispLayout<% echo 'Gotcha' %>AdminAllInstanceList");
        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(false, $context->isSuccessInit);
    }

    public function testRecursiveCheckVar_Array()
    {
        $_GET = array("module" => "admin"
        , "act" => array(
                "dispLayout<% echo 'Gotcha' %>AdminAllInstanceList",
                "dispLayout<? echo 'Gotcha' ?>AdminAllInstanceList")
        );
        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(false, $context->isSuccessInit);
    }

    public function testFilterRequestVar_CleanupKnownNumericValues()
    {
        $_GET = array("page" => "123aaa" ,
                "cpage" => "457sss",
                "someones_srl" => "678eeee",
                "anything_else" => "23444assadsa"
        );

        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals(123, $context->get("page"));
        $this->assertEquals(457, $context->get("cpage"));
        $this->assertEquals(678, $context->get("someones_srl"));
        $this->assertEquals("23444assadsa", $context->get("anything_else"));
    }

    public function testFilterRequestVar_EscapeKnownStringValues()
    {
        $_GET = array("mid" => "aaa&aa" ,
            "vid" => "asdf<aaa",
            "search_keyword" => "what>s up",
            "anything_else" => "Don't convert>         "
        );

        $_REQUEST = $_GET;

        $context = new ContextInstance();
        $context->_setRequestArgument();

        $this->assertEquals("aaa&amp;aa", $context->get("mid"));
        $this->assertEquals("asdf&lt;aaa", $context->get("vid"));
        $this->assertEquals("what&gt;s up", $context->get("search_keyword"));
        $this->assertEquals("Don't convert>", $context->get("anything_else"));
    }

    public function testFilterRequestVar_StripSlashes()
    {
        $_GET = array("some_key" => "asdf\\\"\\\\aaa");

        $_REQUEST = $_GET;

        $context = $this->getMock('ContextInstance', array('magicQuotesAreOn', 'magicQuotesAreSupportedInCurrentPHPVersion'));
        $context->expects($this->any())->method('magicQuotesAreOn')->will($this->returnValue(true));
        $context->expects($this->any())->method('magicQuotesAreSupportedInCurrentPHPVersion')->will($this->returnValue(true));

        $context->_setRequestArgument();

        $this->assertEquals("asdf\"\\aaa", $context->get("some_key"));
    }

    public function testEncodeString_UTF8()
    {
        $to_encode = new stdClass();
        $to_encode->text = iconv('UTF-8','UTF-8','XE팀은 작년 하반기 동안');

        $context = new ContextInstance();
        $encoded = $context->convertEncoding($to_encode);

        $this->assertEquals('XE팀은 작년 하반기 동안', $encoded->text);
    }

    public function testEncodeString_DifferentFromUTF8()
    {
        $to_encode = new stdClass();
        $to_encode->text = iconv('UTF-8','EUC-KR','XE팀은 작년 하반기 동안');

        $context = new ContextInstance();
        $encoded = $context->convertEncoding($to_encode);

        $this->assertEquals('XE팀은 작년 하반기 동안', $encoded->text);
    }

    public function testEncodeString_Array()
    {
        $to_encode = new stdClass();
        $euc_kr_encoded_string = iconv('UTF-8','EUC-KR','XE팀은 작년 하반기 동안');
        $to_encode->text = array($euc_kr_encoded_string, $euc_kr_encoded_string);

        $context = new ContextInstance();
        $encoded = $context->convertEncoding($to_encode);

        $this->assertEquals(array('XE팀은 작년 하반기 동안', 'XE팀은 작년 하반기 동안'), $encoded->text);
    }

    public function testEncodingStr_DifferentFromUTF8()
    {
        $to_encode = iconv('UTF-8','EUC-KR','XE팀은 작년 하반기 동안');

        $context = new ContextInstance();
        $encoded = $context->convertEncodingStr($to_encode);

        $this->assertEquals('XE팀은 작년 하반기 동안', $encoded);
    }

    public function testPathToUrl()
    {
        $k_path = _KARYBU_PATH_; // _KARYBU_PATH_ was defined in Bootstrap file

        if (!(strpos($k_path, '\\') === false)){
            $this->markTestSkipped('This doesn\'t work for non UX systems');
        }

        $context = $this->getMock('ContextInstance', array('getRequestUri'));
        $context->expects($this->any())->method('getRequestUri')->will($this->returnValue('http://localhost/xe'));

        $web_path = $context->pathToUrl($k_path);

        $this->assertEquals('/xe/', $web_path);

        $context = $this->getMock('ContextInstance', array('getRequestUri'));
        $context->expects($this->any())->method('getRequestUri')->will($this->returnValue('http://www.karybu.org'));

        $web_path = $context->pathToUrl('images');

        $this->assertEquals('/../images/', $web_path);
    }

    public function testAddSSLAction_CacheFileDoesntExist()
    {
        // Arrange
        $context = $this->getMock('ContextInstance', array('sslActionsFileExists', 'createSslActionsFile', 'enableSslAction'));
        $context->expects($this->once())->method('sslActionsFileExists')->will($this->returnValue(false));

        // Assert
        $context->expects($this->once())->method('createSslActionsFile');

        // Act
        $context->addSSLAction('dispDashboard');
    }

    public function testAddSSLAction_CacheFileExists_ActionDoesnt()
    {
        // Arrange
        $context = $this->getMock('ContextInstance', array('sslActionsFileExists', 'createSslActionsFile', 'enableSslAction'));
        $context->expects($this->once())->method('sslActionsFileExists')->will($this->returnValue(true));

        // Assert
        $context->expects($this->never())->method('createSslActionsFile');
        $context->expects($this->once())->method('enableSslAction')->with($this->equalTo('dispDashboard'));

        // Act
        $context->addSSLAction('dispDashboard');

        // Assert
        // Actions will only become available in the following request
        $this->assertEquals(0, count($context->getSSLActions()));
        $this->assertEquals(false, $context->isExistsSSLAction('dispDashboard'));
    }

    public function testAddSSLAction_CacheFileExists_ActionDoes()
    {
        // Arrange
        $context = $this->getMock('ContextInstance', array('sslActionsFileExists', 'createSslActionsFile', 'enableSslAction'));
        $context->expects($this->once())->method('sslActionsFileExists')->will($this->returnValue(true));
        $context->ssl_actions['dispDashboard'] = 1;

        // Assert
        $context->expects($this->never())->method('createSslActionsFile');
        $context->expects($this->never())->method('enableSslAction');

        // Act
        $context->addSSLAction('dispDashboard');
    }

    public function testLoadSslActions()
    {
        $context = $this->getMock('ContextInstance', array('sslActionsFileExists', 'getSslActionsFromCacheFile'));
        $context->expects($this->once())->method('sslActionsFileExists')->will($this->returnValue(true));

        $context->expects($this->once())->method('getSslActionsFromCacheFile')->will($this->returnValue(
                    array(
                        'dispDashboard' => 1,
                        'procSomething' => 1
                    )
            ));

        $context->loadSslActionsCacheFile();

        $this->assertEquals(2, count($context->getSSLActions()));
    }


}

/* End of file ContextInstanceTest.php */
/* Location: ./tests/classes/context/ContextInstanceTest.php */

