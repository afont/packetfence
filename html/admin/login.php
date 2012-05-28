<?php
/**
 * TODO short desc
 *
 * TODO long desc
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301,
 * USA.
 * 
 * @author      Olivier Bilodeau <obilodeau@inverse.ca>
 * @copyright   2008-2011 Inverse inc.
 * @license     http://opensource.org/licenses/gpl-2.0.php      GPL
 */
require_once 'common/logging.inc';

function get_group($user) {
  return 'admin';
}

# restricting usernames to avoid LDAP injection
function legal_username($input){
  global $logger;
  if(preg_match("/^[\@a-zA-Z0-9_\:\,\(\)\.]+$/", $input) && strlen($input) <= 60){
    return true; 
  } else {
    $logger->info("Illegal username provided: $input. Rejecting user.");
    return false;
  }
} 

# rejecting NULLs or empty strings because they end-up doing an anonymous LDAP bind
function legal_password($input){
  global $logger;
  if (isset($input) && !is_null($input) && !empty($input)) {
    return true;
  } else {
    $logger->info("Empty passwords are explicitly rejected");
    return false;
  }
}

// First we try to authenticate users through LDAP if LDAP config file is there
// if the LDAP config file is not defined or if the LDAP auth fails then we authenticate through the local file
# TODO: have a better integration of admin auth parameters in config files or admin interface
function validate_user($user,$pass,$hash='') {
    $result = false;

    # standard ldap auth mechanism
    $result = validate_user_ldap($user,$pass,$hash);

    if (!$result) {
        $result = validate_user_flat_file($user,$pass,$hash);
    }

    # alternative way to do ldap auth: if username exist in local config then validate against ldap
    # allows admins to better control who has access without needing to involve their AD teams
    # localuser+ldappass (with localpass fallback)
    #if (validate_user_present_in_flat_file($user)) {
    #  $result = validate_user_ldap($user,$pass,$hash);
    #
    #  if (!$result) {
    #    $result = validate_user_flat_file($user,$pass,$hash); 
    #  }
    #}

    return $result;
}

function validate_user_ldap($user,$pass,$hash='') {
  global $logger;
  include(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . "/conf/admin_ldap.conf");

  if ($hash != '') {
    return $hash;
  }
  if (!isset($ldap_host)) {
    $logger->info("LDAP Host is not set");
    return false;
  }

  if (isset($ldap_port)) {
    $ldap = ldap_connect($ldap_host, $ldap_port);
    if (!$ldap) {
      $logger->info("Could not connect to LDAP at $ldap_host:$ldap_port");
      return false;
    }
  } else {
    $ldap = ldap_connect($ldap_host);
    if (!$ldap) {
      $logger->info("Could not connect to LDAP at $ldap_host");
      return false;
    }
  }

  # We may have to set these 2 options
  #ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
  #ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

  $bind = ldap_bind($ldap, $ldap_bind_dn, $ldap_bind_pwd);
  if (!$bind) {
    $logger->info("Unable to bind to LDAP with bind_dn and bind_pwd");
    return false;
  }

  # Here we look only into one DN ($ldap_user_base)
  $filter="$ldap_user_key=$user";
  $result = ldap_search($ldap, $ldap_user_base, $filter, array("dn"));
  $info = ldap_get_entries($ldap, $result);
  if (!$result) {
    $logger->info("LDAP query failed, check your settings");
    return false;
  }

  # If we want to search in more than one DN (multiple DNs):
#  $dn[]=$ldap_user_base;
#  $dn[]=$ldap_user_base2;
#  $dn[]=$ldap_user_base3;
#
#  $id[] = $ldap;
#  $id[] = $ldap;
#  $id[] = $ldap;
#
#  $result = ldap_search($id, $dn, $filter, array("dn"));
#  $search = false;
#
#  foreach ($result as $value) {
#    if (ldap_count_entries($ldap, $value) > 0) {
#      $search = $value;
#      break;
#    }
#  }
#
#  if ($search) {
#    $info = ldap_get_entries($ldap, $search);
#  } else {
#    return false;
#  }

  if ($info["count"] != 1) {
    $logger->info("User not found or more than one user matched");
    return false;
  }
  $user_dn = $info[0]["dn"];
  $bind = ldap_bind($ldap, $user_dn, $pass);
  if (!$bind) {
    $logger->info("Could not bind as a user. Password is not good or the user doesn't have the rights to bind");
    return false;
  }

  # group membership validation
  if (isset($ldap_group_member_key) && isset($ldap_group_dn)) {
    # if both the validation fails we fail
    if (!_validate_group_memberOf($user_dn, $ldap, $ldap_user_base, $ldap_user_key, $user, $ldap_group_member_key, $ldap_group_dn) 
        && !_validate_group_member($user_dn, $ldap, $ldap_group_dn, $ldap_group_member_key)
      ) {
        $logger->info("User is not a member of the required group");
        return false;
    } else {
      $logger->info("User is a member of the required group");
    }
  }

  return md5($pass);
}

/**
 Validate that the user is member of a given group. 
 Technique: Look under the user for an attribute that represent group membership.
 */
function _validate_group_memberOf($user_dn, $ldap, $ldap_user_base, $ldap_user_key, $user, $ldap_group_member_key, $ldap_group_dn) {
  global $logger;

  # group filter
  $filter="(&($ldap_user_key=$user)($ldap_group_member_key=$ldap_group_dn))";
  $result = ldap_search($ldap, $ldap_user_base, $filter, array("dn"));
  $info = ldap_get_entries($ldap, $result);
  if (!$result) {
    $logger->info("technique memberOf: LDAP group query failed, check your settings");
    return false;
  }

  if ($info["count"] != 1) {
    $logger->info("technique memberOf: User not found or more than one user matched when validating group membership");
    return false;
  }

  if ($user_dn != $info[0]["dn"]) {
    $logger->info("technique memberOf: User DN didn't match the user dn when checking for group membership");
    return false;
  }
  return true;
}

/**
 Validate that the user is member of a given group. 
 Technique: Look under the group for an attribute that represent that the user is a member.
 */
function _validate_group_member($user_dn, $ldap, $ldap_group_dn, $ldap_group_member_key) {
  global $logger;

  $filter = "$ldap_group_member_key=$user_dn";
  # get the group directly with it's DN as the base
  $result = ldap_search($ldap, $ldap_group_dn, $filter, array("dn"));
  $info = ldap_get_entries($ldap, $result);
  if (!$result) {
    $logger->info("technique group_member: LDAP group query failed, check your settings. Correct group_dn?");
    return false;
  }

  if ($info["count"] != 1) {
    $logger->info("technique group_member: Member not found in group.");
    return false;
  }
  return true;
}

function validate_user_present_in_flat_file($user){
  $file = file(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/conf/admin.conf');
  foreach($file as $line){
    $line = rtrim($line);
    $info = explode(":", $line);
    if ($user == $info[0]) {
      return true;
    }
  }
  return false;
}

function validate_user_flat_file($user, $pass, $hash = ''){
  $file = file(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/conf/admin.conf');
  foreach($file as $line){
    $line = rtrim($line);
    $info = explode(":", $line);
    if ($user == $info[0]) {
      if(!$hash){
        $hash = crypt($pass, $info[1]);
      }
      if($info[1] == $hash){
        return $hash;
      }
    }
  }
  return false;
}

/*
 * If you decide to use groups in the conf/admin.conf file, 
 * use this function to validate the hash instead of the normal one
 */
function validate_user_group($user, $pass, $hash = ''){
  $file = file(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/conf/admin.conf');
  foreach($file as $line){
    $line = rtrim($line);
    $info = explode(":", $line);
    if ($user == $info[0]) {
      if(!$hash){
        $hash = crypt($pass, $info[2]);
      }
      if($info[2] == $hash){
        return array($hash, $info[1]);
      }
    }
  }
  return false;
}

$abs_url="https://$_SERVER[HTTP_HOST]";

if(!function_exists('session_start')){
  die("<div id='error'>Error: Your version of PHP does not have session support.  Session support is needed for this application</div>");
}

if (! is_writeable(session_save_path())) {
          die("<div id='error'>Error: Your PHP session.save_path is not writable.</div>");
}

session_start(); 

// To test for cookie enabled browsers
setcookie ('test', 'test');

if(isset($_GET['logout']) || isset($_GET['ip_mismatch'])){
  session_unset();
  session_destroy();
}

else {
  if(isset($_SESSION['user']) && isset($_SESSION['passw'])) {
    if(validate_user($_SESSION['user'], '', $_SESSION['passw'])){
      if(is_readable(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . "/conf/users/" . $_SESSION['user'])){
        $_SESSION['ui_prefs']=unserialize(file_get_contents(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . "/conf/users/" . $_SESSION['user'])); 
        $homepage = $_SESSION['ui_prefs']['homepage'];
      }
      if(!$homepage){
        $homepage = 'status/dashboard.php';
      }
      $_GET['p'] ? header("Location: $abs_url$_GET[p]") : header("Location: $abs_url/$homepage");
      exit;
    }
    else{
      header("Location: $abs_url/login.php");
      exit;
    }
  }

  if (isset($_POST['username'], $_POST['password'])) {

    $hash = validate_user($_POST['username'], $_POST['password']);

    # verifies if a username is considered 'legal'
    if (!legal_username($_POST['username'])) {
      $failed = true;

    # verifies if a password is considered 'legal'. This does not check the actual password!
    } elseif (!legal_password($_POST['password'])) {
      $failed = true;

    # hash invalid or cookies invalid
    } elseif (!$hash || !isset($_COOKIE['test'])) {
      $failed = true;

    # successful auth
    } else {
      $_SESSION['user'] = $_POST['username'];
      $_SESSION['group'] = get_group($_SESSION['user']);
      $_SESSION['passw'] = $hash;
      $_SESSION['last_active'] = time();
      $_SESSION['ip_addr'] = $_SERVER['REMOTE_ADDR'];
      if(is_readable(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . "/conf/users/" . $_SESSION['user'])){
        $_SESSION['ui_prefs']=unserialize(file_get_contents(dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . "/conf/users/" . $_SESSION['user'])); 
        $homepage = $_SESSION['ui_prefs']['homepage'];
      }
      else{
        $homepage = 'status/dashboard.php';
      }
      isset($_GET['p']) ? header("Location: $abs_url$_GET[p]") : header("Location: $abs_url/$homepage");
      exit;
    }
  }
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">

<html>

<head>
  <title>PF::Login</title>
  <base href="<?=$abs_url?>/">
  <link rel="shortcut icon" href="/favicon.ico">
  <link rel="stylesheet" href="style.css" type="text/css">  
</head>

<body onload="document.login.<?=(isset($failed) ? "password" : "username")?>.focus();">

<div id="container">

<table id="main" style="width: 100%;" cellpadding="0" cellspacing="0">
  <tbody><tr colspan="2">
    <td valign="top">
      <a href="index.php"><img src="/common/packetfence.png" alt="[ Packetfence ]" align="right" border="0" height="60" width="193"></a>
    </td>
  </tr>
  <tr colspan="2">
    <td valign="bottom" width="100%">
      <!-- Begin TopNav -->
      <div class="topnav">
        <ul>
          <li class="active"><a href="#">Login</a></li>
        </ul>
      </div>
      <!-- End TopNav -->

    </td>
  </tr>
  <tr>
    <td class="subnav" colspan="2">
      <!-- Begin SubNav -->
      <div class="subnav">
        <ul id="navlist">
         </ul>
       </div>
       <!-- End SubNav -->

    </td>
  </tr>
  <tr>
    <td class="content" colspan="2" height="100%" valign="top">

<!-- Begin Content -->
<div id="content">

<div id="login" align=center>
  <form method="post" name="login" action="<? print "$_SERVER[PHP_SELF]?p=" . (array_key_exists('p', $_GET) ? htmlentities($_GET['p']) :'');?>">
  <table>
    <tbody>
    <tr>
      <td colspan=2 align=center>
      <?
        if(isset($_GET['ip_mismatch'])){
          print "<div id='error'>Error!  Your IP address has changed since you logged on.  Please log in again.</div>"; 
        }

        if(isset($failed)){
          if(!isset($_COOKIE['test'])){
            print "<div id='error'>Error! Your browser does not have cookies enabled.  Cookies are required for using the PacketFence GUI.</div>";
          }
          else{
            echo "Invalid Username/Password";
          }
        }
        if (isset($_GET['logout'])){
          echo "Logged Out<br>";
        }
        else if(isset($_GET['expired'])){
          echo "Your session has expired";
        }
      ?>
      </td>
    </tr>
    <tr valign=bottom>
      <td align=right>Username</td>
      <td align=left><input type="text" name="username" maxlength="20" value="<?=(isset($_POST['username']) ? htmlentities($_POST['username']) : "")?>"></td>
    </tr>
    <tr>
      <td align=right>Password</td>
      <td align=left><input type="password" maxlength="20" name="password"></td>
    </tr>
    <tr height=30% valign=top>
      <td colspan="2" align=right><input type="submit" value="Login"></td>
    </tr>
  </table>
  </form>
</div>


      <!-- End Content -->
      </div>
    </td>
  </tr>
  <tr>
    <td colspan="10">
      <div id="footer">
      </div>
    </td>
  </tr>
  <tr>
    <td colspan="2" align="right">&nbsp;</td>
  </tr>
</tbody></table>
</div>

</body></html>

