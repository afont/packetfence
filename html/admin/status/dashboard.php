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
 * @author      Dominik Gehl <dgehl@inverse.ca>
 * @copyright   2008-2010 Inverse inc.
 * @license     http://opensource.org/licenses/gpl-2.0.php      GPL
 */

  include('sajax-dashboard.php');

  require_once('../common.php');
  require_once('grapher.php');

  $current_top="status";
  $current_sub="dashboard";

  include_once('../header.php');

  if($_POST['graphs'] && $_POST['nuggets']){
    $_SESSION['ui_prefs']['dashboard']['graphs'] = array();
    foreach($_POST['graphs'] as $key => $val){
      $parts=explode("-", $val);
      if($parts[0] && $parts[1]){
        $_SESSION['ui_prefs']['dashboard']['graphs'][]=array('type' => $parts[0], 'span' => $parts[1]);
      }
    }

    unset($_SESSION['ui_prefs']['dashboard']['nuggets']);
    foreach($_POST['nuggets'] as $nugget){
      if($nugget){
        $_SESSION['ui_prefs']['dashboard']['nuggets'][]=array('cmd' => $nugget);
      }
    }
    save_prefs_to_file();
  }

  $default_graphs[0] = array('type' => 'nodes', 'span' => 'month');
  $default_graphs[1] = array('type' => 'unregistered', 'span' => 'month');

  $default_nuggets[0] = array('cmd' => 'vitals');
  $default_nuggets[1] = array('cmd' => 'recent_violations');
  $default_nuggets[2] = array('cmd' => 'recent_registrations');

  if(!isset($_SESSION['ui_prefs']['dashboard']['graphs'])){
    $_SESSION['ui_prefs']['dashboard']['graphs'] = $default_graphs;
  }

  if(!isset($_SESSION['ui_prefs']['dashboard']['nuggets'])){
    $_SESSION['ui_prefs']['dashboard']['nuggets'] = $default_nuggets;
  }

  $graphs  = $_SESSION['ui_prefs']['dashboard']['graphs'];
  $nuggets =  $_SESSION['ui_prefs']['dashboard']['nuggets'];

  ?>

  <div id=pf_status align=center valign=middle>
  <span class='title'>PacketFence Status</span><br>
  <span class='subtitle'><?=$_SERVER['SERVER_NAME']?></span>
  <table class=main cellspacing=10 cellpadding=0>
    <tr valign=top>
      <td>
      <?
        if($_GET['customize']){
          print "<form method='post' action='$current_top/$current_sub.php'>";
          # add extra rows up to a maximum number of 9
          for ($emptyNuggets =  count($nuggets); $emptyNuggets <=9; $emptyNuggets++) {
            $nuggets[]=array('cmd'=>'');
          }
        }

        ## Nuggets ##
        foreach($nuggets as $nugget){
          if($nugget['cmd'] == 'vitals'){
            print "<table class='stats'>";

            print "<tr class='header'><td colspan='10' class='header'>";
            $_GET['customize'] ? print nugget_select($a++, $nugget[cmd]) : print "System Vitals";
            print "</td></tr>";

            print "<tr class='odd'>
            <td class='left'></td>
            <td class='vitals_desc'>Disk Usage</td>
            <td class='vitals_data'>
                <div id='vital_data'>
                  <div id='percent_bar'>
                    <div id='disk_usage'></div>
                      <span id='disk_percent'></span>
                    </div>
                  </div>
                </div>
            </td>
            <td class='right'></td></tr>";

            print "<tr class='even'>
            <td class='left'></td>
            <td class='vitals_desc'>Memory Usage</td>
            <td class='vitals_data'>
                <div id='vital_data'>
                  <div id='percent_bar'>
                    <div id='mem_usage'></div>
                      <span id='mem_percent'></span>
                    </div>
                  </div>
                </div>
            </td>
            <td class='right'></td></tr>";

            print "<tr class='odd'>
            <td class='left' style='width:5px;'></td>
            <td class='vitals_desc'>SQL Queries</td>
            <td class='vitals_data'>
              <div>
                <div id='vital_data'>
                  <span id='sql_queries' style='display:inline-block; margin-left:5px; vertical-align:bottom;'></span>
                </div>
              </div>
            </td>
            <td class='right'></td></tr>";

            print "<tr class='even'>
            <td class='left' style='width:5px;'></td>
            <td class='vitals_desc'>CPU Load</td>
            <td class='vitals_data'>
                <div id='vital_data'>
                  1 min: <span id='load_1' style='display:inline-block; margin-left:5px; vertical-align:bottom;'></span><br>
                  5 min: <span id='load_5' style='display:inline-block; margin-left:5px; vertical-align:bottom;'></span><br>
                  15 min: <span id='load_15' style='display:inline-block; margin-left:5px; vertical-align:bottom; a'></span>
                </div>
            </td>
            <td class='right'></td></tr>";

            print "</table>";
          }
          else{ 
            print "<table class='stats'>";
            $nugget['name'] = pretty_header('status-nuggets', $nugget[cmd]);

            print "<tr class='header'><td colspan='10' class='header'>";
            $_GET['customize'] ? print nugget_select($a++, $nugget[cmd]) : print $nugget[name];
            print "</td></tr>";

            $i=0;
            if($nugget[cmd] != ''){
              $pfcmd = PFCMD("ui dashboard $nugget[cmd]");
              array_shift($pfcmd);
              foreach($pfcmd as $data){
                $parts = explode('|', $data);
                $endcap = array_pop($parts);
                $i++ % 2 == 0 ? $class = 'odd' : $class = 'even';
                print "<tr class='$class'><td class='left'>".implode("</td><td>", $parts)."</td>";
                print "<td class='right'>$endcap</td></tr>";
              }
            }
            print "</table>";
          }
        }
      ?>
      </td>
      <td align=center>
        <?
           ## Graphs ##
           if($_GET['customize']){
          # 9 rows
             for($i=0; $i<9; $i++){
               if($graphs[$i]){
                 jsgraph(array('type' => $graphs[$i][type], 'span' => $graphs[$i][span], 'size' => 'small'));
               }
               else{
                 print "<div style='width:450px;text-align:center;padding-top:20px;padding-bottom:20px;background:#dddddd;border:1px solid black;margin-bottom:10px'>";
               }
               print graph_select($i, $graphs[$i]);
               print "</div>";
             }

             print "<div style='text-align:right;border:1px solid black;background:#FFC366;'><input type='submit'></div>";
             print "</form>";

           }
           else{
             foreach($graphs as $graph){
               jsgraph(array('type' => $graph[type], 'span' => $graph[span], 'size' => 'small'));
             }
           }
        ?>
      </td>
    </tr>
  <? if(!$_GET['customize']){ 
       print "<tr><td colspan=2 align=right>";
       print "<div style='text-align:right;'><a class='no_hover' href='$current_top/$current_sub.php?customize=true'><img src='../images/customize.png' alt='Customize This Page' title='Customize This Page'><br><font size=1>Customize this page</font></a></div></td>";
       print "</tr>";
  } ?>
  </table>
  </div>

  <?

  include_once('../footer.php');

  function graph_select($i, $default){
    $meta = meta('status-graphs');
    $spans = array('day' => 'Daily', 'month' => 'Monthly', 'year' => 'Yearly');
    $select = "<select name='graphs[$i]'>";
    $select .= "<option value=''>Select a Graph Type";
    foreach($meta as $graph){
      foreach($spans as $key => $val){
        if (($graph[0] != 'traps') && ($graph[0] != 'ifoctetshistoryswitch') && ($graph[0] != 'ifoctetshistorymac') && ($graph[0] != 'ifoctetshistoryuser')) {
              ("$default[type]-$default[span]" == "$graph[0]-$key") ? $selected='SELECTED' : $selected='';
          $select.="<option value='$graph[0]-$key' $selected>$graph[1] ($val)";
        }
      }
    }
    $select.="</select>";
    return $select;
  }

  function nugget_select($i, $default){
    $meta = meta('status-nuggets');
    $meta[] = array('vitals', 'System Vitals');
    $select = "<select name='nuggets[$i]'>";
    $select .= "<option value=''>Select a Type";
    foreach($meta as $nugget){
        ($default == $nugget[0]) ? $selected='SELECTED' : $selected='';
        $select.="<option value='$nugget[0]' $selected>$nugget[1]";
    }
    $select.="</select>";
    return $select;
  }



?>


