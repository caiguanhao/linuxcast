<?php
include("simple_html_dom.php");

function get_html($url, $method='', $vars='') {
    $ch = curl_init();
    if ($method == 'post') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies');
    $buffer = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE)!=200)
		$buffer = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $buffer;
}
function htrim($str) {
	$str = trim($str);
	$str = str_replace("<", "＜", $str);
	$str = str_replace(">", "＞", $str);
	return $str;
}

$try_log = 0;

$max_check = 20;

$checked = 0;

$last_cast = 1;

$SCRIPT = "#!/bin/bash\nset -e\n\n";

if (is_file("last_cast")) {
	$last_cast = file_get_contents("last_cast");
	if ($last_cast!==false && is_numeric($last_cast)) {
		$last_cast = intval($last_cast);
	}
}

$descs = array();

echo "Fetching descriptions...\n";
$dID = 1;

get_desc:

$dPAGE = "http://linuxcast.net/public/cast?page={$dID}";
echo "Checking {$dPAGE}...\n";
$dHTML = str_get_html(get_html($dPAGE));
if (@$dHTML->find(".cast_pre_detail", 0)->plaintext!=null) {
	foreach (@$dHTML->find(".cast_pre_detail") as $detail) {
		preg_match("/\d+$/", htrim(@$detail->find("h3", 0)->find("a", 0)->href), $XMAT);
		$descs["s{$XMAT[0]}"] = htrim(@$detail->find("blockquote", 0)->plaintext);
	}
	$dID++;
	goto get_desc;
} else {
	echo "Nothing@ {$dPAGE}...\n";
}

get_html:

if ($checked>=$max_check) {
	file_put_contents("last_cast", $last_cast);
	file_put_contents("bash", $SCRIPT);
	die("The end. Re-run script to continue. Bash script saved.\n");
}

$current_page = "http://linuxcast.net/public/cast_show/{$last_cast}";
echo "Checking {$current_page}...\n";

$ohtml = get_html($current_page);

if ($ohtml == "404") {
	echo "Nothing@ {$current_page}.\n";
	$last_cast++;
	$checked++;
	goto get_html;
}

$html = str_get_html($ohtml);

if ($try_log < 1) {
	if (@$html->find("#myvideo", 0)->plaintext == null) {
		echo "Logging in...\n";
		$login = get_html("http://linuxcast.net/Users/attemp_login", "post", array(
			'user[mail]' => 'EMAIL',
			'user[password]' => 'PASSWORD',
			'remember_login' => 'yes'
		));
		$try_log++;
		goto get_html;
	}
} else {
	die("error logging in.\n");
}

$title = htrim(@$html->find("#cast_show_wrapper", 0)->find("h1", 0)->plaintext);
$description = $descs["s{$last_cast}"];
$videourl = htrim(@$html->find("#myvideo", 0)->find("source", 0)->src);

if ($title=="" || $description=="" || $videourl=="") {
	file_put_contents("last_cast", $last_cast);
	die("exceptions at {$current_page}.\n");
}

$basename = basename($videourl);

$SCRIPT .= "axel -a \"{$videourl}\";\n\n(printf \"{$title}\\n{$description}\\n\\n".
"LinuxCast.net 视频教程 全方位的Linux学习与交流平台\\n\\n".
"{$current_page}\" | ".
"youtube-upload --email=EMAIL ".
"--password=PASSWORD --category=Education --keywords=Linux ".
"--title=\"{$title} [LinuxCast IT播客]\" ".
"--description=\"$(< /dev/stdin)\" \"{$basename}\" | ".
"sed 's/.*\([-A-Za-z0-9\_]\{11\}\).*/\\1/' - | tr -d \"\\n\" && ".
"printf \" => {$current_page}\\n\") >> ./log && tail -n 1 ./log;\n\n".
"rm -f \"{$basename}\";\n\n".
"(printf \"http://www.youtube.com/watch?v=\" && tail -n 1 ./log | cut -c 1-11) | ".
"youtube-upload --email=EMAIL --password=PASSWORD ".
"--add-to-playlist=http://gdata.youtube.com/feeds/api/playlists/CJcQMZOafIBu-W-QI163AoCpla5NGPww $(< /dev/stdin)\n\n";

$last_cast++;
$checked++;
goto get_html;
