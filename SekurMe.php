<?php
session_start();

/*
Plugin Name: SekurMe
Plugin URI: http://localhost:8080/wordpress/
version: 1.0.1
Description: This plugin enables multi factor authentication using SEKUR.me
Author: Krijesh PV
*/

//Actions on different hooks
add_action('init', 'SekurMe_init_method');
add_action('show_user_profile', 'SekurMe_extra_profile_fields');
add_action('edit_user_profile', 'SekurMe_extra_profile_fields');
add_action('personal_options_update', 'SekurMe_save_extra_profile_fields');
add_action('edit_user_profile_update', 'SekurMe_save_extra_profile_fields');
add_action('login_form','SekurMe_login_button_implementation');
add_action('admin_menu', 'SekurMe_admin_menu');

add_filter( 'query_vars', 'add_query_vars_filter' );

function _is_curl_installed() {
    if  (in_array  ('curl', get_loaded_extensions())) {
        return true;
    }
    else {
        return false;
    }
}

if(isset($_GET['action']) && $_GET['action']=="sekur"){
    header( 'Content-type: application/xml' );
	sekurme_response($_REQUEST['server_response']);
	exit;

}
function sekurme_response($response){
	require_once('sekurme_helper.php');

	$res_array = sekurme_helper::_process_response($response);

	if(isset($res_array['CompanyID'])){
		$sekur_company_id = get_option('sekur_company_id');
		if($res_array['CompanyID'] != $sekur_company_id){
		$root = "SekurProcessVerificationResponse";
			$res = array();
			$res['ErrorCode'] = 1;
			$res['StatusMessage'] = "CompanyID Error";
			$xml_res = sekurme_helper::xml_generator($res,$root);
			echo $xml_res;
			return;
		}
	}
	else
	{
		$root = "SekurProcessVerificationResponse";
		$res = array();
		$res['ErrorCode'] = 1;
		$res['StatusMessage'] = "CompanyID Error";
		$xml_res = sekurme_helper::xml_generator($res,$root);
		echo $xml_res;
		return;
	}
	//Checking the Store ID
	if(isset($res_array['StoreID'])){
		$sekur_store_id = get_option('sekur_store_id');
		if($sekur_store_id != $res_array['StoreID']){
		$root = "SekurProcessVerificationResponse";
		$res = array();
			$res['ErrorCode'] = 2;
			$res['StatusMessage'] = "StoreID Error";
			$xml_res = sekurme_helper::xml_generator($res,$root);
			echo $xml_res;
			return;
		}
	}

	//Checking the Authorization Status
	if($res_array['AuthorizationStatus'] == 1){
		$root = "SekurProcessVerificationResponse";
		$res = array();
		$res['ErrorCode'] = 5;
		$res['StatusMessage'] = "Bad AuthorizationStatus";
		$xml_res = sekurme_helper::xml_generator($res,$root);
		echo $xml_res;
		return;
	}

	global $wpdb;

	$query = "SELECT sekuraction  FROM sekurmelogin  WHERE  etxnid= '".$res_array['ETXNID']."' ";
	
	$sekur_action= $wpdb->get_results($query);
	if(!empty($sekur_action)){
	
		$sekur_action = $sekur_action[0]->sekuraction;
	
	}else{
		$sekur_action = 0;
	}
		   	
	//Checking the LocalUserID.
	if($sekur_action == 11 ){
		
		$sql = 'delete from sekurmelogin where date_of_transaction<DATE_SUB(curdate(),INTERVAL '.DELETE_INTERVAL.'  DAY)';
		$done =  $wpdb->query($sql);  
		
		$sql = "update sekurmelogin set sekur_id='".$res_array['SekurId']."' where etxnid = '".$res_array['ETXNID']."'";
		$done =  $wpdb->query($sql);
	
		if($done){   
			$root = "SekurProcessVerificationResponse";
			$res = array();
			$res['ErrorCode'] = 0;
			$res['StatusMessage'] = "Success";
			$xml_res = sekurme_helper::xml_generator($res,$root);
			echo $xml_res;
			return;
	   }   
	   else {
			$root = "SekurProcessVerificationResponse";
			$res = array();
			$res['ErrorCode'] = 3;
			$res['StatusMessage'] = "EtxnId is not valid";
			$xml_res = sekurme_helper::xml_generator($res,$root);
			echo $xml_res;
			return;
	   }	    

	}
	else {
		
		//$user = get_user_by( 'id',$res_array['LocalUserID'] );
		$sql = "select * from wp_users where id=".$res_array['LocalUserID']; 
		$user = $wpdb->get_row($sql);
		
		$sql = "update sekurmelogin set sekur_id='".$res_array['SekurId']."' where etxnid = '".$res_array['ETXNID']."'"; 
	 	$done = $wpdb->query($sql);
		
		//if(!isset($user)){
		if(empty($user->user_login))
		{
			$root = "SekurProcessVerificationResponse";
			$res = array();
			$res['ErrorCode'] = 6;
			$res['StatusMessage'] = "Bad UserID";
			$xml_res = sekurme_helper::xml_generator($res,$root);
			echo $xml_res;
			return;
	   }
		$local_user_id = $res_array['LocalUserID']; 
	 
		$query = "SELECT user_id FROM wp_usermeta WHERE  meta_key =  'sekurid_".$res_array['SekurId']."'";
		$user_id= $wpdb->get_row($query);
		
		if(!empty($user_id)){
			if($res_array['LocalUserID'] != $user_id[0]->user_id){
				$query = "delete  FROM wp_usermeta WHERE  meta_key = 'sekurid_".$res_array['SekurId']."'"  ;
				$wpdb->query($query);
				update_usermeta( $local_user_id,'sekurid_'.$res_array['SekurId'],'sekurid');    
			}
			else{
				$query = "delete  FROM wp_usermeta WHERE  meta_key like 'sekurid_%'  and user_id= ".$local_user_id;
				$wpdb->query($query);
				update_usermeta( $local_user_id,'sekurid_'.$res_array['SekurId'],'sekurid'); 
			}
		}
			else{
			// Delete existing association for the local user id
			$query = "delete  FROM wp_usermeta WHERE  meta_key like 'sekurid_%'  and user_id= ".$local_user_id;
			$wpdb->query($query);
			update_usermeta( $local_user_id,'sekurid_'.$res_array['SekurId'],'sekurid');  
		}
		
		$root = "SekurProcessVerificationResponse";
		$res = array();
		$res['ErrorCode'] = 0;
		$res['StatusMessage'] = "Success";
		$xml_res = sekurme_helper::xml_generator($res,$root);
		echo $xml_res;
		exit;
	}
}

//initialization of diffternt scripts and styles
function SekurMe_init_method() {
	$saasHostName = get_option('sekur_host'); 
	wp_register_script ('sassjs',$saasHostName. "/Scripts/SekurMe.js");
        wp_register_script('sekurjs', plugins_url( '/scripts/sekur.js', __FILE__ ));
	wp_register_style('custom', plugins_url( '/styles/custom.css', __FILE__ ), array(), '20120208', 'all');
	wp_enqueue_style('custom');
	wp_register_style('SekurMePopup',$saasHostName. '/Styles/SekurMePopup.css');
}


//Creating custom table while installing a plugin & activating it.
function SekurMe_create_table() {
    global $wpdb;
 
    // this if statement makes sure that the table doe not exist already
    if($wpdb->get_var("show tables like sekurmelogin") != 'sekurmelogin') 
    {
		$sql = "CREATE TABLE sekurmelogin(
			sekuraction int NOT NULL,
					etxnid varchar(256) NOT NULL,
			tssid varchar(256) NOT NULL,
			sekur_id varchar(100),
			date_of_transaction DateTime NOT NULL,
			PRIMARY KEY (etxnid)
			)";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
    }
}

// this hook will cause our creation function to run when the plugin is activated
register_activation_hook( __FILE__, 'SekurMe_create_table' );


//Automatically delete the table which you created when the plugin is un-install
function SekurMeUninstall() {
    global $wpdb;
    $table = "sekurmelogin";
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

register_deactivation_hook( __FILE__, 'SekurMeUninstall' );


//Added the Sekur.me button in the User profile.
function SekurMe_extra_profile_fields( $user ) {

	$user_id = get_current_user_id();
	$sekur_id =get_metadata('usermeta', $user_id); 
	if(!$sekur_id){
		require_once('sekurme_helper.php'); 
		$config = array();
		$data =array();
		$data['CompanyID'] = get_option('sekur_company_id');
		$data['StoreID'] = get_option('sekur_store_id');
		$data['StoreAuth'] = get_option('sekur_store_auth');
		$config['sekur_host'] = get_option('sekur_host');
		$data['UserID'] = $user_id;
		$data['SekurAction'] = 15;    
		$req_root = "SekurStartTransactionRequest";
		$response = sekurme_helper::start_trac($data,$config['sekur_host'],$req_root);
		global $wpdb;
		$sql = "insert into sekurmelogin values(15,'".$response['ETXNID']."','".$response['TSSID']."','',NOW())";
	
		$done =  $wpdb->query($sql);     
    }
    else {  
    	$response = array();
    }
    wp_enqueue_script('jquery');
    wp_enqueue_script('sassjs');
    wp_enqueue_style('SekurMePopup');
    ?>
        <table class="form-table">
        <tr>
        <th><label for="sekurmelogin">Associate with <b><strong>SEKUR</strong></b><font color="red">.me</font></label></th>
        <td>   
            <div  style="vertical-align: top; margin-top: 0px;" class="sekurme-panel">
        <?php  if(!empty($response)){ ?> 
            <script> 
               jQuery(function(){
                var tssidValue = "<?php echo $response["TSSID"]  ?>"; 
                var etxnIdValue = "<?php echo $response["ETXNID"]  ?>";
                var qrUrl = "<?php echo $response["QRURL"]  ?>";
                var buttonType = "Associate";
            SekurMe.configure("sekurMeDiv", tssidValue, etxnIdValue, qrUrl, buttonType);
                  });
            </script>
        <?php  } ?>  
            </div>
        <?php  if(!empty($response)){ ?>
        <div id="sekurMeDiv"></div>
            <?php }else { ?>
            <b> Sekur.me is associated with your account</b>    
            <?php } ?>
        </td>
        </tr>
        <tr>
        <th>&nbsp </th>
        <td> <span><i>Forget passwords! Associate your account with <b><font color="black"><strong>SEKUR</strong></font></b><font color="red">.me</font>,
            which eliminates Usernames and Passwords securely.
            For more information, please go to <a href="http://www.SEKUR.me">www.SEKUR.me</a></i>
            </span>
        </td>
        </tr>
        </table>
<?php 
}


//Save the fields to the database.
function SekurMe_save_extra_profile_fields( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) )
	return false;
    update_usermeta( $user_id, 'login with sekurme', $_POST['sekurmelogin'] );
}

//Getting the query strings
function add_query_vars_filter( $vars ){
    $vars[] = "eTxnID";
    return $vars;
}

//Added the sekur me login button at  the front page of wordpress 
function SekurMe_login_button_implementation() {   
    if (_is_curl_installed()) {
    //  return;
     } else {
       echo "cURL is NOT <span style=\"color:red\">installed</span> on this server";
      }
    
    parse_str($_SERVER['QUERY_STRING']);
    if(isset($eTxnID)){
		//code check after redirection
		global $wpdb;
		$sekur_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT sekur_id from sekurmelogin where etxnid= %s",
		$eTxnID
		));
	
		$wordpress_user_id = $wpdb->get_var($wpdb->prepare(" SELECT user_id from wp_usermeta where meta_key=%s",'sekurid_'.$sekur_id));
		$user = get_user_by( 'id',$wordpress_user_id );
		if( $user ) {
			wp_set_current_user( $wordpress_user_id, $user->user_login );
			wp_set_auth_cookie( $wordpress_user_id);
			do_action( 'wp_login', $user->user_login );
			$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM sekurmelogin
				WHERE etxnid=%s",$eTxnID)
			);
			wp_redirect(admin_url());
	   }
	   else{
?>
			<b class="errormsg">User is not associated with SEKUR.me</b>
<?php
		}
    }   
    require_once('sekurme_helper.php'); 
    $config = array();
    $data =array();   
    $data['CompanyID'] = get_option('sekur_company_id');
    $data['StoreID'] = get_option('sekur_store_id');
    $data['StoreAuth'] = get_option('sekur_store_auth');   
    $data['APP_DATA'] = wp_login_url();
    $data['APP_DATA_CONTROL']= 'ALL';
    $data['APP_DATA_DESTINATION']='WEB';
    $config['sekur_host'] = get_option('sekur_host');
    $data['SekurAction'] = 11;
    $req_root = "SekurStartTransactionRequest";
    $response = sekurme_helper::start_trac($data,$config['sekur_host'],$req_root);
	global $wpdb;   
	$sql = "insert into sekurmelogin values(11,'".$response['ETXNID']."','".$response['TSSID']."','',NOW())";  
	$done =  $wpdb->query($sql);

    wp_enqueue_script('jquery');
    wp_enqueue_script('sassjs');
    wp_enqueue_style('SekurMePopup');
?>
    <div  style="vertical-align: top; margin-top: 0px;" class="sekurme-panel">
    <script>
	var tssidValue = "<?php echo $response["TSSID"]  ?>"; 
	var etxnIdValue = "<?php echo $response["ETXNID"]  ?>";
	var qrUrl = "<?php echo $response["QRURL"]  ?>";
	var buttonType = "Login";
    </script>
    <?php
    wp_enqueue_script('sekurjs');
    ?>
    </div>
    <div id="login_name"><h1>Login to your account</h1></div>
    <div id="sekurMeDiv" class="sekurApp"></div>
    <?php
}

// create plugin settings menu at Admin screen
function SekurMe_admin_menu() {

    //create new top-level menu
    add_menu_page('SEKUR.me Settings', 'SEKUR.me Settings', 'administrator', __FILE__, 'SekurMe_settings_page',plugins_url('/images/icon.png', __FILE__));
	
    //call register settings function
    add_action( 'admin_init', 'SekurMe_register_mysettings' );
	
}

//Registering the settings field
function SekurMe_register_mysettings() {
    register_setting( 'sekur-me-settings-group', 'sekur_company_id' );
    register_setting( 'sekur-me-settings-group', 'sekur_store_id' );
    register_setting( 'sekur-me-settings-group', 'sekur_store_auth' );
    register_setting( 'sekur-me-settings-group', 'sekur_host' );
}

function SekurMe_settings_page() {
?>   
	<div class="wrap">
    <h2>SEKUR.me Admin Settings</h2>
    <form method="post" action="options.php">
	<?php settings_fields( 'sekur-me-settings-group' ); ?>
	<?php do_settings_sections( 'sekur-me-settings-group' ); ?>
	<table class="form-table">
	    <tr valign="top">
		<th scope="row">Company ID:</th>
		<td><input type="text" name="sekur_company_id" id="company_id" required=true maxlength="34" value="<?php echo get_option('sekur_company_id'); ?>" /></td>
	    </tr> 
	    <tr valign="top">
		<th scope="row">Store ID:</th>
		<td><input type="text" name="sekur_store_id" id="store_id" required=true maxlength="34" value="<?php echo get_option('sekur_store_id'); ?>" /></td>
	    </tr>
	    <tr valign="top">
		<th scope="row">Store Authorization:</th>
		<td><input type="text" name="sekur_store_auth" id="store_auth" required=true maxlength="34" value="<?php echo get_option('sekur_store_auth'); ?>" /></td>
	    </tr>
	    <tr valign="top">
		<th scope="row">Host URL:</th>
		<td><input type="text" name="sekur_host" id="host" required=true maxlength="34" value="<?php echo get_option('sekur_host'); ?>" /></td>
	    </tr>
	</table>
    <span id="description">If you do not have this information, please contact <a href="http://support@SEKUR.me">support@SEKUR.me</a></span>
    <div class="message">Your update was successful.</div>
    <?php submit_button();
    ?>
    </form>
	</div>    
<?php } ?>










