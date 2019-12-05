<?php
/*
Plugin Name: URI Redirect Plus
Description: Redirect URIs to internal or external pages (with '301 Moved Permanently' header). Also view the count and date of last redirection for each redirect.
Version: 0.2.3
Author: Jared Lyon (based on original code from Nathan Friemel)
*/

# get correct id for plugin
$thisfile=basename(__FILE__, '.php');
$uriRedirectFile=GSDATAOTHERPATH .'uri_redirect_plus_settings.xml';
$pluginVersion = '0.2.3';

# Multilingual support by language only, not country ('en' not 'en_US')
global $LANG;
i18n_merge('uri_redirect_plus', substr($LANG,0,2)) || i18n_merge('uri_redirect_plus','en');

# register plugin
register_plugin(
	$thisfile,                      # ID of plugin, should be filename minus php
	'URI Redirect Plus',            # Title of plugin
	$pluginVersion,                 # Version of plugin
	'Jared Lyon',                   # Author of plugin
	'http://github.com/jlyon1515',  # Author URL
	i18n_r('uri_redirect_plus/PLUGIN_DESCRIPTION'), 	# Plugin Description
	'pages',                        # Page type of plugin
	'uri_redirect_show'             # Function that displays content
);

$websiteSettingsFile=GSDATAOTHERPATH .'website.xml';
$max_length_external_display = 38;
$truncation_string = '…';

# hooks
add_action('pages-sidebar','createSideMenu',array($thisfile,i18n_r('uri_redirect_plus/URI_REDIRECT_PLUS_SIDEMENU')));
add_action('index-pretemplate','do_uri_redirect');


/**
 * Create Plugin Page
 *
 * <p>Handle form submits, display form for adding new page, 
 * and display table of current redirects</p>
 */
function uri_redirect_show(){
	global $uriRedirectFile, $websiteSettingsFile, $success, $error, $SITEURL, $truncation_string, $max_length_external_display;

	checkVersion();
	
	//Check if settings file exists
	if (file_exists($uriRedirectFile)) {
		$xml_settings = simplexml_load_file($uriRedirectFile);
	}
	//Check if settings file from former incarnation of this plugin exists and copy it
	else if (file_exists(GSDATAOTHERPATH .'URIRedirectSettings.xml')) {
		copy(GSDATAOTHERPATH .'URIRedirectSettings.xml', $uriRedirectFile);
		$xml_settings = simplexml_load_file($uriRedirectFile);
	}
	//Create new settings file
	else {
		$xml = @new SimpleXMLElement('<uris></uris>');
		$xml->asXML($uriRedirectFile);
		$xml_settings = simplexml_load_file($uriRedirectFile);
	}
    
    //Get website settings file (to get Permalink configuration)
	if (file_exists($websiteSettingsFile)) {
		$website_settings = simplexml_load_file($websiteSettingsFile);
	}
    else { $website_settings = array(); }
	
	// submitted form
	if (isset($_POST['submit'])) {
		// check to see if URI provided
		if ($_POST['incoming_uri'] != '' && ($_POST['redirect_page'] != '' || $_POST['redirect_page_external'] != '') ){
			$incoming_uri = $_POST['incoming_uri'];
			if($_POST['redirect_page_external'] != ''){
				if(strtolower(substr($_POST['redirect_page_external'],0,7)) != 'http://' && strtolower(substr($_POST['redirect_page_external'],0,8)) != 'https://' ){
					$error .= i18n_r('uri_redirect_plus/EXTERNAL_URL_FORMATTING');
				}
				else{
					$redirect_page_title = '';
					$redirect_page_url = $_POST['redirect_page_external'];
					$redirect_page_parent = '';
				}
			}
			else{
				$redirect_page = explode('|||', $_POST['redirect_page']);
				$redirect_page_title = $redirect_page[0];
				$redirect_page_url = $redirect_page[1];
				$redirect_page_parent = $redirect_page[2];
			}
			
		} else {
			$error .= i18n_r('uri_redirect_plus/NO_REDIRECT_ADDED');
		}
		
        // Need to do a check to see if "from redirect" already exists
        
		// if there are no errors, save data
		if (!$error) {
			$uri = $xml_settings->addChild('uri');
			$uri->addChild('id', uniqid());
			$uri->addChild('incoming', $incoming_uri);
			$uri->addChild('redirect_page_title', $redirect_page_title);
			$uri->addChild('redirect_page_url', $redirect_page_url);
			$uri->addChild('redirect_page_parent', $redirect_page_parent);
            $uri->addChild('date_created', date('M j, Y g:ia') );
			$uri->addChild('redirect_count', '0');
			$uri->addChild('last_accessed', i18n('uri_redirect_plus/NEVER'));
			
			if (! $xml_settings->asXML($uriRedirectFile)) {
				$error = i18n_r('CHMOD_ERROR');
			} else {
				$success = i18n_r('SETTINGS_UPDATED');
			}
		}
	}
	elseif (isset($_POST['delete'])) {
		$id = $_POST['id'];
		if ($id != '') {
			$i = 0;
			foreach ($xml_settings as $a_setting) {
				if ($id == $a_setting->id) {
					unset($xml_settings->uri[$i]);
					break;
				}
				$i++;
			}
			if (!$xml_settings->asXML($uriRedirectFile)) {
				$error = i18n_r('CHMOD_ERROR');
			} else {
				$success = i18n_r('SETTINGS_UPDATED');
			}
		} else {
			$error .= i18n_r('uri_redirect_plus/ID_NOT_FOUND');
		}
	}
	?>
	
	<h3><?php i18n('uri_redirect_plus/ADD_NEW_REDIRECT'); ?></h3>
	
	<?php 
	if ($success) { 
		echo '<p style="color:#669933;"><b>'. i18n_r('uri_redirect_plus/MSG_SUCCESS') .'</b></p>';
	} 
	if ($error) { 
		echo '<p style="color:#cc0000;"><b>'. i18n_r('uri_redirect_plus/MSG_ERROR') .'</b><br>'. $error .'</p>';
	}
	?>
	
	<form method="post" action="<?php echo $_SERVER ['REQUEST_URI']?>">
		<p><label for="incoming_uri" ><?php i18n('uri_redirect_plus/FROM'); ?></label><input id="incoming_uri" name="incoming_uri" class="text" /></p>
		<p><label for="redirect_page" ><?php i18n('uri_redirect_plus/TO_INTERNAL_PAGE'); ?></label><select id="redirect_page" name="redirect_page" class="select"><?php uri_pages_options(); ?></select></p>
		<p><label for="redirect_page_external" ><?php i18n('uri_redirect_plus/TO_EXTERNAL_PAGE'); ?></label><input id="redirect_page_external" name="redirect_page_external" class="text" /></p>
		
		<p><input type="submit" id="submit" class="submit" value="<?php i18n('BTN_SAVESETTINGS'); ?>" name="submit" /></p>
	</form>
	
	<?php if ($xml_settings) { ?>
	<h3><?php i18n('uri_redirect_plus/EXISTING_REDIRECTS'); ?></h3>
	<table class="edittable highlight paginate">
		<tbody>
			<tr>
				<th><?php i18n('uri_redirect_plus/FROM'); ?></th>
				<th><?php i18n('uri_redirect_plus/TO'); ?></th>
				<th><?php i18n('uri_redirect_plus/COUNT'); ?></th>
				<th><?php i18n('uri_redirect_plus/LAST_ACCESSED'); ?></th>
				<th style="text-align: center;"><?php i18n('uri_redirect_plus/DELETE'); ?></th>
			</tr>
	<?php
		foreach ($xml_settings as $a_setting) {
	?>
			<tr>
				<td><?php echo '<a href="'.$SITEURL. $a_setting->incoming.'">'. $a_setting->incoming .'</a>'; ?></td>
				<td><?php
				// If external URL
				if(strtolower(substr($a_setting->redirect_page_url,0,7)) == 'http://' || strtolower(substr($a_setting->redirect_page_url,0,8)) == 'https://' ){
					$truncated = urldecode($a_setting->redirect_page_url);
					
					if(substr($truncated,0,11) == 'http://www.') $truncated = substr($truncated, 11);
					if(substr($truncated,0,12) == 'https://www.') $truncated = substr($truncated, 12);
					if(substr($truncated,0,7) == 'http://') $truncated = substr($truncated, 7);
					if(substr($truncated,0,8) == 'https://') $truncated = substr($truncated, 8);
						
					if($max_length_external_display != '0' && strlen($truncated) > $max_length_external_display){
						
						$after_domain = strpos($truncated, '/') + 1;
						$truncated = substr($truncated, 0, $after_domain). $truncation_string . substr($truncated, -1*($max_length_external_display - $after_domain - strlen($truncation_string)));
					}
					echo '<a href="'.$a_setting->redirect_page_url.'" title="'.$a_setting->redirect_page_url.'">'. $truncated .'</a>';
				}
				// Else internal URL (written in same style as site's custom permalink setting)
				else{
                    $sitepermalink = $website_settings->PERMALINK;
                    $sitepermalink = str_replace('%parents%','%parent%',$sitepermalink);
                    $sitepermalink = str_replace('%parent%', stripcslashes($a_setting->redirect_page_parent), $sitepermalink);
                    $sitepermalink = str_replace('%slug%', stripcslashes($a_setting->redirect_page_url), $sitepermalink);
                    echo '<a href="'. $SITEURL . $sitepermalink .'">'. stripcslashes($a_setting->redirect_page_url) .'</a>';	
				} ?></td>
				<td><?php if(isset($a_setting->redirect_count)) echo $a_setting->redirect_count; else echo '0'; ?></td>
				<td><?php if(isset($a_setting->last_accessed) && trim($a_setting->last_accessed) != '' && $a_setting->last_accessed != 'Never') echo $a_setting->last_accessed; else i18n('uri_redirect_plus/NEVER'); ?></td>
				<td style="text-align: center;"><form method="post" action="<?php	echo $_SERVER ['REQUEST_URI']?>"><input type="hidden" name="id" value="<?php echo $a_setting->id; ?>" /><input style="cursor: pointer;" type="submit" value="X" name="delete" /></form></td>
			</tr>
	<?php
		}
	?>
		</tbody>
	</table>
	<?php
	}
}

/**
 * Sort multiple or multi-dimensional arrays
 *
 * <p>Used to get list of all availabl page sorted by parent then page title. 
 * Source: http://php.net/manual/en/function.array-multisort.php#100534</p>
 */
function array_orderby()
{
    $args = func_get_args();
    $data = array_shift($args);
    foreach ($args as $n => $field) {
        if (is_string($field)) {
            $tmp = array();
            foreach ($data as $key => $row)
                $tmp[$key] = $row[$field];
            $args[$n] = $tmp;
            }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}


/**
 * Generate List of Internal Pages in an Option List
 *
 * <p>Cycle through all the page xml files and build an options 
 * list to be inserted in a select tag</p>
 */
function uri_pages_options(){
	$path = GSDATAPAGESPATH;
	$dir_handle = @opendir($path) or die('Unable to open ' . $path);
	$filenames = array();
	while ($filename = readdir($dir_handle)) {
		$filenames[] = $filename;
	}
	closedir($dir_handle);

	$pagesArray = array();
	$count = 0;
	if (count($filenames) != 0) {
		foreach ($filenames as $file) {
			if ($file == '.' || $file == '..' || is_dir($path . $file) || $file == '.htaccess') {
				// not a page data file
			} else {
				$data = getXML($path . $file);
				if ($data->private != 'Y') {
					$pagesArray[$count]['parent'] = $data->parent;
					$pagesArray[$count]['title'] = stripcslashes($data->title);
					$pagesArray[$count]['url'] = $data->url;
					$pagesArray[$count]['sort'] = $data->parent .' - '. stripcslashes($data->title);
					$count++;
				}
			}
		}
	}
	
	// Sort the pagesArray by the 'sort' key created above
	$pagesSorted = subval_sort($pagesArray,'sort');
	
	//Need to sort by title within each parent here.
	
	$options = '<option value="">-'. i18n_r('uri_redirect_plus/SELECT_PAGE') .'-</option>';
	foreach ($pagesSorted as $page) {
		$options .= '<option value="' . $page[title] . '|||' . $page[url] . '|||' . $page[parent] . '">';
		if ($page['parent'] != '') {
			$options .= $page[parent];
		}
		$options .=  ' - '. $page[title] . '</option>';
	}
	echo $options;
}

/**
 * Do Redirect
 *
 * <p>Fired before a page is loaded to check if the current URI 
 * is in the uri_redirect_file and if it is it will redirect 
 * to the provided page</p>
 */
function do_uri_redirect(){
	global $uriRedirectFile, $websiteSettingsFile, $SITEURL;

	if (file_exists($uriRedirectFile)) {
		$xml_settings = simplexml_load_file($uriRedirectFile);

		$subFolder = parse_url($SITEURL, PHP_URL_PATH);
		$subFolder = rtrim($subFolder, '/');
	
		$requestURI = rtrim($_SERVER['REQUEST_URI'], '/');
		$requestURI = str_replace('index.php?id=','',$requestURI);
			
		if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') {
			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}
		
		$i = 0;
		foreach ($xml_settings as $a_setting) {
			$incoming = rtrim($a_setting->incoming, '/');
			$incoming = $subFolder .'/'. $incoming;

			if ($incoming == $requestURI) {

				if(isset($a_setting->redirect_count) )
					$new_redirect_count = $a_setting->redirect_count +1;
				else $new_redirect_count = '1';
				$new_last_accessed = date('M j, Y g:ia');
				
				$xml_settings->uri[$i]->redirect_count = $new_redirect_count;
				$xml_settings->uri[$i]->last_accessed = $new_last_accessed;

				//echo $xml_settings->asXML();
				
				if (! $xml_settings->asXML($uriRedirectFile)) {
					$error = i18n_r('CHMOD_ERROR');
				} else {
					$success = i18n_r('SETTINGS_UPDATED');
				}
				
				// If redirect to external page		
				if(strtolower(substr($a_setting->redirect_page_url,0,7)) == 'http://' || strtolower(substr($a_setting->redirect_page_url,0,8)) == 'https://' ){
					header ('HTTP/1.1 301 Moved Permanently');
					header ('Location: ' . $a_setting->redirect_page_url);
					die();
				}
				// Else must be redirecting to internal page
				else{
                    
                    // Get as site's custom permalink setting, and write URL to be redirected to in that style
                    $sitepermalink = $website_settings->PERMALINK;
                    $sitepermalink = str_replace('%parents%','%parent%',$sitepermalink);
                    $sitepermalink = str_replace('%parent%', stripcslashes($a_setting->redirect_page_parent), $sitepermalink);
                    $sitepermalink = str_replace('%slug%', stripcslashes($a_setting->redirect_page_url), $sitepermalink);	
                    // If %parent% wasn't rewritten in the permalink, then a parent wasn't set for some reason, so remove that text
                    if(strpos($sitepermalink, '%parent%') !== false){
                        $sitepermalink = str_replace('%parent%','',$sitepermalink);
                    }
                    
					$link = $subFolder .'/'. $sitepermalink;
                    //Just in case and double slashes exist, replace them with a single one
                    $link = str_replace('//','/',$link);
                    
					if ($a_setting->redirect_page_parent != '') {
						$link .= $a_setting->redirect_page_parent . '/';
					}
					//Need to write code to check if GetSimple's Custom permalink structure is set to have a trailing slash, for now, assuming it doesn't
					$link .= $a_setting->redirect_page_url . '';
	
					header ('HTTP/1.1 301 Moved Permanently');
					
					//echo $protocol . $_SERVER['SERVER_NAME'] . $link;
					header ('Location: ' . $protocol . $_SERVER['SERVER_NAME'] . $link);
					die();
				}
			}
			$i++;
		}
	}
}

/**
 * Check For New Plugin Version
 *
 * <p>Checks the GetSimple extend_api and compares versions  
 * and if the versions are different displays a note to the user</p>
 */
function checkVersion(){
	try { // Check if curl installed
		$v = curl_version();
	} catch (Exception $e) {
		return;
	}

	global $pluginVersion;
	
	$c = curl_init();
	curl_setopt($c, CURLOPT_URL, 'http://get-simple.info/api/extend/?id=1062');
	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
	$checkVersion = json_decode(curl_exec($c), true);
	curl_close($c);

	if ($pluginVersion < $checkVersion['version']) {
		echo '<p style="background: #F7F7C3; border: 1px solid #F9CF51; color:#669933; padding: 5px 10px;">'. i18n_r('uri_redirect_plus/MSG_PLUGIN_UPDATE') .' <a href="' . $checkVersion['file'] . '">'. i18n_r('uri_redirect_plus/MSG_DOWNLOAD_NOW') .'</a> (v'. $checkVersion['version'] .')</p>';
	}
}
?>