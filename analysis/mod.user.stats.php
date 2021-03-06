<?php
require_once './common/config.php';
require_once './common/functions.php';
require_once './common/CSV.class.php'
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>TCAT :: User stats</title>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <link rel="stylesheet" href="css/main.css" type="text/css" />

        <script type="text/javascript" language="javascript">
	
	
	
        </script>

    </head>

    <body>

        <h1>TCAT :: User Stats</h1>

        <?php
        validate_all_variables();

        $filename = get_filename_for_export("userStats");

        $csv = new CSV($filename, $outputformat);

        // tweets per user
        $sql = "SELECT count(distinct(t.id)) AS count, t.from_user_id, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart, from_user_id";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['datepart']][$res['from_user_id']] = $res['count'];
        }
        if (!empty($array)) {
            foreach ($array as $date => $ar)
                $stats[$date]['tweets_per_user'] = stats_summary($ar);
        }

        // users per interval
        $sql = "SELECT count(distinct(t.from_user_id)) AS count, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart";
        //print $sql . "<br>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['datepart']] = $res['count'];
        }
        if (!empty($array)) {
            $stats['all dates']['users_per_date'] = stats_summary($array);
        }

        // urls per user per interval
        $sql = "SELECT count(distinct(u.url)) AS count, u.from_user_id, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_urls u, " . $esc['mysql']['dataset'] . "_tweets t ";
        $where = "t.id = u.tweet_id AND ";
        $sql .= sqlSubset($where);
        $sql .= "GROUP BY datepart, from_user_id";
        //print $sql."<br>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['datepart']][$res['from_user_id']] = $res['count'];
        }
        if (!empty($array)) {
            foreach ($array as $date => $ar)
                $stats[$date]['urls_per_user'] = stats_summary($ar);
        }

        // select latest user info per interval
        $sql = "SELECT max(t.created_at), t.from_user_id, t.from_user_followercount, t.from_user_friendcount, t.from_user_tweetcount, ";
        $sql .= sqlInterval();
        $sql .= " FROM " . $esc['mysql']['dataset'] . "_tweets t ";
        $sql .= sqlSubset();
        $sql .= "GROUP BY datepart, from_user_id";
        //print $sql."<bR>";
        $sqlresults = mysql_query($sql);
        $array = array();
        while ($res = mysql_fetch_assoc($sqlresults)) {
            $array[$res['datepart']]['followercount'][$res['from_user_id']] = $res['from_user_followercount'];
            $array[$res['datepart']]['friendcount'][$res['from_user_id']] = $res['from_user_friendcount'];
            $array[$res['datepart']]['tweetcount'][$res['from_user_id']] = $res['from_user_tweetcount'];
        }
        if (!empty($array)) {
            foreach ($array as $date => $ar) {
                $stats[$date]['followercount'] = stats_summary($ar['followercount']);
                $stats[$date]['friendcount'] = stats_summary($ar['friendcount']);
                $stats[$date]['tweetcount'] = stats_summary($ar['tweetcount']);
            }
        }

        // @todo: aantal retweets

        $csv->writeheader(array("date", "what", "min", "max", "avg", "Q1", "median", "Q3", "25%TrimmedMean"));
        foreach ($stats as $date => $datestats) {
            foreach ($datestats as $what => $stat) {
                $csv->newrow();
                $csv->addfield($date);
                $csv->addfield($what);
                $csv->addfield($stat['min']);
                $csv->addfield($stat['max']);
                $csv->addfield($stat['avg']);
                $csv->addfield($stat['Q1']);
                $csv->addfield($stat['median']);
                $csv->addfield($stat['Q3']);
                $csv->addfield($stat['truncatedMean']);
                $csv->writerow();
            }
        }
        $csv->close();

        echo '<fieldset class="if_parameters">';
        echo '<legend>User stats</legend>';
        echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename)) . '">' . $filename . '</a></p>';
        echo '</fieldset>';
        /*
          // interface language, user-defined location
          $sql = "SELECT max(t.created_at), t.from_user_id, t.from_user_lang, t.location FROM " . $esc['mysql']['dataset'] . "_tweets t ";
          $sql .= sqlSubset();
          $sql .= "GROUP BY from_user_id";
          $sqlresults = mysql_query($sql);
          $locations = $languages = array();
          while ($res = mysql_fetch_assoc($sqlresults)) {
          $locations[] = $res['location'];
          $languages[] = $res['from_user_lang'];
          }

          $locations = array_count_values($locations);
          arsort($locations);
          $contents = "location,frequency\n";
          foreach ($locations as $location => $frequency)
          $contents .= preg_replace("/[\r\n\s\t,]+/im", " ", trim($location)) . ",$frequency\n";

          file_put_contents($filename_locations, chr(239) . chr(187) . chr(191) . $contents);

          echo '<fieldset class="if_parameters">';
          echo '<legend>Locations </legend>';
          echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_locations)) . '">' . $filename_locations . '</a></p>';
          echo '</fieldset>';

          $languages = array_count_values($languages);
          arsort($languages);
          $contents = "language,frequency\n";
          foreach ($languages as $language => $frequency)
          $contents .= preg_replace("/[\r\n\s\t]+/", "", $language) . ",$frequency\n";

          file_put_contents($filename_languages, chr(239) . chr(187) . chr(191) . $contents);

          echo '<fieldset class="if_parameters">';
          echo '<legend>Languages </legend>';
          echo '<p><a href="' . str_replace("#", urlencode("#"), str_replace("\"", "%22", $filename_languages)) . '">' . $filename_languages . '</a></p>';
          echo '</fieldset>';
         */
        ?>

    </body>
</html>
