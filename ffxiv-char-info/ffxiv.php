<?php
/*
Plugin Name: FFXI Character Stats for Wordpress
Description: Add FFXIV Character Information to your site.
Version: 1.0
Author: Demonicpagan
Author URI: http://ffxiv.stelth2000inc.com


Copyright 2007-2012  Demonicpagan  (email : demonicpagan@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('WP_STR_SHOW_FFXIV_NFO_PORT', '/<!-- FFXIV_port(\((([0-9],|[0-9]|,)*?)\))? -->/i');
define('WP_STR_SHOW_FFXIV_NFO_LAND', '/<!-- FFXIV_land(\((([0-9],|[0-9]|,)*?)\))? -->/i');

require('wplib/utils_formbuilder.inc.php');
require('wplib/utils_sql.inc.php');
require('wplib/utils_tablebuilder.inc.php');

include_once('apiv3.php');

global $cname,$cserver,$cavatar,$lsname,$lsemb;

// Admin section
// -------------
add_action('admin_menu', 'FFXIV_menu');

function FFXIV_menu()
{
	add_menu_page('FFXIV Character Information', 'FFXIV Config', 8, __FILE__, 'FFXIV_Conf');
}

function FFXIV_Conf()
{
?>
	<div class="wrap">
	<div id="icon-options-general" class="icon32">
	<br />
	</div>
	<h2>Final Fantasy XIV Character Configuration</h2>
<?php
	// Get all the options from the database for the form
	$setting_char_name = get_option('FFXIV_setting_name');
	$setting_char_server = get_option('FFXIV_setting_server');
	$setting_char_linkshell = get_option('FFXIV_setting_linkshell');

	// Check if updated data.
	if(isset($_POST) && isset($_POST['update']))
	{
		$setting_char_name = trim($_POST['FFXIV_setting_name']);
		$setting_char_server = trim($_POST['FFXIV_setting_server']);
		$setting_char_linkshell = trim($_POST['FFXIV_setting_linkshell']);

		update_option('FFXIV_setting_name', $setting_char_name);
		update_option('FFXIV_setting_server', $setting_char_server);
		update_option('FFXIV_setting_linkshell', $setting_char_linkshell);
	}

	// Build the form
	$form = new FormBuilder();

	$formElem = new FormElement('FFXIV_setting_name', 'FFXIV Character Name');
	$formElem->value = $setting_char_name;
	$formElem->description = "Some Name";
	$form->addFormElement($formElem);

	$formElem = new FormElement('FFXIV_setting_server', 'FFXIV Character Server');
	$formElem->value = $setting_char_server;
	$formElem->description = "Durandal";
	$form->addFormElement($formElem);

	$formElem = new FormElement('FFXIV_setting_linkshell', 'FFXIV Character Linkshell ID');
	$formElem->value = $setting_char_linkshell;
	$formElem->description = "This will be grabbed from <a href='http://xivpads.com/?-Linkshells' target='_blank'>http://xivpads.com/?-Linkshells</a> and doing a search and getting the resulting http://xivpads.com/?ls/<lsid>";
	$form->addFormElement($formElem);

	echo $form->toString();
}

function FFXIV_install()
{
	global $wpdb;

	// Create Default Settings
	if (!get_option('FFXIV_setting_name'))
		update_option('FFXIV_setting_name', 'Some name');

	if (!get_option('FFXIV_setting_server'))
		update_option('FFXIV_setting_server', 'Durandal');

	if (!get_option('FFXIV_setting_linkshell'))
		update_option('FFXIV_setting_linkshell', 'Linkshell ID from XIVPads.com');

	$wpdb->show_errors();
}

// Retrieval section
// ------------------
function FFXIVPads_Get()
{
	$name = get_option('FFXIV_setting_name');
	$server = get_option('FFXIV_setting_server');
	$lsid = get_option('FFXIV_setting_linkshell');

	// Call the XIVPads API
	$API = new LodestoneAPI();

	// Pull character information
	$Result = $API->SearchCharacter($name, $server);

	// Set data
	if ($Result[0])
	{
		$API->v3GetProfileData();
		$API->v3GetHistory(0);
		$API->v3GetAvatars();
		$API->GetLinkshellData($lsid);
	}

	$ffxiv = array("CName"		=> $API->player_name,
				   "CServer"	=> $API->player_server,
				   "CAvatar"	=> $API->player_avatar,
				   "LsName"		=> $API->linkshell_name,
				   "LsEmb"		=> $API->linkshell_emblum,
				   "Race"		=> $API->player_profile['Race'],
				   "Nation"		=> $API->player_profile['Nation'],
				   "BDate"		=> $API->player_profile['Birthdate'],
				   "Guardian"	=> $API->player_profile['Guardian'],
				   "Active"		=> $API->player_profile['Active']);

	return $ffxiv;
}

// Display character information
// ------------------------------
function FFXIV_Character_show_port($oldcontent)
{
	// Ensure we don't lose the original page
	$newcontent = $oldcontent;

	// Detect if we need to render the information by looking for the 
	// special string <!-- FFXIV_port -->
	if (preg_match(WP_STR_SHOW_FFXIV_NFO_PORT, $oldcontent, $matches))
	{
		// Turn DB stuff into HTML
		$content = FFXIV_Render_Port();

		// Now replace search string with formatted information
		$newcontent = ffxiv_replace_string($matches[0], $content, $oldcontent);
	}
	return $newcontent;
}

add_filter('widget_text', 'FFXIV_Character_show_port');

function FFXIV_Character_show_land($oldcontent)
{
	// Ensure we don't lose the original page
	$newcontent = $oldcontent;

	// Detect if we need to render the information by looking for the 
	// special string <!-- FFXIV_land -->
	if (preg_match(WP_STR_SHOW_FFXIV_NFO_LAND, $oldcontent, $matches))
	{
		// Turn DB stuff into HTML
		$content = FFXIV_Render_Land();

		// Now replace search string with formatted information
		$newcontent = ffxiv_replace_string($matches[0], $content, $oldcontent);
	}
	return $newcontent;
}

add_filter('the_content', 'FFXIV_Character_show_land');

function ffxiv_replace_string($searchstr, $replacestr, $haystack) {

	// Faster, but in PHP5.
	if (function_exists("str_ireplace")) {
		return str_ireplace($searchstr, $replacestr, $haystack);
	}
	// Slower but handles PHP4
	else { 
		return preg_replace("/$searchstr/i", $replacestr, $haystack);
	}
}

function FFXIV_Render_Port()
{
	$ffxiv = FFXIVPads_Get();

	// Remove the | in player_profile['Race']
	$ffxiv['Race'] = str_replace("|", " ", $ffxiv['Race']);

	$classes = array("Alechemist","Archer","Armorer","Blacksmith","Botanist","Carpenter","Conjurer","Fisher","Gladiator","Goldsmith","Lancer","Marauder","Miner",
					"Pugilist","Tanner","Thaumaturge","Weaver");

	$icons_class = array("Alchemist"		=> plugins_url('images/classes/Alchemist.png', __FILE__),
						 "Archer"			=> plugins_url('images/classes/Archer.png', __FILE__),
						 "Armorer"			=> plugins_url('images/classes/Armorer.png', __FILE__),
						 "Blacksmith"		=> plugins_url('images/classes/Blacksmith.png', __FILE__),
						 "Botanist"			=> plugins_url('images/classes/Botanist.png', __FILE__),
						 "Carpenter"		=> plugins_url('images/classes/Carpenter.png', __FILE__),
						 "Conjurer"			=> plugins_url('images/classes/Conjurer.png', __FILE__),
						 "Fisher"			=> plugins_url('images/classes/Fisher.png', __FILE__),
						 "Gladiator"		=> plugins_url('images/classes/Gladiator.png', __FILE__),
						 "Goldsmith"		=> plugins_url('images/classes/Goldsmith.png', __FILE__),
						 "Lancer"			=> plugins_url('images/classes/Lacer.png', __FILE__),
						 "Marauder"			=> plugins_url('images/classes/Marauder.png', __FILE__),
						 "Miner"			=> plugins_url('images/classes/Miner.png', __FILE__),
						 "Pugilist"			=> plugins_url('images/classes/Pugilist.png', __FILE__),
						 "Tanner"			=> plugins_url('images/classes/Tanner.png', __FILE__),
						 "Thaumaturge"		=> plugins_url('images/classes/Thaumaturge.png', __FILE__),
						 "Weaver"			=> plugins_url('images/classes/Weaver.png', __FILE__));

	switch ($ffxiv['Active'])
	{
		case 'Alchemist':
			$aicon = $icons_class['Alchemist'];
			break;
		case 'Archer':
			$aicon = $icons_class['Archer'];
			break;
		case 'Armorer':
			$aicon = $icons_class['Armorer'];
			break;
		case 'Blacksmith':
			$aicon = $icons_class['Blacksmith'];
			break;
		case 'Botanist':
			$aicon = $icons_class['Botanist'];
			break;
		case 'Carpenter':
			$aicon = $icons_class['Carpenter'];
			break;
		case 'Conjurer':
			$aicon = $icons_class['Conjurer'];
			break;
		case 'Fisher':
			$aicon = $icons_class['Fisher'];
			break;
		case 'Gladiator':
			$aicon = $icons_class['Gladiator'];
			break;
		case 'Goldsmith':
			$aicon = $icons_class['Goldsmith'];
			break;
		case 'Lancer':
			$aicon = $icons_class['Lancer'];
			break;
		case 'Marauder':
			$aicon = $icons_class['Marauder'];
			break;
		case 'Miner':
			$aicon = $icons_class['Miner'];
			break;
		case 'Pugilist':
			$aicon = $icons_class['Pugilist'];
			break;
		case 'Tanner':
			$aicon = $icons_class['Tanner'];
			break;
		case 'Thaumaturge':
			$aicon = $icons_class['Thaumaturge'];
			break;
		case 'Weaver':
			$aicon = $icons_class['Weaver'];
			break;
	}

	$icons_guard = array("Althyk"		=> plugins_url('images/guardians/althyk.png', __FILE__),
						 "Azeyma"		=> plugins_url('images/guardians/azeyma.png', __FILE__),
						 "Byregot"		=> plugins_url('images/guardians/byregot.png', __FILE__),
						 "Halone"		=> plugins_url('images/guardians/halone.png', __FILE__),
						 "Llymlaen"		=> plugins_url('images/guardians/llymlaen.png', __FILE__),
						 "Menphina"		=> plugins_url('images/guardians/menphina.png', __FILE__),
						 "Naldthal"		=> plugins_url('images/guardians/naldthal.png', __FILE__),
						 "Nophica"		=> plugins_url('images/guardians/nophica.png', __FILE__),
						 "Nymeia"		=> plugins_url('images/guardians/nymeia.png', __FILE__),
						 "Oschon"		=> plugins_url('images/guardians/oshon.png', __FILE__),
						 "Rhalgr"		=> plugins_url('images/guardians/rhalgr.png', __FILE__),
						 "Thaliak"		=> plugins_url('images/guardians/thaliak.png', __FILE__));

	switch ($ffxiv['Guardian'])
	{
		case 'Althyk, the Keeper':
			$gicon = $icons_guard['Althyk'];
			break;
		case 'Azeyma, the Warden':
			$gicon = $icons_guard['Azeyma'];
			break;
		case 'Byregot, the Builder':
			$gicon = $icons_guard['Byregot'];
			break;
		case 'Halone, the Fury':
			$gicon = $icons_guard['Halone'];
			break;
		case 'Llymlaen, the Navigator':
			$gicon = $icons_guard['Llymlaen'];
			break;
		case 'Menphina, the Lover':
			$gicon = $icons_guard['Menphina'];
			break;
		case 'Nald\'thal, the Traders':
			$gicon = $icons_guard['Naldthal'];
			break;
		case 'Nophica, the Matron':
			$gicon = $icons_guard['Nophica'];
			break;
		case 'Nymeia, the Spinner':
			$gicon = $icons_guard['Nymeia'];
			break;
		case 'Oschon, the Wanderer':
			$gicon = $icons_guard['Oschon'];
			break;
		case 'Rhalgr, the Destroyer':
			$gicon = $icons_guard['Rhalgr'];
			break;
		case 'Thaliak, the Scholar':
			$gicon = $icons_guard['Thaliak'];
			break;
	}

	$content = "<img src='".$ffxiv['CAvatar']."' width='25%' height='25%' align='left' /> <small style='font-size: 10px'>".$ffxiv['CName']." (".$ffxiv['CServer'].")</small><br />";
	$content .= "<small style='font-size: 10px'>".$ffxiv['Race']." of ".$ffxiv['Nation']."</small><br />";
	$content .= "<small style='font-size: 10px'>".$ffxiv['BDate']."</small><br />";
	$content .= "<img src='".$ffxiv['LsEmb']."' width='10%' height='10%' valign='top' /> <small style='font-size: 10px'>".$ffxiv['LsName']."</small>";
	$content .= "<img src='".$gicon."' width='10%' height='10%' title='".$ffxiv['Guardian']."' style='float: right' /><br /><img src='".$aicon."' width='10%' height='10%' title='".$ffxiv['Active']."' style='float:right' />";
	$content .= "<br /><hr style='border: 1px dashed' align='center' width='85%' />";

	$content .= "<table width='100%'>";

	foreach($classes as $class)
	{
		$content .= "<tr><td><img src='".$icon_class[$class]."' /></td></tr>";
	}

	$content .= "</table>";

	return $content;
}

function FFXIV_Render_Land()
{

}


?>