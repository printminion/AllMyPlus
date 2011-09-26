<?php
  $base_url = "{base-url}";
  $user_ip = $_SERVER["REMOTE_ADDR"];
  if(!$user_ip||$user_ip=="") {
    $user_ip = $_SERVER["SERVER_ADDR"];
  }  
  require_once "{Path to G-API-client}/apiClient.php";
  require_once "{Path to G-API-client}/contrib/apiPlusService.php";
  session_start();
  $client = new apiClient();
  $client->setApplicationName("All my +");
  $client->setClientId("{Client ID}");
  $client->setClientSecret("{Client Secret}");
  $client->setRedirectUri($base_url."index.php"); 
  $client->setDeveloperKey("{Developer Key}");
  $client->setScopes(array("https://www.googleapis.com/auth/plus.me"));
  $plus = new apiPlusService($client);
  
  if (isset($_REQUEST["logout"])) {
    unset($_SESSION["access_token"]);
    header("Location: ".$base_url);
  }

  $timezone = 0;
  if (isset($_REQUEST["timezone"])) {
    $timezone = $_REQUEST["timezone"];
    if(!is_numeric($timezone)) $timezone = 0;
  }
  
  if (isset($_GET["code"])) {
    $client->authenticate();
    $_SESSION["access_token"] = $client->getAccessToken();
    $me = $plus->people->get("me");
    header("Location: ".$base_url."user/".$me["id"]);
  }
  if (isset($_GET["error"])) {
    unset($_SESSION["access_token"]);
    header("Location: ".$base_url);
  }
  
  if($_POST["userid"]&&$_POST["userid"]!="") {
    header("Location: ".$base_url."user/".$_POST["userid"]);
  }

  $request = $_SERVER["REQUEST_URI"];
  $path = $_SERVER["PHP_SELF"];
  $p = strrpos($path,"/");
  if(!($p === false)) {
    $request = substr($request,$p+1);
    $path = substr($path,0,$p);
  }
  $p = strrpos($request,"?");
  if(!($p === false)) {
    $q_user = substr($request,5,$p-5);
  } else {
    $q_user = substr($request,5);
  }
  
  if (isset($_SESSION['access_token'])) {
    $client->setAccessToken($_SESSION['access_token']);
  }

  if ($client->getAccessToken()) {
    $me = $plus->people->get('me');
    $_SESSION['access_token'] = $client->getAccessToken();
    if($q_user=="") {
      header("Location: ".$base_url."user/".$me["id"]);
    }
    $login_name = $me["displayName"];
  } else {
    $authUrl = $client->createAuthUrl();
    $authUrl = str_replace("&amp;","&",$authUrl);
    $authUrl = str_replace("&","&amp;",$authUrl);
  }
  
  $num_activities = 0;
  $activities = array();
  $str_author_id = "";
  $str_author_name = "";
  $str_author_url = "";
  $str_author_pic = "";
  
  function set_author($a) {
    global $str_author_id, $str_author_name, $str_author_url, $str_author_pic;
    $str_author_id = $a["actor"]["id"];
    $str_author_name = $a["actor"]["displayName"];
    $str_author_url = $a["actor"]["url"];
    if(isset($a["actor"]["image"])){
      if(isset($a["actor"]["image"]["url"])){
        $str_author_pic = $a["actor"]["image"]["url"];
      }
    }
  }
  
  function handle_activity($a) {
    global $num_activities, $activities, $str_author_id;
    $activities[$num_activities] = $a;
    $num_activities++;
    if($str_author_id=="") {
      set_author($a);
    }
  }
  
  function empty_string($l) {
    $str = "";
    for($i=0;$i<$l;$i++) $str = $str." ";
    return $str;
  }
  
  function recalc_date($d,$tz) {
    $h = 0;
    $m = 0;
    if($tz==0) {
      $h = 0;
      $m = 0;
    } else {
      if($tz<0) {
        $h = ceil($tz);
        $m = round(($tz-$h)*60,0);
      } else {
        $h = floor($tz);
        $m = round(($tz-$h)*60,0);
      }
    }
    if($h==0&&$m==0) {
      return $d;
    } else {
      $date = new DateTime($d);
      if($h>0) $date->add(new DateInterval("PT".$h."H"));
      if($h<0) $date->sub(new DateInterval("PT".abs($h)."H"));
      if($m>0) $date->add(new DateInterval("PT".$m."M"));
      if($m<0) $date->sub(new DateInterval("PT".abs($m)."M"));   
      return $date->format("Y-m-d H:i:s");
    }
  }
  
  function print_activity($a,$ws) {
    global $timezone;
    $post_published = recalc_date(substr($a["published"],0,10)." ".substr($a["published"],11,8),$timezone);
    $post_updated = recalc_date(substr($a["updated"],0,10)." ".substr($a["updated"],11,8),$timezone);
    $post_link = $a["url"];
    $chk_reshares = false;
    
    printf("%s<p class=\"smallr\"><a href=\"%s\">%s</a>",empty_string($ws),$post_link,$post_published);
    if($post_published!=$post_updated) {
      printf(" (updated %s)",$post_updated);
    }
    printf("</p>\n");

    if(isset($a["object"]["actor"])) {
      $chk_reshare = true;
      if(isset($a["annotation"])) {
        if($a["annotation"]!="") {
          $annotation = preg_replace("/ oid=\".*?\"/","",$a["annotation"]);
          printf("%s%s<br>\n",empty_string($ws),$annotation);
        }
      }
      printf("%s<p class=\"smalll\">Reshared <a href=\"%s\">post</a> by <a href=\"%s\">%s</a></p>\n",empty_string($ws),$a["object"]["url"],$a["object"]["actor"]["url"],$a["object"]["actor"]["displayName"]);
      printf("%s<table><tr>\n",empty_string($ws));
      
      $str_reshare_pic = "";
      if(isset($a["object"]["actor"]["image"])) {
        if(isset($a["object"]["actor"]["image"]["url"])) {
          $str_reshare_pic = $a["object"]["actor"]["image"]["url"];
        }
      }
      if($str_reshare_pic=="") $str_reshare_pic = $base_url."noimage.png";
      printf("%s  <td style=\"border: 1px solid black\"><a href=\"%s\"><img src=\"%s\" style=\"max-width:100px;max-height:100px\" alt=\"%s\"></a></td>\n",empty_string($ws),$a["object"]["actor"]["url"],$str_reshare_pic,$a["object"]["actor"]["displayName"]);
      printf("%s  <td style=\"border: 1px solid black\">\n",empty_string($ws));
      $ws = $ws + 4;
    }
    $content = $a["object"]["content"];
    $content = preg_replace("/ oid=\".*?\"/","",$content);
    printf("%s%s<br>\n",empty_string($ws),$content);
    $chk_pic = false;

    if(isset($a["object"]["attachments"])) {
      foreach($a["object"]["attachments"] as $att) {   
        $att_link = "";
        $att_preview = "";
        $att_title = "";
        if(isset($att["url"])) $att_link = $att["url"];
        if(isset($att["image"])) $att_preview = $att["image"]["url"];
        if(isset($att["displayName"])) $att_title = $att["displayName"];
        if($att_link=="") {
          if(isset($att["fullImage"])) $att_link = $att["fullImage"]["url"];
        }
        if($att_title==""&&$att_preview=="") $att_title = $att_link;
        if($att_link!="") {
          $att_link = str_replace("&amp;","&",$att_link);
          $att_link = str_replace("&","&amp;",$att_link);
          if(!($att_preview!=""&&$chk_pic==true)) {
            printf("%s<br><br>\n",empty_string($ws));
          }
          printf("%s<a href=\"%s\">",empty_string($ws),$att_link);
          if($att_preview!="") {
            $chk_pic = true;
            $att_preview = str_replace("&amp;","&",$att_preview);
            $att_preview = str_replace("&","&amp;",$att_preview);
            $att_preview = str_replace("http://images0-focus-opensocial.googleusercontent.com","https://images0-focus-opensocial.googleusercontent.com",$att_preview);
            printf("<img src=\"%s\" alt=\"%s\" style=\"border:1px solid black; max-width:800px;\">",$att_preview,(($att_title!="")?$att_title:"preview"));
          } else {
            printf("%s",$att_title);
          }
          printf("</a>\n");
        }
      }
    }
    
    if($chk_reshare==true) {
      $ws = $ws - 4;
      printf("%s  </td>\n",empty_string($ws));
      printf("%s</tr></table>\n",empty_string($ws));
    }
  }

  function print_photos($a,$ws) {
    if(isset($a["object"]["attachments"])) {
      foreach($a["object"]["attachments"] as $att) {
        if($att["objectType"]=="photo") {
          $att_link = "";
          $att_preview = "";
          $att_title = "";
          if(isset($att["url"])) $att_link = $att["url"];
          if(isset($att["image"])) $att_preview = $att["image"]["url"];
          if(isset($att["displayName"])) $att_title = $att["displayName"];
          if($att_link=="") {
            if(isset($att["fullImage"])) $att_link = $att["fullImage"]["url"];
          }
          if($att_title==""&&$att_preview=="") $att_title = $att_link;
          if($att_preview!="") {
            $att_link = str_replace("&amp;","&",$att_link);
            $att_link = str_replace("&","&amp;",$att_link);
            $att_preview = str_replace("&amp;","&",$att_preview);
            $att_preview = str_replace("&","&amp;",$att_preview);
            $att_preview = str_replace("http://images0-focus-opensocial.googleusercontent.com","https://images0-focus-opensocial.googleusercontent.com",$att_preview);
            printf("%s<a href=\"%s\">",empty_string($ws),$att_link);
            printf("<img src=\"%s\" alt=\"%s\" style=\"border:1px solid black; max-height:100px; max-width:900px;\">",$att_preview,(($att_title!="")?$att_title:"preview"));
            printf("</a>\n");
          }
        }
      }
    }    
  }  
  
  $chk_error = false;
  $str_errors = "";
  if($q_user!="") {
    $chk_more = true;
    $optParams = array("maxResults" => 85, "userIp" => $user_ip);
    while($chk_more) {
      try {
        $activity_list = $plus->activities->listActivities($q_user, "public", $optParams);
        if(isset($activity_list["items"])) {
          foreach($activity_list["items"] as $activity) { 
            handle_activity($activity);
          }
        }
        $errors = 0;
        if(isset($activity_list["nextPageToken"])) {
          $optParams = array("maxResults" => 85, "userIp" => $user_ip, "pageToken" => $activity_list["nextPageToken"]);    
        } else {
          $chk_more = false;
        }
        unset($activity_list);
      } catch (Exception $e) {
        $errors++;
        if($errors>3) {
          $str_errors = $str_errors.$e->getMessage()."<br>";
          $chk_more = false;
          $chk_error = true;
        }
      }
    }
  }
?>
<!DOCTYPE html>
<html itemscope itemtype="http://schema.org/Person">
<head>
  <meta charset="UTF-8">
<?php
  if($str_author_name=="") {
    printf("  <title>All my +</title>\n");
    printf("  <meta itemprop=\"name\" content=\"All my +\">\n");
    printf("  <meta itemprop=\"description\" content=\"A quick overview and statistics of your public g+ activities.\">\n");
  } else {
    printf("  <title>All my + are belong to %s</title>\n",$str_author_name);
    printf("  <meta itemprop=\"name\" content=\"All my + data for %s\">\n",$str_author_name);
    printf("  <meta itemprop=\"description\" content=\"A quick overview and statistics of the g+ activities of %s.\">\n",$str_author_name);
    printf("  <meta itemprop=\"image\" content=\"%s\">\n",$str_author_pic);
  }
?>
  <link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>style.css"> 
  <link rel="shortcut icon" href="<?php echo $base_url; ?>favicon.ico">
  <link rel="icon" href="<?php echo $base_url; ?>favicon.ico">  
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.0/jquery.min.js"></script>
  <script type="text/javascript" src="https://www.google.com/jsapi?key={JS-API Key}"></script>
  <script type="text/javascript">
    $(function() {
      // just to make sure that urls of a previous version redirect correctly
      var h = document.location.hash.substring(1);
      if(h!=""&&h.length>12) {
        window.location="<?php echo $base_url; ?>user/"+h;
      }
      // ...
<?php
  if($str_author_id!="") {
  
    $stats = array();
    $day_stats = array();
    for($d=0;$d<=31;$d++) {
      $stats[$d]=array();    
      $stats[$d]["posts"] = array();
      $stats[$d]["comments"] = array();
      $stats[$d]["plusones"] = array();
      $stats[$d]["reshares"] = array();
      $stats[$d]["cpp"] = array();
      $stats[$d]["ppp"] = array();
      $stats[$d]["rpp"] = array();
      $stats[$d]["photos"] = array();
      $stats[$d]["videos"] = array();
      $stats[$d]["links"] = array();
      $stats[$d]["gifs"] = array();
      for($i=0;$i<3;$i++) {
        $stats[$d]["posts"][$i] = 0;
        $stats[$d]["comments"][$i] = 0;
        $stats[$d]["plusones"][$i] = 0;
        $stats[$d]["reshares"][$i] = 0;
        $stats[$d]["cpp"][$i] = 0;
        $stats[$d]["ppp"][$i] = 0;
        $stats[$d]["rpp"][$i] = 0;
        $stats[$d]["location"][$i] = 0;
        $stats[$d]["photos"][$i] = 0;
        $stats[$d]["gifs"][$i] = 0;
        $stats[$d]["videos"][$i] = 0;
        $stats[$d]["links"][$i] = 0;
      }
    }
    $max_plusones = 0;
    $max_plusones_post = -1;
    $max_comments = 0;
    $max_comments_post = -1;
    $max_reshares = 0;
    $max_reshares_post = -1;
    
    for($i=0;$i<$num_activities;$i++) {
      $a = $activities[$i];
      $post_published = recalc_date(substr($a["published"],0,10)." ".substr($a["published"],11,8),$timezone);
      $post_date = new DateTime($post_published);
      $day = (int)($post_date->format("N"));
      $date = $post_date->format("Y-m-d");
      $hour = (int)($post_date->format("G"))+8;
      $tmp_date = new DateTime($date);
      if(isset($min_date)) {
        if($tmp_date<$min_date) {
          unset($min_date);
          $min_date = new DateTime($date);
        }
      } else {
        $min_date = new DateTime($date);
      }
      if(isset($max_date)) {
        if($tmp_date>$max_date) {
          unset($max_date);
          $max_date = new DateTime($date);
        }
      } else {
        $max_date = new DateTime($date);
      }
      unset($tmp_date);
      unset($post_date);
      if(!isset($day_stats[$date])) {
        $day_stats[$date]=array();
        $day_stats[$date]["posts"] = array();
        $day_stats[$date]["comments"] = array();
        $day_stats[$date]["plusones"] = array();
        $day_stats[$date]["reshares"] = array();
        $day_stats[$date]["cpp"] = array();
        $day_stats[$date]["ppp"] = array();
        $day_stats[$date]["rpp"] = array();
        $day_stats[$date]["photos"] = array();
        $day_stats[$date]["videos"] = array();
        $day_stats[$date]["links"] = array();
        $day_stats[$date]["gifs"] = array();
        for($j=0;$j<3;$j++) {
          $day_stats[$date]["posts"][$j] = 0;
          $day_stats[$date]["comments"][$j] = 0;
          $day_stats[$date]["plusones"][$j] = 0;
          $day_stats[$date]["reshares"][$j] = 0;
          $day_stats[$date]["cpp"][$j] = 0;
          $day_stats[$date]["ppp"][$j] = 0;
          $day_stats[$date]["rpp"][$j] = 0;
          $day_stats[$date]["location"][$j] = 0;
          $day_stats[$date]["photos"][$j] = 0;
          $day_stats[$date]["gifs"][$j] = 0;
          $day_stats[$date]["videos"][$j] = 0;
          $day_stats[$date]["links"][$j] = 0;
        }
      }
      $chk_r = false;
      if(isset($a["object"]["actor"])) $chk_r = true;
      $stats[0]["posts"][0]++;
      $stats[0]["posts"][$chk_r?2:1]++;
      $stats[$day]["posts"][0]++;
      $stats[$day]["posts"][$chk_r?2:1]++;
      $stats[$hour]["posts"][0]++;
      $stats[$hour]["posts"][$chk_r?2:1]++;
      $day_stats[$date]["posts"][0]++;
      $day_stats[$date]["posts"][$chk_r?2:1]++;      
      if(isset($a["object"]["replies"])) {
        if($a["object"]["replies"]["totalItems"]>$max_comments) {
          $max_comments = $a["object"]["replies"]["totalItems"];
          $max_comments_post = $i;
        }
        $stats[0]["comments"][0]+=$a["object"]["replies"]["totalItems"];
        $stats[0]["comments"][$chk_r?2:1]+=$a["object"]["replies"]["totalItems"];
        $stats[$day]["comments"][0]+=$a["object"]["replies"]["totalItems"];
        $stats[$day]["comments"][$chk_r?2:1]+=$a["object"]["replies"]["totalItems"];     
        $stats[$hour]["comments"][0]+=$a["object"]["replies"]["totalItems"];
        $stats[$hour]["comments"][$chk_r?2:1]+=$a["object"]["replies"]["totalItems"];
        $day_stats[$date]["comments"][0]+=$a["object"]["replies"]["totalItems"];
        $day_stats[$date]["comments"][$chk_r?2:1]+=$a["object"]["replies"]["totalItems"];
      }
      if(isset($a["object"]["plusoners"])) {
        if($a["object"]["plusoners"]["totalItems"]>$max_plusones) {
          $max_plusones = $a["object"]["plusoners"]["totalItems"];
          $max_plusones_post = $i;
        }
        $stats[0]["plusones"][0]+=$a["object"]["plusoners"]["totalItems"];
        $stats[0]["plusones"][$chk_r?2:1]+=$a["object"]["plusoners"]["totalItems"];
        $stats[$day]["plusones"][0]+=$a["object"]["plusoners"]["totalItems"];
        $stats[$day]["plusones"][$chk_r?2:1]+=$a["object"]["plusoners"]["totalItems"];
        $stats[$hour]["plusones"][0]+=$a["object"]["plusoners"]["totalItems"];
        $stats[$hour]["plusones"][$chk_r?2:1]+=$a["object"]["plusoners"]["totalItems"]; 
        $day_stats[$date]["plusones"][0]+=$a["object"]["plusoners"]["totalItems"];
        $day_stats[$date]["plusones"][$chk_r?2:1]+=$a["object"]["plusoners"]["totalItems"];
      }
      if(isset($a["object"]["resharers"])) {
        if($a["object"]["resharers"]["totalItems"]>$max_reshares) {
          $max_reshares = $a["object"]["resharers"]["totalItems"];
          $max_reshares_post = $i;
        }      
        $stats[0]["reshares"][0]+=$a["object"]["resharers"]["totalItems"];
        $stats[0]["reshares"][$chk_r?2:1]+=$a["object"]["resharers"]["totalItems"];
        $stats[$day]["reshares"][0]+=$a["object"]["resharers"]["totalItems"];
        $stats[$day]["reshares"][$chk_r?2:1]+=$a["object"]["resharers"]["totalItems"];
        $stats[$hour]["reshares"][0]+=$a["object"]["resharers"]["totalItems"];
        $stats[$hour]["reshares"][$chk_r?2:1]+=$a["object"]["resharers"]["totalItems"];
        $day_stats[$date]["reshares"][0]+=$a["object"]["resharers"]["totalItems"];
        $day_stats[$date]["reshares"][$chk_r?2:1]+=$a["object"]["resharers"]["totalItems"];
      }
      if(isset($a["geocode"])) {
        $stats[0]["location"][0]++;
        $stats[0]["location"][$chk_r?2:1]++;
        $stats[$day]["location"][0]++;
        $stats[$day]["location"][$chk_r?2:1]++;
        $stats[$hour]["location"][0]++;
        $stats[$hour]["location"][$chk_r?2:1]++;
        $day_stats[$date]["location"][0]++;
        $day_stats[$date]["location"][$chk_r?2:1]++;
      }
      if(isset($a["object"]["attachments"])) {
        foreach($a["object"]["attachments"] as $at) {
          if($at["objectType"]=="article") {
            $stats[0]["links"][0]++;
            $stats[0]["links"][$chk_r?2:1]++;
            $stats[$day]["links"][0]++;
            $stats[$day]["links"][$chk_r?2:1]++;
            $stats[$hour]["links"][0]++;
            $stats[$hour]["links"][$chk_r?2:1]++;
            $day_stats[$date]["links"][0]++;
            $day_stats[$date]["links"][$chk_r?2:1]++;
          }
          if($at["objectType"]=="photo") {
            $stats[0]["photos"][0]++;
            $stats[0]["photos"][$chk_r?2:1]++;
            $stats[$day]["photos"][0]++;
            $stats[$day]["photos"][$chk_r?2:1]++;
            $stats[$hour]["photos"][0]++;
            $stats[$hour]["photos"][$chk_r?2:1]++;
            $day_stats[$date]["photos"][0]++;
            $day_stats[$date]["photos"][$chk_r?2:1]++;
          }
          if($at["objectType"]=="video") {
            $stats[0]["videos"][0]++;
            $stats[0]["videos"][$chk_r?2:1]++;
            $stats[$day]["videos"][0]++;
            $stats[$day]["videos"][$chk_r?2:1]++;
            $stats[$hour]["videos"][0]++;
            $stats[$hour]["videos"][$chk_r?2:1]++;
            $day_stats[$date]["videos"][0]++;
            $day_stats[$date]["videos"][$chk_r?2:1]++;
          }
          if(isset($at["image"])) {
            if(isset($at["image"]["url"])) {
              if(strtoupper(substr($at["image"]["url"],-4))==".GIF") {
                $stats[0]["gifs"][0]++;
                $stats[0]["gifs"][$chk_r?2:1]++;
                $stats[$day]["gifs"][0]++;
                $stats[$day]["gifs"][$chk_r?2:1]++;
                $stats[$hour]["gifs"][0]++;
                $stats[$hour]["gifs"][$chk_r?2:1]++;
                $day_stats[$date]["gifs"][0]++;
                $day_stats[$date]["gifs"][$chk_r?2:1]++;
              }
            }
          }
        }
      }
    }
    
    for($d=0;$d<=31;$d++) {
      for($i=0;$i<3;$i++) {
        if($stats[$d]["posts"][$i]!=0) {
          $stats[$d]["cpp"][$i] = $stats[$d]["comments"][$i] / $stats[$d]["posts"][$i];
          $stats[$d]["rpp"][$i] = $stats[$d]["reshares"][$i] / $stats[$d]["posts"][$i];
          $stats[$d]["ppp"][$i] = $stats[$d]["plusones"][$i] / $stats[$d]["posts"][$i];
        }
      }
    }
    $num_days = 0;
    if(isset($min_date)) {
      $tmp_date = new DateTime($min_date->format("Y-m-d"));
      $tmp_date_max = new DateTime($max_date->format("Y-m-d"));
      while($tmp_date <= $tmp_date_max) {
        $num_days++;
        $date = $tmp_date->format("Y-m-d");
        if(isset($day_stats[$date])) {
          for($i=0;$i<3;$i++) {
            if($day_stats[$date]["posts"][$i]!=0) {
              $day_stats[$date]["cpp"][$i] = $day_stats[$date]["comments"][$i] / $day_stats[$date]["posts"][$i];
              $day_stats[$date]["rpp"][$i] = $day_stats[$date]["reshares"][$i] / $day_stats[$date]["posts"][$i];
              $day_stats[$date]["ppp"][$i] = $day_stats[$date]["plusones"][$i] / $day_stats[$date]["posts"][$i];
            }
          }
        }
        $tmp_date->add(new DateInterval("P1D"));
      }
    }  
  
?>
      google.load("visualization", "1", {packages:["corechart"], callback: prepare_charts});
      google.load("maps", "3", {other_params:'sensor=false', callback: draw_map});

<?php } ?>
    });  
<?php
  if($str_author_id!="") { ?>

    var day_data;
    var day_view;
    var day_chart;
    var weekday_data;
    var weekday_view;
    var weekday_chart;
    var hour_data;
    var hour_view;
    var hour_chart;
  
    function prepare_charts() {
<?php
        
    $data_array = "[['Date','Posts','Posts (o)','Posts (r)','Location','Location (o)','Location (r)','Photos','Photos (o)','Photos (r)','GIFs','GIFs (o)','GIFs (r)','Videos','Videos (o)','Videos (r)','Links','Links (o)','Links (r)','Comments','Comments (o)','Comments (r)','CpP','CpP (o)','CpP (r)','+1\'s','+1\'s (o)','+1\'s (r)','PpP','PpP (o)','PpP (r)','Reshares','Reshares (o)','Reshares (r)','RpP','RpP (o)','RpP (r)']";
    if(isset($min_date)) {
      $tmp_date = new DateTime($min_date->format("Y-m-d"));
      $tmp_date_max = new DateTime($max_date->format("Y-m-d"));
      while($tmp_date <= $tmp_date_max) {
        $date = $tmp_date->format("Y-m-d");
        if(isset($day_stats[$date])) {
          $data_array = $data_array.",['".$date."'";
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["posts"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["location"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["photos"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["gifs"][$i];          
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["videos"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["links"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["comments"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$day_stats[$date]["cpp"][$i]);          
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["plusones"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$day_stats[$date]["ppp"][$i]);          
          for($i=0;$i<3;$i++) $data_array = $data_array.",".$day_stats[$date]["reshares"][$i];
          for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$day_stats[$date]["rpp"][$i]);

          $data_array = $data_array . "]";
        } else {
          $data_array = $data_array.",['".$date."',0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]";
        }
        $tmp_date->add(new DateInterval("P1D"));
      }
    }
    $data_array = $data_array . "]";
    printf("      day_data = google.visualization.arrayToDataTable(%s);\n",$data_array);
    
    $data_array = "[['Date','Posts','Posts (o)','Posts (r)','Location','Location (o)','Location (r)','Photos','Photos (o)','Photos (r)','GIFs','GIFs (o)','GIFs (r)','Videos','Videos (o)','Videos (r)','Links','Links (o)','Links (r)','Comments','Comments (o)','Comments (r)','CpP','CpP (o)','CpP (r)','+1\'s','+1\'s (o)','+1\'s (r)','PpP','PpP (o)','PpP (r)','Reshares','Reshares (o)','Reshares (r)','RpP','RpP (o)','RpP (r)']";
    for($d=1;$d<=7;$d++) {
      if($d==1) $data_array = $data_array.",['Mon'";
      if($d==2) $data_array = $data_array.",['Tue'";
      if($d==3) $data_array = $data_array.",['Wed'";
      if($d==4) $data_array = $data_array.",['Thu'";
      if($d==5) $data_array = $data_array.",['Fri'";
      if($d==6) $data_array = $data_array.",['Sat'";
      if($d==7) $data_array = $data_array.",['Sun'";
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["posts"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["location"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["photos"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["gifs"][$i];          
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["videos"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["links"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["comments"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$stats[$d]["cpp"][$i]);          
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["plusones"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$stats[$d]["ppp"][$i]);          
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$d]["reshares"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$stats[$d]["rpp"][$i]);
      $data_array = $data_array . "]";
    }
    $data_array = $data_array . "]";
    printf("      weekday_data = google.visualization.arrayToDataTable(%s);\n",$data_array);
    
    $data_array = "[['Date','Posts','Posts (o)','Posts (r)','Location','Location (o)','Location (r)','Photos','Photos (o)','Photos (r)','GIFs','GIFs (o)','GIFs (r)','Videos','Videos (o)','Videos (r)','Links','Links (o)','Links (r)','Comments','Comments (o)','Comments (r)','CpP','CpP (o)','CpP (r)','+1\'s','+1\'s (o)','+1\'s (r)','PpP','PpP (o)','PpP (r)','Reshares','Reshares (o)','Reshares (r)','RpP','RpP (o)','RpP (r)']";
    for($h=8;$h<=31;$h++) {
      $data_array = $data_array.sprintf(",['%s'",$h-8);
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["posts"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["location"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["photos"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["gifs"][$i];          
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["videos"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["links"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["comments"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$stats[$h]["cpp"][$i]);          
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["plusones"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$stats[$h]["ppp"][$i]);          
      for($i=0;$i<3;$i++) $data_array = $data_array.",".$stats[$h]["reshares"][$i];
      for($i=0;$i<3;$i++) $data_array = $data_array.",".sprintf("%01.2f",$stats[$h]["rpp"][$i]);
      $data_array = $data_array . "]";
    }
    $data_array = $data_array . "]";
    printf("      hour_data = google.visualization.arrayToDataTable(%s);\n",$data_array);    
?>
      day_view = new google.visualization.DataView(day_data);
      day_view.setColumns([0,1,2,3]);
      day_chart = new google.visualization.AreaChart($("#day_chart")[0]);
      
      weekday_view = new google.visualization.DataView(weekday_data);
      weekday_view.setColumns([0,1,2,3]);
      weekday_chart = new google.visualization.ColumnChart($("#weekday_chart")[0]);

      hour_view = new google.visualization.DataView(hour_data);
      hour_view.setColumns([0,1,2,3]);
      hour_chart = new google.visualization.ColumnChart($("#hour_chart")[0]);
      
      update_charts();
    }
    
    function update_charts() {
      var cols = new Array();
      cols.push(0);
      if($("#chk_posts").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(1);
        if($("#chk_original").is(":checked")) cols.push(2);
        if($("#chk_reshared").is(":checked")) cols.push(3);
      }
      if($("#chk_location").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(4);
        if($("#chk_original").is(":checked")) cols.push(5);
        if($("#chk_reshared").is(":checked")) cols.push(6);
      } 
      if($("#chk_photos").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(7);
        if($("#chk_original").is(":checked")) cols.push(8);
        if($("#chk_reshared").is(":checked")) cols.push(9);
      }
      if($("#chk_gifs").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(10);
        if($("#chk_original").is(":checked")) cols.push(11);
        if($("#chk_reshared").is(":checked")) cols.push(12);
      }
      if($("#chk_videos").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(13);
        if($("#chk_original").is(":checked")) cols.push(14);
        if($("#chk_reshared").is(":checked")) cols.push(15);
      }
      if($("#chk_links").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(16);
        if($("#chk_original").is(":checked")) cols.push(17);
        if($("#chk_reshared").is(":checked")) cols.push(18);
      }
      if($("#chk_comments").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(19);
        if($("#chk_original").is(":checked")) cols.push(20);
        if($("#chk_reshared").is(":checked")) cols.push(21);
      }
      if($("#chk_cpp").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(22);
        if($("#chk_original").is(":checked")) cols.push(23);
        if($("#chk_reshared").is(":checked")) cols.push(24);
      }
      if($("#chk_plusones").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(25);
        if($("#chk_original").is(":checked")) cols.push(26);
        if($("#chk_reshared").is(":checked")) cols.push(27);
      }
      if($("#chk_ppp").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(28);
        if($("#chk_original").is(":checked")) cols.push(29);
        if($("#chk_reshared").is(":checked")) cols.push(30);
      }
      if($("#chk_reshares").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(31);
        if($("#chk_original").is(":checked")) cols.push(32);
        if($("#chk_reshared").is(":checked")) cols.push(33);
      }
      if($("#chk_rpp").is(":checked")) {
        if($("#chk_total").is(":checked"))    cols.push(34);
        if($("#chk_original").is(":checked")) cols.push(35);
        if($("#chk_reshared").is(":checked")) cols.push(36);
      }
      if(cols.length>1) {
        $("#chart_warning").hide();
        $("#day_chart").show();
        $("#weekday_chart").show();
        $("#hour_chart").show();
        day_view.setColumns(cols);
        day_chart.draw(day_view,
          {width:950,
           height:250,
           title:"Timeline",
           hAxis:{textStyle:{fontSize:10}},
           legendTextStyle:{fontSize:10}}
        );
        weekday_view.setColumns(cols);
        weekday_chart.draw(weekday_view,
          {width:950,
           height:250,
           title:"Posting behaviour per weekday",
           hAxis:{textStyle:{fontSize:10}},
           legendTextStyle:{fontSize:10}}
        );
        hour_view.setColumns(cols);
        hour_chart.draw(hour_view,
          {width:950,
           height:250,
           title:"Posting behaviour per hour",
           hAxis:{textStyle:{fontSize:10}},
           legendTextStyle:{fontSize:10}}
        );
      } else {
        $("#chart_warning").show();
        $("#day_chart").hide();
        $("#weekday_chart").hide();
        $("#hour_chart").hide();
      }
    }
    
    function draw_map () {
      var latlng = new google.maps.LatLng(0, 0);
      var myOptions = {
        zoom: 0,
        center: latlng,
        disableDefaultUI: true,
        zoomControl: true,
        mapTypeId: google.maps.MapTypeId.ROADMAP
      };
      var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
      var llbounds = new google.maps.LatLngBounds();
      var wp;
      var maps_marker;    
<?php
    $chk_locations=false;
    for($i=0;$i<$num_activities;$i++) {
      if(!isset($activities[$i]["object"]["actor"])) {
        if(isset($activities[$i]["geocode"])) {
          $chk_locations=true;
          list($lat,$lng) = explode(" ",$activities[$i]["geocode"]);
          printf("      wp = new google.maps.LatLng(%s, %s);\n",$lat,$lng);
          printf("      var maps_marker = new google.maps.Marker({position: wp, map: map});\n");
          printf("      llbounds.extend(wp);\n");
        }
      }
    }
    if($chk_locations==true) {
      printf("      map.fitBounds(llbounds);\n");
    }
?>
    }
    
 <?php } ?>
    
  </script>
</head>
<body> 
  <div id="header">
    <div id="header1">
      <table><tr>
        <td><form method="post" action="<?php echo $base_url; ?>">Profile ID: <input id="userid" name="userid" title="Go to a Google+ profile and copy the long number from the URL into this field."><input type="submit"></form></td>
<?php
  if(isset($authUrl)) {
    printf("        <td style=\"text-align: right;\"><a class=\"login\" href=\"%s\">Login via Google</a></td>\n",$authUrl);
  } else {
    printf("        <td style=\"text-align: right;\">Logged in as %s / <a class=\"logout\" href=\"?logout\">Logout</a></td>\n",$login_name);
  }?>
      </tr></table>
    </div>
    <div id="header2">
      <div id="header2_info">
        <table><tr>
          <td style="width: 70px;"><img src="<?php echo $base_url; ?>allmy+.png" alt="All my +"></td>
          <td>
<?php
  if($str_author_name=="") {
    if($q_user!="") {
      printf("            <h1>No data found.</h1>\n");
      printf("            Please check the profile ID and note that for now only public data can be accessed via the API.<br>\n");
    } else {
      printf("            <h1>No profile chosen.</h1>\n");
      printf("            Use the form above to look up a specific profile or login via Google to display this page with your own data.\n");
    }
  } else { 
    printf("            <h1>Data for %s</h1>\n",$str_author_name);
  }
  if($str_author_name!="") {
?>
            <p class="smalll"><a href="#overview">Overview</a> - <a href="#charts">Charts</a> - <a href="#popular">Most popular posts</a> - <a href="#people">People</a> - <a href="#photos">Photos</a> - <a href="#posts">Posts</a></p>
<?php
  }
?>
          </td>
          <td style="text-align:right;">
        </td>
        </tr></table>
      </div>
    </div>
  </div>
  <div id="main">
<?php
  if($str_author_name!="") {
?>  
  <div class="header" onclick="$('#overview_d').toggle(); $('#collapse_overview').toggle(); $('#expand_overview').toggle();">
    <div id="overview" class="anchor"></div>
    <table><tr>
      <td>Overview</td>
      <td style="text-align:right;"><img src="<?php echo $base_url; ?>collapse.png" alt="collapse" id="collapse_overview"><img src="<?php echo $base_url; ?>expand.png" alt="expand" id="expand_overview" style="display:none;"></td>
    </tr></table>
  </div>
  <div id="overview_d">
    <table style="width: 100%;"><tr>
      <td style="text-align: center;">
<?php
    if($str_author_pic=="") $str_author_pic = $base_url."noimage.png";
    printf("        <br><a href=\"%s\"><img src=\"%s\" alt=\"%s\" style=\"max-width:200px; max-height:200px\"></a><br>\n",$str_author_url,$str_author_pic,$str_author_name);
    printf("        <a href=\"%s\" style=\"font-weight: bold;\">%s</a>\n",$str_author_url,$str_author_name);
?>
      </td>
      <td>
<?php
    printf("        <table style=\"margin-left: auto; margin-right:auto;\">\n");
    printf("          <tr><th></th><th>&nbsp;&nbsp;&nbsp;Total</th><th>&nbsp;&nbsp;&nbsp;Original</th><th>&nbsp;&nbsp;&nbsp;Reshared</th></tr>\n");
    printf("          <tr><th>Posts</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["posts"][0],$stats[0]["posts"][1],$stats[0]["posts"][2]);
    printf("          <tr><th>Location</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["location"][0],$stats[0]["location"][1],$stats[0]["location"][2]);
    printf("          <tr><th>Photos</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["photos"][0],$stats[0]["photos"][1],$stats[0]["photos"][2]);
    printf("          <tr><th>GIFs</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["gifs"][0],$stats[0]["gifs"][1],$stats[0]["gifs"][2]);
    printf("          <tr><th>Videos</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["videos"][0],$stats[0]["videos"][1],$stats[0]["videos"][2]);
    printf("          <tr><th>Links</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["links"][0],$stats[0]["links"][1],$stats[0]["links"][2]);
    printf("          <tr><th>Comments</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["comments"][0],$stats[0]["comments"][1],$stats[0]["comments"][2]);
    printf("          <tr><td class=\"stats noborder\">per post</td><td class=\"stats\">%01.2f</td><td class=\"stats\">%01.2f</td><td class=\"stats\">%01.2f</td></tr>",$stats[0]["cpp"][0],$stats[0]["cpp"][1],$stats[0]["cpp"][2]);
    printf("          <tr><th>+1's</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["plusones"][0],$stats[0]["plusones"][1],$stats[0]["plusones"][2]);
    printf("          <tr><td class=\"stats noborder\">per post</td><td class=\"stats\">%01.2f</td><td class=\"stats\">%01.2f</td><td class=\"stats\">%01.2f</td></tr>",$stats[0]["ppp"][0],$stats[0]["ppp"][1],$stats[0]["ppp"][2]);
    printf("          <tr><th>Reshares</th><td class=\"stats\">%s</td><td class=\"stats\">%s</td><td class=\"stats\">%s</td></tr>",$stats[0]["reshares"][0],$stats[0]["reshares"][1],$stats[0]["reshares"][2]);
    printf("          <tr><td class=\"stats noborder\">per post</td><td class=\"stats\">%01.2f</td><td class=\"stats\">%01.2f</td><td class=\"stats\">%01.2f</td></tr>",$stats[0]["rpp"][0],$stats[0]["rpp"][1],$stats[0]["rpp"][2]);
    printf("        </table>\n");
    for($d=0;$d<=31;$d++) {
      unset($stats[$d]["posts"]);
      unset($stats[$d]["comments"]);
      unset($stats[$d]["plusones"]);
      unset($stats[$d]["reshares"]);
      unset($stats[$d]["cpp"]);
      unset($stats[$d]["ppp"]);
      unset($stats[$d]["rpp"]);
      unset($stats[$d]["photos"]);
      unset($stats[$d]["videos"]);
      unset($stats[$d]["links"]);
      unset($stats[$d]["gifs"]);
      unset($stats[$d]);
    }
    if(isset($min_date)) {
      $tmp_date = new DateTime($min_date->format("Y-m-d"));
      $tmp_date_max = new DateTime($max_date->format("Y-m-d"));
      while($tmp_date <= $tmp_date_max) {
        if(isset($day_stats[$tmp_date->format("Y-m-d")])) {
          unset($day_stats[$tmp_date->format("Y-m-d")]["posts"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["comments"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["plusones"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["reshares"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["cpp"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["ppp"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["rpp"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["photos"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["videos"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["links"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]["gifs"]);
          unset($day_stats[$tmp_date->format("Y-m-d")]);
        }
        $tmp_date->add(new DateInterval("P1D"));
      }
    }
    unset($stats);
?>      
      </td>
      <td>
        <table style="margin-left: auto;">
          <tr><th style="text-align:center;">Locations of posts</th></tr>      
          <tr><td class="stats noborder"><div id="map_canvas" style="width:400px; height:250px; margin-left:auto; border:1px solid black;"></div></td></tr>
        </table>
    </tr></table><br>
  </div>
  <div class="header" onclick="$('#charts_d').toggle(); $('#collapse_charts').toggle(); $('#expand_charts').toggle();">
    <div id="charts" class="anchor"></div>
    <table><tr>
      <td>Charts</td>
      <td style="text-align:right;"><img src="<?php echo $base_url; ?>collapse.png" alt="collapse" id="collapse_charts"><img src="<?php echo $base_url; ?>expand.png" alt="expand" id="expand_charts" style="display:none;"></td>
    </tr></table>
  </div>
  <div id="charts_d">
    <p class="smalll">Note: The API delivers post date and time in UTC. To display the data for your timezone please choose it below.<br>Unfortunately this will cause the page to reload completely, since all calculations have to be done again.</p>
    <p class="smalll">
      <?php
        if($timezone!=-12) { printf("<a href=\"?timezone=-12\">-12</a> "); } else { printf("<b>-12</b> "); };
        if($timezone!=-11) { printf("<a href=\"?timezone=-11\">-11</a> "); } else { printf("<b>-11</b> "); };
        if($timezone!=-10) { printf("<a href=\"?timezone=-10\">-10</a> "); } else { printf("<b>-10</b> "); };
        if($timezone!=-9.5) { printf("<a href=\"?timezone=-9.5\">-9:30</a> "); } else { printf("<b>-9:30</b> "); };
        if($timezone!=-9) { printf("<a href=\"?timezone=-9\">-9</a> "); } else { printf("<b>-9</b> "); };
        if($timezone!=-8) { printf("<a href=\"?timezone=-8\">-8</a> "); } else { printf("<b>-8</b> "); };
        if($timezone!=-7) { printf("<a href=\"?timezone=-7\">-7</a> "); } else { printf("<b>-7</b> "); };
        if($timezone!=-6) { printf("<a href=\"?timezone=-6\">-6</a> "); } else { printf("<b>-6</b> "); };
        if($timezone!=-5) { printf("<a href=\"?timezone=-5\">-5</a> "); } else { printf("<b>-5</b> "); };
        if($timezone!=-4) { printf("<a href=\"?timezone=-4\">-4</a> "); } else { printf("<b>-4</b> "); };
        if($timezone!=-3) { printf("<a href=\"?timezone=-3\">-3</a> "); } else { printf("<b>-3</b> "); };
        if($timezone!=-2) { printf("<a href=\"?timezone=-2\">-2</a> "); } else { printf("<b>-2</b> "); };
        if($timezone!=-1) { printf("<a href=\"?timezone=-1\">-1</a> "); } else { printf("<b>-1</b> "); };
        if($timezone!=0) { printf("<a href=\"?timezone=0\">UTC</a> "); } else { printf("<b>UTC</b> "); };
        if($timezone!=1) { printf("<a href=\"?timezone=1\">+1</a> "); } else { printf("<b>+1</b> "); };
        if($timezone!=2) { printf("<a href=\"?timezone=2\">+2</a> "); } else { printf("<b>+2</b> "); };
        if($timezone!=3) { printf("<a href=\"?timezone=3\">+3</a> "); } else { printf("<b>+3</b> "); };
        if($timezone!=3.5) { printf("<a href=\"?timezone=3.5\">+3:30</a> "); } else { printf("<b>+3:30</b> "); };
        if($timezone!=4) { printf("<a href=\"?timezone=4\">+4</a> "); } else { printf("<b>+4</b> "); };
        if($timezone!=4.5) { printf("<a href=\"?timezone=4.5\">+4:30</a> "); } else { printf("<b>+4:30</b> "); };
        if($timezone!=5) { printf("<a href=\"?timezone=5\">+5</a> "); } else { printf("<b>+5</b> "); };
        if($timezone!=5.5) { printf("<a href=\"?timezone=5.5\">+5:30</a> "); } else { printf("<b>+5:30</b> "); };
        if($timezone!=5.75) { printf("<a href=\"?timezone=5.75\">+5:45</a> "); } else { printf("<b>+5:45</b> "); };
        if($timezone!=6) { printf("<a href=\"?timezone=6\">+6</a> "); } else { printf("<b>+6</b> "); };
        if($timezone!=6.5) { printf("<a href=\"?timezone=6.5\">+6:30</a> "); } else { printf("<b>+6:30</b> "); };
        if($timezone!=7) { printf("<a href=\"?timezone=7\">+7</a> "); } else { printf("<b>+7</b> "); };
        if($timezone!=8) { printf("<a href=\"?timezone=8\">+8</a> "); } else { printf("<b>+8</b> "); };
        if($timezone!=8.75) { printf("<a href=\"?timezone=8.75\">+8:45</a> "); } else { printf("<b>+8:45</b> "); };
        if($timezone!=9) { printf("<a href=\"?timezone=9\">+9</a> "); } else { printf("<b>+9</b> "); };
        if($timezone!=9.5) { printf("<a href=\"?timezone=9.5\">+9:30</a> "); } else { printf("<b>+9:30</b> "); };
        if($timezone!=10) { printf("<a href=\"?timezone=10\">+10</a> "); } else { printf("<b>+10</b> "); };
        if($timezone!=10.5) { printf("<a href=\"?timezone=10.5\">+10:30</a> "); } else { printf("<b>+10:30</b> "); };
        if($timezone!=11) { printf("<a href=\"?timezone=11\">+11</a> "); } else { printf("<b>+11</b> "); };
        if($timezone!=11.5) { printf("<a href=\"?timezone=11.5\">+11:30</a> "); } else { printf("<b>+11:30</b> "); };
        if($timezone!=12) { printf("<a href=\"?timezone=12\">+12</a> "); } else { printf("<b>+12</b> "); };
        if($timezone!=12.75) { printf("<a href=\"?timezone=12.75\">+12:45</a> "); } else { printf("<b>+12:45</b> "); };
        if($timezone!=13) { printf("<a href=\"?timezone=13\">+13</a> "); } else { printf("<b>+13</b> "); };
        if($timezone!=14) { printf("<a href=\"?timezone=14\">+14</a> "); } else { printf("<b>+14</b> "); };
      ?>
    </p>
    <hr>
    <p class="smalll">
      Type: Total <input type="checkbox" id="chk_total" name="chk_total" value="chk_total" checked onclick="update_charts(); return true;"> / Original <input type="checkbox" id="chk_original" name="chk_original" value="chk_original" checked onclick="update_charts(); return true;"> / Reshared <input type="checkbox" id="chk_reshared" name="chk_reshared" value="chk_reshared" onclick="update_charts(); return true;"><br><br>
      Values: Posts <input type="checkbox" id="chk_posts" name="chk_posts" value="chk_posts" checked onclick="update_charts(); return true;">
      / Location <input type="checkbox" id="chk_location" name="chk_location" value="chk_location" onclick="update_charts(); return true;">
      / Photos <input type="checkbox" id="chk_photos" name="chk_photos" value="chk_photos" onclick="update_charts(); return true;">
      / GIFs <input type="checkbox" id="chk_gifs" name="chk_gifs" value="chk_gifs" onclick="update_charts(); return true;">
      / Videos <input type="checkbox" id="chk_videos" name="chk_videos" value="chk_videos" onclick="update_charts(); return true;">
      / Links <input type="checkbox" id="chk_links" name="chk_links" value="chk_links" onclick="update_charts(); return true;">
      / Comments <input type="checkbox" id="chk_comments" name="chk_comments" value="chk_comments" onclick="update_charts(); return true;">
      / CpP <input type="checkbox" id="chk_cpp" name="chk_cpp" value="chk_cpp" onclick="update_charts(); return true;">
      / +1's <input type="checkbox" id="chk_plusones" name="chk_plusones" value="chk_plusones" onclick="update_charts(); return true;">
      / PpP <input type="checkbox" id="chk_ppp" name="chk_ppp" value="chk_ppp" onclick="update_charts(); return true;">
      / Reshares <input type="checkbox" id="chk_reshares" name="chk_reshares" value="chk_reshares" onclick="update_charts(); return true;">
      / RpP <input type="checkbox" id="chk_rpp" name="chk_rpp" value="chk_rpp" onclick="update_charts(); return true;">
    </p>
    <div id="chart_warning" style="font-weight:bold;">No values selected.<br><br></div>
    <div id="day_chart"></div>
    <div id="weekday_chart"></div>
    <div id="hour_chart"></div>
  </div>    
  
  <div class="header" onclick="$('#popular_d').toggle(); $('#collapse_popular').toggle(); $('#expand_popular').toggle();">
    <div id="popular" class="anchor"></div>
    <table><tr>
      <td>Most popular posts</td>
      <td style="text-align:right;"><img src="<?php echo $base_url; ?>collapse.png" alt="collapse" id="collapse_popular"><img src="<?php echo $base_url; ?>expand.png" alt="expand" id="expand_popular" style="display:none;"></td>
    </tr></table>
  </div>
  <div id="popular_d">
<?php
    $chk_comments = false;
    $chk_reshares = false;
    $chk_plusones = false;
    printf("    <br>\n");
    if($max_comments>0) {
      $chk_comments = true;
      printf("    <b>Most comments (%s)",$max_comments);
      if($max_comments_post==$max_reshares_post) {
        printf(" / Most reshares (%s)",$max_reshares);
        $chk_reshares = true;
      }
      if($max_comments_post==$max_plusones_post) {
        printf(" / Most +1's (%s)",$max_plusones);
        $chk_plusones = true;
      }
      printf("</b><br>\n");
      print_activity($activities[$max_comments_post],4);
    }
    if($max_reshares>0&&$chk_reshares==false) {
      $chk_reshares = true;
      if($chk_comments) printf("    <hr>\n");
      printf("    <b>Most reshares (%s)",$max_reshares);
      if($max_reshares_post==$max_plusones_post) {
        printf(" / Most +1's (%s)",$max_plusones);
        $chk_plusones = true;
      }
      printf("</b><br>\n");
      print_activity($activities[$max_reshares_post],4);
    }
    if($max_plusones>0&&$chk_plusones==false) {
      if($chk_comments||$chk_reshares) printf("    <hr>\n");
      printf("    <b>Most +1's (%s)</b><br>\n",$max_plusones);
      print_activity($activities[$max_plusones_post],4);
    }
    printf("    <br><br>\n");
?>
  </div>
  <div class="header" onclick="$('#people_d').toggle(); $('#collapse_people').toggle(); $('#expand_people').toggle();">
    <div id="people" class="anchor"></div>
    <table><tr>
      <td>People</td>
      <td style="text-align:right;"><img src="<?php echo $base_url; ?>collapse.png" alt="collapse" id="collapse_people"><img src="<?php echo $base_url; ?>expand.png" alt="expand" id="expand_people" style="display:none;"></td>
    </tr></table>
  </div>
  <div id="people_d">  
<?php
    $resharer_count = array();
    $resharer_name = array();
    $resharer_url = array();
    $resharer_pic = array();

   for($i=0;$i<$num_activities;$i++) {
      $a = $activities[$i];
      $chk_r = false;
      if(isset($a["object"]["actor"])) {
        $id = $a["object"]["actor"]["id"];
        if($id!=$str_author_id) {
          if(isset($resharer_count[$id])) {
            $resharer_count[$id]++;
          } else {
            $resharer_count[$id] = 1;
            $resharer_name[$id] = $a["object"]["actor"]["displayName"];
            $resharer_url[$id] = $a["object"]["actor"]["url"];
            $resharer_pic[$id] = "";
            if(isset($a["object"]["actor"]["image"])) {
              if(isset($a["object"]["actor"]["image"]["url"])) {
                $resharer_pic[$id] = $a["object"]["actor"]["image"]["url"];
              }
            }
            if($reshares_pic[$id]=="") $reshares_pic[$id] = $base_url."noimage.png";
          }
        }
      }
    }    
    arsort($resharer_count);
    printf("    <br>\n");
    printf("    <b>Reshared</b><br><br>\n");
    printf("    <div style=\"text-align:left\">\n");
    foreach($resharer_count as $id => $count) {
      printf("      <div class=\"profile\">\n");
      printf("        <b>%s</b><br>\n",$resharer_name[$id]);
      printf("        <a href=\"%s\"><img src=\"%s\" alt=\"%s\" style=\"width:200px;height:200px\"></a><br>\n",$resharer_url[$id],$resharer_pic[$id],$resharer_name[$id]);
      if($count==1) {
        printf("        1 reshare\n");
      } else {
        printf("        %s reshares\n",$count);
      }
      printf("      </div>\n");
    }
    printf("    </div><br>\n");
    
    //printf("    <b>Mentioned</b><br><br>\n");
?>
  </div>
  <div class="header" onclick="$('#photos_d').toggle(); $('#collapse_photos').toggle(); $('#expand_photos').toggle();">
    <div id="photos" class="anchor"></div>
    <table><tr>
      <td>Photos</td>
      <td style="text-align:right;"><img src="<?php echo $base_url; ?>collapse.png" alt="collapse" id="collapse_photos"><img src="<?php echo $base_url; ?>expand.png" alt="expand" id="expand_photos" style="display:none;"></td>
    </tr></table>
  </div>
  <div id="photos_d">
<?php
    printf("    <br>\n");
    printf("    <b>Photos from own posts</b><br>\n");
    printf("    <div style=\"text-align:center\">\n");
    for($i=0;$i<$num_activities;$i++) {    
      $chk_r = false;
      if(isset($activities[$i]["object"]["actor"])) $chk_r = true;
      if($chk_r==false) {
        print_photos($activities[$i],6);
      }
    }
    printf("    </div><br>\n");
    printf("    <b>Photos from reshared posts</b><br>\n");
    printf("    <div style=\"text-align:center\">\n");
    for($i=0;$i<$num_activities;$i++) {    
      $chk_r = false;
      if(isset($activities[$i]["object"]["actor"])) $chk_r = true;
      if($chk_r==true) {
        print_photos($activities[$i],6);
      }
    }    
    printf("    </div><br>\n");
?>
  </div>
  <div class="header" onclick="$('#posts_d').toggle(); $('#collapse_posts').toggle(); $('#expand_posts').toggle();">
    <div id="posts" class="anchor"></div>
    <table><tr>
      <td>Posts</td>
      <td style="text-align:right;"><img src="<?php echo $base_url; ?>collapse.png" alt="collapse" id="collapse_posts"><img src="<?php echo $base_url; ?>expand.png" alt="expand" id="expand_posts" style="display:none;"></td>
    </tr></table>
  </div>
  <div id="posts_d">
<?php
    for($i=$num_activities-1;$i>=0;$i--) {
      print_activity($activities[$i],4);
      if($i>0) { printf("    <hr>\n"); }
    }
?>
  </div>
<?php
    unset($activities);
  }
  if($str_errors!="") {
    $str_errors = str_replace("&amp;","&",$str_errors);
    $str_errors = str_replace("&","&amp;",$str_errors);
    printf("<div id=\"errors\" style=\"display:none\">%s</div>\n",$str_errors);
  }
?>
  </div>
<?php
  if($str_author_name!="") {
    printf("  <div id=\"footer\" class=\"footer_data\">\n");
  } else {
    printf("  <div id=\"footer\">\n");
  }
?>  
    <p class="smalll">
      <b>Disclaimer:</b><br>
      Alpha version still in development, script may be broken at times as I change stuff...<br>
      Known API issues: only a maximum 250 posts can be retrieved / the API sometimes returns an error for users with lots of activity (sorry Robert and Tom) / for some people the API client throws an "invalid json in service response" error<br><br>
      If you have questions/suggestions/problems or want to stay updated on changes you can <a href="https://plus.google.com/112336147904981294875/about" rel="author">circle me on Google+</a>.<br><br>
      All the data presented here is public information available through the <a href="https://developers.google.com/+/api/">Google+&#8482; API</a>.<br>
      No data will be stored on this site and your authentication token is only saved temporarily to allow access to your data.<br> 
      Google+ is a trademark of Google Inc. Use of this trademark is subject to <a href="http://www.google.com/permissions/index.html">Google Permissions</a>.<br>
      I'm not the owner of any of the data and not responsible for any offensive material you might find.<br>
      This site is not affiliated with, sponsored by, or endorsed by <a href="http://www.google.com/">Google Inc</a>.</p>
    <p class="smallr">Programming by <a href="https://profiles.google.com/scarygami" style="color:#000000;" rel="author">Gerwin Sturm</a>, <a href="http://www.foldedsoft.at/" style="color:#000000;">FoldedSoft e.U.</a></p>
  </div>
</body>
</html>