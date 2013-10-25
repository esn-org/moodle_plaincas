<?php

/**
 * @author Mohammed Nassar
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle auth
 *
 * Authentication Plugin: PhpCAS Authentication only
 *
 *
 * 2013-10-10  File created.
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php'); //This library defines the auth_plugin_base class that is the basis for any new authentication module for moodle. 
require_once($CFG->dirroot.'/auth/moocas/include/CAS-1.3.2/CAS.php'); // Load the CAS lib

/**
 * Plugin for no authentication.
 */
class auth_plugin_moocas extends auth_plugin_base {

    var $_userInfo = array();

    /**
     * Constructor.
     */
    function auth_plugin_moocas() {

        $this->authtype = 'moocas'; 
        $this->roleauth = 'auth_moocas';
        $this->errorlogtag = '[AUTH MOOCAS] ';
        $this->pluginconfig = 'auth/'.$this->authtype;

        $this->config = get_config($this->pluginconfig);
    }

    /**
     * Returns true if the username and password work or don't exist and false
     * if the user exists and the password is wrong.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login ($username, $password) {
        global $DB;

        //$this->CAS_connect();
        return phpCAS::isAuthenticated() && (trim(textlib::strtolower(phpCAS::getUser())) == $username);
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
    * Indicates if password hashes should be stored in local moodle database.
    * @return bool
    */
    function prevent_local_passwords() {
        return true;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return false;
    }

    /**
    * Returns true if plugin allows resetting of internal password.
    *
    * @return bool
    */
    function can_signup() {
        return false;
    }

    /**
    * Returns true if plugin allows editing the profile.
    *
    * @return bool
    */
    function can_edit_profile() {
        return true;
    }

    /**
    * Authentication choice (CAS or other)
    */
    function loginpage_hook() {
       
       //return false;    
        global $CFG, $DB, $USER, $SESSION, $OUTPUT, $PAGE;
        $site = get_site();

        // Return if CAS enabled and settings not specified yet
        if (empty($this->config->hostname)) {
            return;
        }

        $this->CAS_connect();

        /*if ($authParam == 'CAS') {
             if (!phpCAS::checkAuthentication()) {
                phpCAS::forceAuthentication();
            }
        }*/

        // force CAS authentication
        phpCAS::forceAuthentication();
        $username = '';

        if (phpCAS::checkAuthentication()) {
            $key = sesskey();

            if (phpCAS::isAuthenticated() || phpCAS::checkAuthentication()){
                $username = phpCAS::getUser();
                $user = $this->isUserExistInMoodle($username);
                if ($user) {
                    $user_array = $this->get_userinfo($username);
                    $user = authenticate_user_login($username, $key);
                    $USER = complete_user_login($user);

                    $urltogo = $CFG -> wwwroot . '/';
                    redirect($urltogo);
                    
                } else {//Moodle should create a new user entry in its DB
                    $user_array = $this->get_userinfo($username);
                    
                    //Creating a new user account
                    $user = authenticate_user_login($username, $key);

                    $USER = complete_user_login($user);

                    $urltogo = $CFG -> wwwroot . '/';
                    redirect($urltogo); 
                }

                add_to_log(SITEID, 'user', 'login', "view.php?id=$USER->id&course=".SITEID, $user->id, 0, $user->id);
            
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Logout from the CAS
     *
     */
    function prelogout_hook() {
        global $CFG;

        if (!empty($this->config->logoutcas)) {
            $backurl = $CFG->wwwroot;
            $this->CAS_connect();
            phpCAS::logoutWithRedirectService($backurl);
        }
    }


    /**
    * Connect to CAS
    */
    function CAS_connect() {
        global $CFG, $PHPCAS_CLIENT;

        if (!is_object($PHPCAS_CLIENT)) {
            // Uncomment to enable debugging
            phpCAS::setDebug();

            // Make sure phpCAS doesn't try to start a new PHP session when connecting to the CAS server.
            // Initialize phpCAS
            phpCAS::client($this->config->casversion, $this->config->hostname, (int) $this->config->port, $this->config->baseuri, true);
            
            // For quick testing you can disable SSL validation of the CAS server.
            if($this->config->certificate_check && $this->config->certificate_path){
                phpCAS::setCasServerCACert($this->config->certificate_path);
            }else{
                // Don't try to validate the server SSL credentials
                phpCAS::setNoCasServerValidation();
            }

            // force CAS authentication
            //phpCAS::forceAuthentication();

            return true;
   
        } else {
            return false;
        } 
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        global $CFG;

        include($CFG->dirroot.'/auth/moocas/config.html');
    }

    /**
     * A chance to validate form data, and last chance to
     * do stuff before it is inserted in config_plugin
     * @param object object with submitted configuration settings (without system magic quotes)
     * @param array $err array of error messages
     */
    function validate_form($form, &$err) {
        $certificate_path = trim($form->certificate_path);
        if ($form->certificate_check && empty($certificate_path)) {
            $err['certificate_path'] = get_string('auth_moocas_certificate_path_empty', 'auth_moocas');
        }
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config($config) {

        // CAS settings
        if (!isset($config->hostname)) {
            $config->hostname = '';
        }
        if (!isset($config->port)) {
            $config->port = '';
        }
        if (!isset($config->casversion)) {
            $config->casversion = '';
        }
        if (!isset($config->baseuri)) {
            $config->baseuri = '';
        }
        if (!isset($config->logoutcas)) {
            $config->logoutcas = '';
        }
        if (!isset($config->certificate_check)) {
            $config->certificate_check = '';
        }
        if (!isset($config->certificate_path)) {
            $config->certificate_path = '';
        }
        if (!isset($config->logout_return_url)) {
            $config->logout_return_url = '';
        }

        // CAS Roles settings
        $roles = get_all_roles();
        if($roles){
           foreach($roles as $role){
                $fieldName = "cas_role_".$role->id;
                if (!isset($config->$fieldName)) {
                    $config->$fieldName = '';
                }
            } 
        }
        
        // save CAS settings
        set_config('hostname', trim($config->hostname), $this->pluginconfig);
        set_config('port', trim($config->port), $this->pluginconfig);
        set_config('casversion', $config->casversion, $this->pluginconfig);
        set_config('baseuri', trim($config->baseuri), $this->pluginconfig);
        set_config('logoutcas', $config->logoutcas, $this->pluginconfig);
        set_config('certificate_check', $config->certificate_check, $this->pluginconfig);
        set_config('certificate_path', $config->certificate_path, $this->pluginconfig);
        set_config('logout_return_url', $config->logout_return_url, $this->pluginconfig);

        if($roles){
            foreach($roles as $role){
                $fieldName = "cas_role_".$role->id;
                set_config($fieldName, trim($config->$fieldName), $this->pluginconfig);
            }
        }

        return true;
    }

    /**
     * Reads user information from CAS and returns it in array()
     *
     * Function should return all information available. If you are saving
     * this information to moodle user-table you should honor syncronization flags
     *
     * @param string $username username
     *
     * @return mixed array with no magic quotes or false on error
     */
    function get_userinfo($username) {

        if (empty($this->config->hostname)) {
            return array();
        }

        $userinfo = array();
        $user_att = phpCAS::getAttributes();

        // Check whether we have got all the essential attributes (first name, last name, email)
        /*if ( empty() || empty() || empty() ) {
            print_error();
        }*/

        $attrmap = $this->get_attributes();

        foreach ($attrmap as $key=>$value) {
            // Check if attribute is present
            if (!isset($user_att[$value])){
                $userinfo[$key] = '';
                continue;
            }

            // Make usename lowercase
            if ($key == 'username'){
                $userinfo[$key] = strtolower($this->get_first_string($user_att[$value]));
            } else {
                $userinfo[$key] = $this->get_first_string($user_att[$value]);
            }
        }

        return $userinfo;
    }


    /**
    * Checks if user exists on Moodle
    *
    * @param string $username
    */
    function isUserExistInMoodle($username) {
        global $CFG, $DB;
        $user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id));
        return $user;
    }

    /**
     * Returns array containg attribute mappings between Moodle and CAS.
     *
     * @return array
     */
    function get_attributes() {
        $configarray = (array) $this->config;

        $moodleattributes = array();
        foreach ($this->userfields as $field) {
            if (isset($configarray["field_map_$field"])) {
                $moodleattributes[$field] = $configarray["field_map_$field"];
            }
        }
        $moodleattributes['username'] = $configarray["user_attribute"];

        return $moodleattributes;
    }

    /**
     * Cleans and returns first of potential many values (multi-valued attributes)
     *
     * @param string $string Possibly multi-valued attribute from CAS
     */
    function get_first_string($string) {
        $list = explode( ';', $string);
        $clean_string = rtrim($list[0]);

        return $clean_string;
    }


    /**
     * Reads user information from CAS and returns it in an object
     *
     * @param string $username username (with system magic quotes)
     * @return mixed object or false on error
     */
    function getUserInfoAsObj($user_array) {

        if ($user_array == false) {
            return false; //error or not found
        }
        $user = new stdClass();

        $user->username  = $user_array['username'];
        $user->email     = $user_array['mail'];
        $user->auth      = $this->authtype;
        $user->firstname = $user_array['first'];
        $user->lastname  = $user_array['last'];

        // Prep a few params
        //$user->modified   = time();
        $user->confirmed  = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;

        return $user;
    }

    /**
     * Syncronizes user from CAS to moodle user table
     *
     * Sync is now using username attribute.
     *
     * @param bool $do_updates will do pull in data updates from CAS if relevant
     */
    function sync_users($do_updates=true) {
        global $DB;

        //$id = $DB->insert_record('user', $this->_userInfo);
    }

    function update_user_record($username) {
        global $DB;
        /*$user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id));
        $userid = $user->id;
        $DB->set_field('user', 'email', 'myemail@mydomain.net', array('id'=>$userid));
        return $DB->get_record('user', array('id'=>$userid, 'deleted'=>0));*/
    }

    /**
     * Sync roles for this user
     *
     * @param $user object user object
     */
    function sync_roles($user) {
        global $USER;
        $systemcontext = context_system::instance();
        $existUserRoles = array($this->getUserRoles($USER->id));//Get the user's role in Moodle
        $this->_setCASGroups ();

        $CAS_roles_IDs = $this->getRolesIDs($this->_userInfo['tmp_roles']);

        //Get just the ids for user's moodle roles
        $moodleTempRoles = array();
        foreach ($existUserRoles as $value) {
            array_push($moodleTempRoles, $value->roleid);
        }

        $inMoodleNotInCAS = array_diff($moodleTempRoles, $CAS_roles_IDs);
        $inCASNotInMoodle = array_diff($CAS_roles_IDs, $moodleTempRoles);

        foreach ($inMoodleNotInCAS as $key => $value) {
           // role_unassign((int)$value, $user->id, $systemcontext->id, $this->roleauth);//@TODO: still do not unassign the roles
            //@TODO: Check for the context
        }

        foreach ($inCASNotInMoodle as $key => $value) {
            role_assign((int)$value, $user->id, $systemcontext->id, $this->roleauth, $itemid = 0, $timemodified = '');
        }
    }


    function getRolesIDs($rolesNames) {
        $rolesIDs = array();
        foreach ($this->_userInfo['tmp_roles'] as $key => $value) {
            $role_id = substr($value, 9, 1);
            array_push($rolesIDs, $role_id);
        }

        return $rolesIDs;
    }


    /**
    * 
    *
    * @author  Fabian Bircher
    */
    function _setCASGroups () {
        if( phpCAS::checkAuthentication() ) {
          $attributes = $this->get_role_attributes(phpCAS::getAttributes());
          if (!is_array($attributes)) {
            $attributes = array($attributes);
          }
          $patterns = $this->get_role_patterns();
          if (!empty($patterns)) {
            foreach ($patterns as $role => $pattern) {
              foreach ($attributes as $attribute) {

                // An invalid pattern will generate a php warning and will not be considered.
                if (preg_match($pattern, $attribute)) {
                  $this->_addUserRole($role);
                }
              }
            }
          }
          else {
            foreach ($attributes as $attribute) {
              // Add all attributes as groups
              $this->_addUserRole($attribute);
            }
          }
        }
    }

    /**
    * 
    *
    * @author  Fabian Bircher
    */
    function get_role_attributes( $attributes ){
        if (is_array($attributes['roles'])) {
            return $attributes['roles'];
        } else {
            return array($attributes['roles']);
        }
    }


    function get_role_patterns() {
        $rolePatterns = array();

        foreach ($this->config as $key => $value) {
           if(preg_match('/^cas_role_[0-9]*$/', $key)) {
                $rolePatterns[$key] = $value;
            }
        }

        return $rolePatterns;
    }

    /**
    * 
    *
    * @author  Fabian Bircher
    */
    function _addUserRole ($roleName) {
        if (! isset($this->_userInfo['tmp_roles'])) {
          $this->_userInfo['tmp_roles'] = array();
        }
        if( !in_array(trim($roleName), $this->_userInfo['tmp_roles'])) {
          $this->_userInfo['tmp_roles'][] = trim($roleName);
        } 
    }

    /**
    * return user role
    */
    function getUserRoles($userid) {
        $context = get_context_instance (CONTEXT_SYSTEM);
        $roles = get_user_roles($context, $userid, true);
        
        return $roles;
    }


    /**
    * Hook for logout page
    */
    function logoutpage_hook() {
        global $USER, $redirect;
        // Only do this if the user is actually logged in via CAS
        if ($USER->auth === $this->authtype) {
            // Check if there is an alternative logout return url defined
            if (isset($this->config->logout_return_url) && !empty($this->config->logout_return_url)) {
                // Set redirect to alternative return url
                $redirect = $this->config->logout_return_url;
            }
        }
    }

}


