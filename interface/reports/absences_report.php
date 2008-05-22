<?php 
 // This module is for team sports use and reports on absences by
 // injury type (diagnosis) for a given time period.

require_once("../globals.php");
require_once("../../library/patient.inc");
require_once("../../library/acl.inc");
require_once("../../custom/code_types.inc.php");

// Might want something different here.
//
// if (! acl_check('acct', 'rep')) die("Unauthorized access.");

$from_date = fixDate($_POST['form_from_date']);
$to_date   = fixDate($_POST['form_to_date'], date('Y-m-d'));
$form_by   = $_POST['form_by'];

// Look up descriptions for one or more billing codes.  This should
// probably be moved to an "include" file somewhere.
//
function lookup_code_descriptions($codes) {
  global $code_types;
  $code_text = '';
  if (!empty($codes)) {
    $relcodes = explode(';', $codes);
    foreach ($relcodes as $codestring) {
      if ($codestring === '') continue;
      list($codetype, $code) = explode(':', $codestring);
      $wheretype = "";
      if (empty($code)) {
        $code = $codetype;
      } else {
        $wheretype = "code_type = '" . $code_types[$codetype]['id'] . "' AND ";
      }
      $crow = sqlQuery("SELECT code_text FROM codes WHERE " .
        "$wheretype code = '$code' ORDER BY id LIMIT 1");
      if (!empty($crow['code_text'])) {
        if ($code_text) $code_text .= '; ';
        $code_text .= $crow['code_text'];
      }
    }
  }
  return $code_text;
}
?>
<html>
<head>
<?php html_header_show();?>
<title><?php xl('Absences by Diagnosis','e'); ?></title>
<script type="text/javascript" src="../../library/overlib_mini.js"></script>
<script type="text/javascript" src="../../library/textformat.js"></script>
<script language="JavaScript">
 var mypcc = '<?php  echo $GLOBALS['phone_country_code'] ?>';
</script>
<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
</head>

<body class="body_top">

<!-- Required for the popup date selectors -->
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>

<center>

<h2><?php  xl('Days and Games Missed','e'); ?></h2>

<form name='theform' method='post' action='absences_report.php'>

<table border='0' cellpadding='3'>

 <tr>
  <td>
  <?php  xl('By:','e'); ?>
   <input type='radio' name='form_by' value='d'
    <?php  echo ($form_by == 'p') ? '' : 'checked' ?> /><?php  xl('Diagnosis','e'); ?>&nbsp;
   <input type='radio' name='form_by' value='p'
    <?php  echo ($form_by == 'p') ? 'checked' : '' ?> /><?php  xl('Player','e'); ?> &nbsp;
   <?php  xl('From:','e'); ?>
   <input type='text' name='form_from_date' size='10' value='<?php  echo $from_date ?>'
    onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
   <img src='../pic/show_calendar.gif' align='absbottom' width='24' height='22'
    id='img_from_date' border='0' alt='[?]' style='cursor:pointer'
    title='<?php xl('Click here to choose a date','e'); ?>'>
   &nbsp;<?php  xl('To:','e'); ?>
   <input type='text' name='form_to_date' size='10' value='<?php  echo $to_date ?>'
    onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
   <img src='../pic/show_calendar.gif' align='absbottom' width='24' height='22'
    id='img_to_date' border='0' alt='[?]' style='cursor:pointer'
    title='<?php xl('Click here to choose a date','e'); ?>'>
   &nbsp;
   <input type='submit' name='form_refresh' value='<?php  xl('Refresh','e'); ?>'>
  </td>
 </tr>

 <tr>
  <td height="1">
  </td>
 </tr>

</table>

<table border='0' cellpadding='1' cellspacing='2' width='98%'>

 <tr bgcolor="#dddddd">
<?php   if ($form_by == 'p') { ?>
  <td class="dehead">
   <?php  xl('Name','e'); ?>
  </td>
<?php  } else { ?>
  <td class="dehead">
   <?php  xl('Code','e'); ?>
  </td>
  <td class="dehead">
   <?php  xl('Description','e'); ?>
  </td>
<?php  } ?>
  <td class='dehead' align='right'>
   <?php  xl('Issues','e'); ?>
  </td>
  <td class='dehead' align='right'>
   <?php  xl('Days','e'); ?>
  </td>
  <td class='dehead' align='right'>
   <?php  xl('Games','e'); ?>
  </td>
 </tr>
<?php 
 if ($_POST['form_refresh']) {
  $form_doctor = $_POST['form_doctor'];
  $from_date = fixDate($_POST['form_from_date']);
  $to_date   = fixDate($_POST['form_to_date'], date('Y-m-d'));

  if ($form_by == 'p') {
   $query = "SELECT patient_data.lname, patient_data.fname, patient_data.mname, " .
    "count(*) AS count, " .
    "SUM(lists.extrainfo) AS gmissed, " .
    "SUM(TO_DAYS(LEAST(IFNULL(lists.enddate,CURRENT_DATE),'$to_date')) - TO_DAYS(GREATEST(lists.begdate,'$from_date'))) AS dmissed " .
    "FROM lists, patient_data WHERE " .
    "(lists.enddate IS NULL OR lists.enddate >= '$from_date') AND lists.begdate <= '$to_date' AND " .
    "patient_data.pid = lists.pid " .
    "GROUP BY lname, fname, mname";
  }
  else {
   /******************************************************************
   $query = "SELECT lists.diagnosis, codes.code_text, count(*) AS count, " .
    "SUM(lists.extrainfo) AS gmissed, " .
    "SUM(TO_DAYS(LEAST(IFNULL(lists.enddate,CURRENT_DATE),'$to_date')) - TO_DAYS(GREATEST(lists.begdate,'$from_date'))) AS dmissed " .
    "FROM lists " .
    "LEFT OUTER JOIN codes " .
    "ON codes.code = lists.diagnosis AND " .
    "(codes.code_type = 2 OR codes.code_type = 4 OR codes.code_type = 5 OR codes.code_type = 8) " .
    "WHERE " .
    "(lists.enddate IS NULL OR lists.enddate >= '$from_date') AND lists.begdate <= '$to_date' " .
    "GROUP BY lists.diagnosis";
   ******************************************************************/
   $query = "SELECT lists.diagnosis, count(*) AS count, " .
    "SUM(lists.extrainfo) AS gmissed, " .
    "SUM(TO_DAYS(LEAST(IFNULL(lists.enddate,CURRENT_DATE),'$to_date')) - TO_DAYS(GREATEST(lists.begdate,'$from_date'))) AS dmissed " .
    "FROM lists WHERE " .
    "(lists.enddate IS NULL OR lists.enddate >= '$from_date') AND lists.begdate <= '$to_date' " .
    "GROUP BY lists.diagnosis";
  }

  // echo "<!-- $query -->\n"; // debugging

  $res = sqlStatement($query);

  while ($row = sqlFetchArray($res)) {
    $code_text = lookup_code_descriptions($row['diagnosis']);
?>

 <tr>
<?php   if ($form_by == 'p') { ?>
  <td class='detail'>
   <?php  echo $row['lname'] . ', ' . $row['fname'] . ' ' . $row['mname'] ?>
  </td>
<?php  } else { ?>
  <td class='detail'>
   <?php  echo $row['diagnosis'] ?>
  </td>
  <td class='detail'>
   <?php  echo $code_text ?>
  </td>
<?php  } ?>
  <td class='detail' align='right'>
   <?php  echo $row['count'] ?>
  </td>
  <td class='detail' align='right'>
   <?php  echo $row['dmissed'] ?>
  </td>
  <td class='detail' align='right'>
   <?php  echo $row['gmissed'] ?>
  </td>
 </tr>
<?php 
  }
 }
?>

</table>
</form>
</center>
</body>
<!-- stuff for the popup calendar -->
<style type="text/css">@import url(../../library/dynarch_calendar.css);</style>
<script type="text/javascript" src="../../library/dynarch_calendar.js"></script>
<script type="text/javascript" src="../../library/dynarch_calendar_en.js"></script>
<script type="text/javascript" src="../../library/dynarch_calendar_setup.js"></script>
<script language="Javascript">
 Calendar.setup({inputField:"form_from_date", ifFormat:"%Y-%m-%d", button:"img_from_date"});
 Calendar.setup({inputField:"form_to_date", ifFormat:"%Y-%m-%d", button:"img_to_date"});
</script>
</html>
