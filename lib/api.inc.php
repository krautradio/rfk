<?php

function getDJ(&$out){
    global $db;
    $sql = "SELECT * FROM streamer WHERE status = 'STREAMING' LIMIT 1;";
    $dbres = $db->query($sql);
    if($dbres) {
        $row = $db->fetch($dbres);
        $out['dj'] = $row['username'];
        $out['djid'] = $row['streamer'];
    }
}

/**
 * get the streamer
 */
function getDJInfo(&$out){
    global $db;
    if(isset($_GET['djname'])) {
        $djname = $db->escape($_GET['djname']);
        $sql = "SELECT * FROM streamer WHERE username = '" . $djname . "' LIMIT 1;";
    }
    elseif(isset($_GET['djid'])) {
        $djid = $db->escape($_GET['djid']);
        $sql = "SELECT * FROM streamer WHERE streamer = '" . $djid . "' LIMIT 1;";
    }
    else {
        throw_error(0, 'no djname or djid given');
    }
    $dbres = $db->query($sql);
    if($dbres) {
        $row = $db->fetch($dbres);
        $out['dj'] = $row['username'];
        $out['djid'] = $row['streamer'];
    }
}

function kickDJ(&$out){
    $liquid = new Liquidsoap;
    $liquid->connect();
    $liquid->getHarborSource();
    $liquid->kickHarbor();

    global $db;
    $timestamp = time() + (2 * 60);
    $timestamp = date('Y-m-d H:i:s', $timestamp);
    $sql = "UPDATE streamer SET ban = '". $timestamp . "' WHERE streamer = '". $out['djid'] ."';";
    $dbres = $db->query($sql);
    $out['status'] = 0;
}

function getCurrShow(&$out){
    global $db;
    $sql = 'SELECT `show`, UNIX_TIMESTAMP(begin) as b,UNIX_TIMESTAMP(end) as e,name, description,type, username, streamer, status, thread
            FROM shows
            JOIN streamer USING (streamer)
            WHERE end IS NULL
            OR NOW() between begin AND end;';
    $dbres = $db->query($sql);
    if($dbres && $db->num_rows($dbres) > 0) {
        while($row = $db->fetch($dbres)) {
            if($db->num_rows($dbres) > 1 && $row['type'] == 'PLANNED') {
                $key = 'ushow';
                $out['status'] = 'OVERLAP';
            }else {
                $key = 'show';
                if($out['status'] != 'OVERLAP')
                {
                    $out['status'] = $row['status'];
                }
            }
            $out[$key.'begin'] = (int)$row['b'];
            $out[$key.'end'] = (int)$row['e'];
            $out[$key.'type'] = $row['type'];
            $out[$key.'name'] = $row['name'];
            $out[$key.'description'] = $row['description'];
            $out[$key.'id'] = $row['show'];
            $out[$key.'thread'] = $row['thread'];
            $out[$key.'dj'] = $row['username'];
            $out['showdjid'] = $row['streamer'];
        }
    }
}

function getNextShows(&$out){
    global $db;
    if(isset($_GET['c']) && $_GET['c'] > 1){
        $limit = $_GET['c'];
    }else{
        $limit = 1;
    }
    $sql  = 'SELECT thread,UNIX_TIMESTAMP(begin) as b,UNIX_TIMESTAMP(end) as e, name, description, type, username, streamer
                FROM shows
                JOIN streamer USING (streamer)
                WHERE begin > NOW() ';

    if(isset($_GET['djname']) && strlen($_GET['djname']) > 0) {
        $sql .= 'AND username = "' . $db->escape($_GET['djname']) . '" ';
    }

    $sql .= 'ORDER BY begin ASC
                LIMIT 0,'.$limit;

    $dbres = $db->query($sql);
    if($dbres) {

        while($row = $db->fetch($dbres)) {
            $tmp = array();
            $tmp['showbegin'] = (int)$row['b'];
            $tmp['showend'] = (int)$row['e'];
            $tmp['showtype'] = $row['type'];
            $tmp['showname'] = $row['name'];
            $tmp['showdescription'] = $row['description'];
            $tmp['showdj'] = $row['username'];
            $tmp['showdjid'] = $row['streamer'];
            $tmp['showthread'] = (int)$row['thread'];
            $out['shows'][] = $tmp;
        }
    }
    if(!isset($out['shows'])){
        $out['shows'] = array();
    }

}

function getCurrTrack(&$out) {
    global $db;
    $lasttrack = 0;
    if(isset($_GET['ltid']) && $_GET['ltid'] > 0){
        $lasttrack = $db->escape($_GET['ltid']);
    }
    $sql = "SELECT *
            FROM songhistory
            WHERE end IS NULL
            AND song > ".$lasttrack.";";
    $dbres = $db->query($sql);
    if($dbres) {
        if($row = $db->fetch($dbres)) {
            $out['trackid'] = $row['song'];
            $out['title'] = $row['title'];
            $out['artist'] = $row['artist'];
        }
    }
}

function getListener(&$out){
    global $db;
    $sql = "SELECT name, IF(c IS NULL, 0, c) as c, description
            FROM (SELECT COUNT(*) as c, mount
                  FROM listenerhistory
                  WHERE disconnected IS NULL
                  GROUP BY mount) as c
            RIGHT JOIN mounts USING (mount);";
    $dbres = $db->query($sql);
    if($dbres) {
        while($row = $db->fetch($dbres)) {
            $out['listener'][$row['name']]['description'] = $row['description'];
            $out['listener'][$row['name']]['c'] = $row['c'];
        }
    }
}

function getListenerData(&$out) {
    global $db;
    $sql = "SELECT ip, country, city FROM listenerhistory WHERE disconnected IS NULL;";
    $dbres = $db->query($sql);
    $tmp = array();
    if($dbres) {
        while($row = $db->fetch($dbres)) {
            $location = getLocation($row['ip']);
            $tmp[] = array('ip' => $row['ip'],
                           'country' => $row['country'],
                           'city' => $row['city'],
                           'latitude' => (string)$location['latitude'],
                           'longitude' => (string)$location['longitude']);
        }
    }
    $out['listener'] = $tmp;
}

function getTracks(&$out){
    global $db;
    if(isset($_GET['c']) && $_GET['c'] > 1){
        $limit = $_GET['c'];
    }else{
        $limit = 1;
    }
    $sql = 'SELECT title, artist
            FROM songhistory
            ORDER BY song DESC
            LIMIT 0,'.$limit.';';
    $dbres = $db->query($sql);
    $tmp = array();
    if($dbres) {
        while($row = $db->fetch($dbres)) {
            $tmp[] = array('title' => $row['title'],
                           'artist' => $row['artist']);
        }
    }
    $out['history'] = $tmp;
}

function getTraffic(&$out) {
    //didn't want to include common-functions.inc.php
    $str = file_get_contents('../../var/vnstat');
    $tmp = array();
    if (preg_match('/tx.*?([0-9]+)\\.([0-9]+).*/', $str,$matches)) {
        $tmp['out'] = $matches[1].'.'.$matches[2];
    }
    if (preg_match('/rx.*?([0-9]+)\\.([0-9]+).*/', $str,$matches)) {
        $tmp['in'] = $matches[1].'.'.$matches[2];
    }
    $tmp['sum'] = $tmp['in']+$tmp['out'];

    $out['traffic'] = $tmp;
}

function getCountries(&$out) {
    global $db;

    $sql =  'SELECT c, country
            FROM (SELECT COUNT(*) as c, country
            FROM listenerhistory
            WHERE disconnected IS NULL
            GROUP BY country) as c
            ORDER BY c DESC;';

    $dbres = $db->query($sql);
    $tmp = array();
    if($dbres) {
        while($row = $db->fetch($dbres)) {
            $tmp[] = array('country' => $row['country'],
                           'count' => (int)$row['c']);
        }
    }
    $out['countries'] = $tmp;
}

function authTest(&$out) {
    global $db;
    if(isset($_GET['hostmask']) && strlen($_GET['hostmask']) > 0) {
        $hostmask = explode('!', $_GET['hostmask']);
        $sql = "SELECT streamer, username 
                FROM streamersettings
                JOIN streamer using(streamer)
                WHERE `key` = 'hostmask'
                AND `value` REGEXP '[A-z0-9]+!". $db->escape($hostmask[1]) . "' ;";
        $dbres = $db->query($sql);
        if($dbres) {
            if($row = $db->fetch($dbres)) {
                $out['auth']['nick'] = $row['username'];
                $out['auth']['id'] = $row['streamer'];
                $out['auth']['status'] = 0;
                return;
            }
        }
    }
    $out['auth']['status'] = 1;
}

function authAdd(&$out) {
    global $db;
    
    if((isset($_GET['hostmask']) && strlen($_GET['hostmask']) > 0) && (isset($_GET['user']) && strlen($_GET['user']) > 0) && (isset($_GET['pass']) && strlen($_GET['pass']) > 0)) {
        $sql = "SELECT * FROM streamer WHERE username = '" . $db->escape($_GET['user']) . "' AND streampassword = '" . $db->escape($_GET['pass']) . "';";
        $dbres = $db->query($sql);
        if($dbres && $db->num_rows($dbres) > 0) {
            if($row = $db->fetch($dbres)) {
                $sql = "INSERT INTO streamersettings (streamer,`key`,value)
                        VALUES (" . $row['streamer'] . ",'hostmask','" . $db->escape($_GET['hostmask']) ."')
                        ON DUPLICATE KEY UPDATE value = '" . $db->escape($_GET['hostmask']) . "';";
                if($db->execute($sql)) {
                    $out['auth']['nick'] = $row['username'];
                    $out['auth']['id'] = $row['streamer'];
                    $out['auth']['status'] = 0;
                    return;
                }
            }
        }
    }
    $out['auth']['status'] = 1;
}

function authJoin(&$out) {
    global $db;
    
    if(isset($_GET['hostmask']) && strlen($_GET['hostmask']) > 0) {
        $hostmask = explode('!', $_GET['hostmask']);
        $sql = "SELECT * FROM streamersettings JOIN streamer using(streamer) WHERE `key` = 'hostmask' AND `value` REGEXP '[A-z0-9]+!" . $db->escape($hostmask[1])  . "';";
        $dbres = $db->query($sql);
        if($dbres && $db->num_rows($dbres) > 0) {
            if($row = $db->fetch($dbres)) {
                if($row['hostmask'] != $_GET['hostmask']) {
                    $sql = "INSERT INTO streamersettings (streamer,`key`,value)
                            VALUES (" . $row['streamer'] . ",'hostmask', '" . $db->escape($_GET['hostmask']) . "')
                            ON DUPLICATE KEY UPDATE value = '" . $db->escape($_GET['hostmask']) . "';";
                    $db->execute($sql);
                }
                $sql = "INSERT INTO streamersettings (streamer,`key`,value)
                        VALUES (" . $row['streamer'] . ",'isIRC', 1)
                        ON DUPLICATE KEY UPDATE value = 1;";
                if($db->execute($sql)) {
                    $out['auth']['nick'] = $row['username'];
                    $out['auth']['id'] = $row['streamer'];
                    $out['auth']['status'] = 0;
                    return; 
                }
            }
        }
    }
    $out['auth']['status'] = 1;
}

function authPart(&$out) {
    global $db;
    
    if(isset($_GET['hostmask']) && strlen($_GET['hostmask']) > 0) {
        $hostmask = explode('!', $_GET['hostmask']);
        $sql = "SELECT * FROM streamersettings JOIN streamer using(streamer) WHERE `key` = 'hostmask' AND `value` REGEXP '[A-z0-9]+!" . $db->escape($hostmask[1])  . "';";
        $dbres = $db->query($sql);
        if($dbres && $db->num_rows($dbres) > 0) {
            if($row = $db->fetch($dbres)) {
                $sql = "INSERT INTO streamersettings (streamer,`key`,value)
                        VALUES (" . $row['streamer'] . ",'isIRC', 0)
                        ON DUPLICATE KEY UPDATE value = 0;";
                if($db->execute($sql)) {
                    $out['auth']['nick'] = $row['username'];
                    $out['auth']['id'] = $row['streamer'];
                    $out['auth']['status'] = 0;
                    return; 
                }
            }
        }
    }
    $out['auth']['status'] = 1;
}

function authUpdate(&$out) {
    global $db;
    
    if((isset($_GET['hostmask']) && strlen($_GET['hostmask']) > 0) && (isset($_GET['nick']) && strlen($_GET['nick']) > 0)) {
        $hostmask = explode('!', $_GET['hostmask']);
        $sql = "SELECT * FROM streamersettings JOIN streamer using(streamer) WHERE `key` = 'hostmask' AND `value` REGEXP '[A-z0-9]+!" . $db->escape($hostmask[1])  . "';";
        $dbres = $db->query($sql);
        if($dbres && $db->num_rows($dbres) > 0) {
            if($row = $db->fetch($dbres)) {
                $sql = "INSERT INTO streamersettings (streamer,`key`,value)
                        VALUES (" . $row['streamer'] . ",'hostmask', '" . $db->escape($_GET['nick'] . "!" . $hostmask[1]) . "')
                        ON DUPLICATE KEY UPDATE value = '" . $db->escape($_GET['nick'] . "!" . $hostmask[1]) . "';";
                if($db->execute($sql)) {
                    $out['auth']['nick'] = $row['username'];
                    $out['auth']['id'] = $row['streamer'];
                    $out['auth']['status'] = 0;
                    return; 
                }
            }
        }
    }
    $out['auth']['status'] = 1;
}

function isIRC(&$out) {
    global $db;
    
    if(isset($_GET['djid']) && strlen($_GET['djid']) > 0) {
        $sql = "SELECT * FROM streamersettings JOIN streamer using(streamer) WHERE `key` = 'isIRC' AND value = 1 AND streamer = '" . $db->escape($_GET['djid']) . "';";
        $dbres = $db->query($sql);
        if($dbres && $db->num_rows($dbres) > 0) {
            if($row = $db->fetch($dbres)) {
                $out['auth']['hostmask'] = getHostmask($row['streamer']);
                $out['auth']['nick'] = $row['username'];
                $out['auth']['id'] = $row['streamer'];
                $out['auth']['status'] = 0;
                return;
            }
        }
    }
    $out['auth']['status'] = 1;
}

function getHostmask($djid) {
    global $db;
    if(isset($djid)) {
        $sql = "SELECT * FROM streamersettings JOIN streamer using(streamer) WHERE `key` = 'hostmask' AND streamer = '" . $db->escape($djid) . "';";
        $dbres = $db->query($sql);
        if($dbres && $db->num_rows($dbres) > 0) {
            if($row = $db->fetch($dbres)) {
                return $row['value'];
            }
        }
    }
}

function setIRCCount(&$out) {
    if(isset($_GET['c']) && (is_int((int)$_GET['c']))) {
        if(file_put_contents('../../var/irccount', (int)$_GET['c']));
        $out['status'] = 0;
    }
    else {
        $out['status'] = 1;
    }
}

function getIRCCount() {
    return (int)file_get_contents('../../var/irccount');
}

?>