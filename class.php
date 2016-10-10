<?php
include("simple_html_dom.php");

function get_html($url, $method='', $vars='') {
    $ch = curl_init();
    if ($method == 'post') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.64 Safari/537.11");
    curl_setopt($ch, CURLOPT_REFERER, $url);
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

$last_class = 1;

$SCRIPT = "#!/bin/bash\nset -e\n\n";

if (is_file("last_class")) {
	$last_class = file_get_contents("last_class");
	if ($last_class!==false && is_numeric($last_class)) {
		$last_class = intval($last_class);
	}
}

get_html:

if ($checked>=$max_check) {
	file_put_contents("last_class", $last_class);
	file_put_contents("bash", $SCRIPT);
	die("The end. Re-run script to continue. Bash script saved.\n");
}

$current_page = "http://linuxcast.net/Users/class_detail/{$last_class}";
echo "Checking {$current_page}...\n";

$ohtml = get_html($current_page);

if ($ohtml == "404") {
	echo "Nothing@ {$current_page}.\n";
	$last_class++;
	$checked++;
	goto get_html;
}

$html = str_get_html($ohtml);

if ($try_log < 1) {
	if (@$html->find("#myvideo", 0)->plaintext == null) {
		echo "Logging in...\n";
		$login = get_html("http://linuxcast.net/Users/navi_attemp_login", "post", http_build_query(array(
			'user[mail]' => 'EMAIL',
			'user[password]' => 'PASSWORD',
			'remember_login' => 'yes'
		)));
		$try_log++;
		goto get_html;
	}
} else {
	die("error logging in.\n");
}

$title = htrim(@$html->find("#course_content", 0)->find("h2", 0)->plaintext);
$description = htrim(@$html->find("#course_content", 0)->find("h6", 0)->plaintext);
$videourl = htrim(@$html->find("#myvideo", 0)->find("source", 0)->src);
$notes = html_entity_decode(str_replace("  ", "\n", trim(@$html->find("div.key_note", 0)->plaintext)), ENT_COMPAT, "UTF-8");

if ($title=="" || $description=="" || $videourl=="") {
	file_put_contents("last_class", $last_class);
	die("exceptions at {$current_page}.\n");
}
$newnotes = "";
if ($notes=="") {
	$notes = "无";
} else {
	$t = 1;
	foreach (explode("\n", $notes) as $k => $v) {
		$v = trim(preg_replace("/ +/", " ", trim($v)));
		if ($v=="") continue;
		$newnotes .= "{$t}.{$v}\\n";
		$t++;
	}
	$newnotes = htrim($newnotes);
}

$basename = basename($videourl);

$SCRIPT .= "axel -a \"{$videourl}\";\n\n(printf \"{$title}\\n{$description}\\n\\n".
"本课知识点：\\n{$newnotes}\\nLinuxCast.net 全方位的Linux学习与交流平台\\n\\n".
"{$current_page}\" | ".
"youtube-upload --email=EMAIL ".
"--password=PASSWORD --category=Education --keywords=Linux ".
"--title=\"{$title} [LinuxCast视频教程]\" ".
"--description=\"$(< /dev/stdin)\" \"{$basename}\" | ".
"sed 's/.*\([-A-Za-z0-9\_]\{11\}\).*/\\1/' - | tr -d \"\\n\" && ".
"printf \" => {$current_page}\\n\") >> ./log && tail -n 1 ./log;\n\n".
"rm -f \"{$basename}\";\n\n".
"(printf \"http://www.youtube.com/watch?v=\" && tail -n 1 ./log | cut -c 1-11) | ".
"youtube-upload --email=EMAIL --password=PASSWORD ".
"--add-to-playlist=http://gdata.youtube.com/feeds/api/playlists/CJcQMZOafICYrx7zhFu_RWHRZqpB8fIW $(< /dev/stdin)\n\n";

$last_class++;
$checked++;
goto get_html;
