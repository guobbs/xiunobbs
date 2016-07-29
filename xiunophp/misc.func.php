<?php


// 此处不利于 HHVM，应该强制要求 APP 定义 message 函数，为了正确性，暂时如此。
function xn_message($code, $message) {
	global $ajax;
	echo $ajax ? xn_json_encode(array('code'=>$code, 'message'=>$message)) : $message;
	exit;
}

function xn_log_post_data() {
	global $method;
	if($method != 'POST') return;
	$post = $_POST;
	isset($post['password']) AND $post['password'] = '******'; 		// 干掉密码信息
	isset($post['password_new']) AND $post['password_new'] = '******'; 	// 干掉密码信息
	isset($post['password_old']) AND $post['password_old'] = '******'; 	// 干掉密码信息

	xn_log(xn_json_encode($post), 'post_data');
}

// 中断流程很危险！可能会导致数据问题，线上模式不允许中断流程！
function error_handle($errno, $errstr, $errfile, $errline) {
	global $time, $ajax;
	$br = ($ajax ? "\n" : "<br>\n");
	IN_CMD AND $errstr = str_replace('<br>', "\n", $errstr);
	$s = $br."Error[$errno]: $errstr, File: $errfile, Line: $errline";
	xn_log($s, 'php_error'); // 所有PHP错误报告都记录日志
	if(DEBUG) {
		// 如果放在 register_shutdown_function 里面，文件句柄会被关闭，然后这里就写入不了文件了！
		// if(strpos($s, 'error_log(') !== FALSE) return TRUE;
		echo $s.$br;
		$arr = debug_backtrace();
		array_shift($arr);
		foreach($arr as $v) {
			$args = '';
			if(is_array($v['args'])) foreach ($v['args'] as $v2) $args .= ($args ? ' , ' : '').(is_array($v2) ? 'array('.count($v2).')' : $v2);
			echo $br."File: $v[file], Line: $v[line], $v[function]($args) ";
		}
		echo $br;
		return TRUE;
	} else {
		return FALSE;
	}
	// true 表示不执行 PHP 内部错误处理程序, false 表示执行PHP默认处理
	//return DEBUG ? FALSE : TRUE;
}

// 使用全局变量记录错误信息
function xn_error($no, $str, $return = FALSE) {
	global $errno, $errstr;
	$errno = $no;
	$errstr = $str;
	return $return;
}

/*
	param(1);
	param(1, '');
	param(1, 0);
	param(1, array());
	param(1, array(''));
	param(1, array(0));
*/
function param($key, $defval = '', $safe = TRUE) {
	if(!isset($_REQUEST[$key]) || ($key === 0 && empty($_REQUEST[$key]))) {
		if(is_array($defval)) {
			return array();
		} else {
			return $defval;
		}
	}
	$val = $_REQUEST[$key];
	$val = param_force($val, $defval, $safe);
	return $val;
}

/*
	仅支持一维数组的类型强制转换。
	param_force($val);
	param_force($val, '');
	param_force($val, 0);
	param_force($arr, array());
	param_force($arr, array(''));
	param_force($arr, array(0));
*/
function param_force($val, $defval, $safe = TRUE) {
	global $get_magic_quotes_gpc;
	if(is_array($defval)) {
		$defval = empty($defval) ? '' : $defval[0]; // 数组的第一个元素，如果没有则为空字符串
		if(is_array($val)) {
			foreach($val as &$v) {
				if(is_array($v)) {
					$v = $defval;
				} else {
					if(is_string($defval)) {
						//$v = trim($v);
						$safe AND !$get_magic_quotes_gpc && $v = addslashes($v);
						!$safe AND $get_magic_quotes_gpc && $v = stripslashes($v);
						$safe AND $v = htmlspecialchars($v);
					} else {
						$v = intval($v);
					}
				}
			}
		} else {
			return array();
		}
	} else {
		if(is_array($val)) {
			$val = $defval;
		} else {
			if(is_string($defval)) {
				//$val = trim($val);
				$safe AND !$get_magic_quotes_gpc && $val = addslashes($val);
				!$safe AND $get_magic_quotes_gpc && $val = stripslashes($val);
				$safe AND $val = htmlspecialchars($val);
			} else {
				$val = intval($val);
			}
		}
	}
	return $val;
}

/*
	lang('mobile_length_error');
	lang('mobile_length_error', array('mobile'=>$mobile));
*/
function lang($key, $arr = array()) {
	global $lang;
	if(!isset($lang[$key])) return 'lang['.$key.']';
	$s = $lang[$key];
	if(!empty($arr)) {
		foreach($arr as $k=>$v) {
			$s = str_replace('$'.$k, $v, $s);
		}
	}
	return $s;
}

function jump($message, $url = '', $delay = 3) {
	global $ajax;
	if($ajax) return $message;
	if(!$url) return $message;
	$htmladd = '<script>setTimeout(function() {window.location=\''.$url.'\'}, '.($delay * 1000).');</script>';
	return '<a href="'.$url.'">'.$message.'</a>'.$htmladd;
}

function xn_strlen($s) {
	return mb_strlen($s, 'UTF-8');
}

function xn_substr($s, $start, $len) {
	return mb_substr($s, $start, $len, 'UTF-8');
}

// txt 转换到 html
function xn_txt_to_html($s) {
	$s = htmlspecialchars($s);
	$s = str_replace("\t", ' &nbsp; &nbsp; &nbsp; &nbsp;', $s);
	$s = str_replace("\r\n", '<br>', $s);
	return $s;
}

function xn_urlencode($s) {
	$s = str_replace('-', '_2d', $s);
	$s = str_replace('.', '_2e', $s);
	$s = str_replace('+', '_2b', $s);
	$s = str_replace('=', '_3d', $s);
	$s = urlencode($s);
	$s = str_replace('%', '_', $s);
	return $s;
}

function xn_urldecode($s) {
	$s = str_replace('_', '%', $s);
	$s = urldecode($s);
	return $s;
}

function xn_json_encode($arg) {
	$r = '';
	switch (gettype($arg)) {
		case 'array':
			$r = is_number_array($arg) ? xn_json_number_array_to_string($arg) : xn_json_assoc_array_to_string($arg);
		break;
		case 'object':
			return xn_json_encode(get_object_vars($arg));
		break;
		case 'integer':
		case 'double':
			$r = is_numeric($arg) ? (string)$arg : 'null';
		break;
		case 'string':
		$r = '"' . strtr($arg, array(
			"\r"   => '\\r',    "\n"   => '\\n',    "\t"   => '\\t',     "\b"   => '\\b',
			"\f"   => '\\f',    '\\'   => '\\\\',   '"'    => '\"',
			"\x00" => '\u0000', "\x01" => '\u0001', "\x02" => '\u0002', "\x03" => '\u0003',
			"\x04" => '\u0004', "\x05" => '\u0005', "\x06" => '\u0006', "\x07" => '\u0007',
			"\x08" => '\b',     "\x0b" => '\u000b', "\x0c" => '\f',     "\x0e" => '\u000e',
			"\x0f" => '\u000f', "\x10" => '\u0010', "\x11" => '\u0011', "\x12" => '\u0012',
			"\x13" => '\u0013', "\x14" => '\u0014', "\x15" => '\u0015', "\x16" => '\u0016',
			"\x17" => '\u0017', "\x18" => '\u0018', "\x19" => '\u0019', "\x1a" => '\u001a',
			"\x1b" => '\u001b', "\x1c" => '\u001c', "\x1d" => '\u001d', "\x1e" => '\u001e',
			"\x1f" => '\u001f'
			)) . '"';
		break;
		case 'boolean':
			$r = $arg ? 1 : 0;
		break;
		default:
			$r = 'null';
	}
	return $r;
}

function xn_json_number_array_to_string($arr) {
	$s = '';
	foreach ($arr as $k=>$v) {
		$s .= ','.xn_json_encode($v);
	}
	$s = substr($s, 1);
	$r = '['.$s.']';
	return $r;
}

function xn_json_assoc_array_to_string($arr) {
	$s = '';
	foreach ($arr as $k=>$v) {
		$s .= ',"'.$k.'":'.xn_json_encode($v);
	}
	$s = substr($s, 1);
	$r = '{'.$s.'}';
	return $r;
}
function is_number_array($arr) {
	$i = 0;
	foreach ($arr as $k=>$v) {
		if(!is_numeric($k) || $k != $i++) return FALSE; // 如果从0 开始，并且连续，则为数字数组
	}
	return TRUE;
}
function xn_json_decode($json) {
	return json_decode($json, 1);
}

/*
// 此函数太耗费资源已经废弃。
function xn_json_encode($json) {
	if(version_compare(PHP_VERSION, '5.4.0') == 1) {
		return json_encode($json, JSON_UNESCAPED_UNICODE);
	} else {
		$json = json_encode($json);
		return ucs2_to_utf8($json);
	}
}
// 此函数仅仅在工具中使用！不允许在主程序调用。不利于APC，并且可能有安全问题。
function ucs2_to_utf8($s) {
	$s = preg_replace("#\\\u([0-9a-f]+)#ie", "iconv('UCS-2', 'UTF-8', pack('H4', '\\1'))", $s);
	return $s;
}
*/


// ---------------------> encrypt function end

// bootstrap 翻页，命名与 bootstrap 保持一致
function pagination($url, $totalnum, $page, $pagesize = 20) {
	$totalpage = ceil($totalnum / $pagesize);
	if($totalpage < 2) return '';
	$page = min($totalpage, $page);
	$shownum = 5;	// 显示多少个页 * 2

	$start = max(1, $page - $shownum);
	$end = min($totalpage, $page + $shownum);

	// 不足 $shownum，补全左右两侧
	$right = $page + $shownum - $totalpage;
	$right > 0 && $start = max(1, $start -= $right);
	$left = $page - $shownum;
	$left < 0 && $end = min($totalpage, $end -= $left);

	//$s = '<ul class="pagination">';
	$s = '';
	$page != 1 && $s .= '<li class="page-item"><a href="'.str_replace('{page}', $page-1, $url).'" class="page-link">◀</a></li>'."\r\n";
	if($start > 1) $s .= '<li class="page-item"><a href="'.str_replace('{page}', 1, $url).'">1 '.($start > 2 ? '... ' : '').'</a></li>'."\r\n";
	for($i=$start; $i<=$end; $i++) {
		if($i == $page) {
			$s .= '<li class="page-item"><a href="'.str_replace('{page}', $i, $url).'" class="page-link active">'.$i.'</a></li>'."\r\n";// active
		} else {
			$s .= '<li class="page-item"><a href="'.str_replace('{page}', $i, $url).'" class="page-link">'.$i.'</a></li>'."\r\n";
		}
	}
	if($end != $totalpage) $s .= '<li class="page-item"><a href="'.str_replace('{page}', $totalpage, $url).'" class="page-link">'.($totalpage - $end > 1 ? '... ' : '').$totalpage.'</a></li>'."\r\n";
	$page != $totalpage && $s .= '<li class="page-item"><a href="'.str_replace('{page}', $page+1, $url).'" class="page-link">▶</a></li>'."\r\n";
	//$s .= '</ul>';
	return $s;
}

// 简单的上一页，下一页，比较省资源，不用count(), 推荐使用，命名与 bootstrap 保持一致
function pager($url, $totalnum, $page, $pagesize = 20) {
	$totalpage = ceil($totalnum / $pagesize);
	if($totalpage < 2) return '';
	$page = min($totalpage, $page);

	$s = '';
	$page > 1 AND $s .= '<li><a href="'.str_replace('{page}', $page-1, $url).'">上一页</a></li>';
	$s .= " $page / $totalpage ";
	$totalnum >= $pagesize AND $page != $totalpage AND $s .= '<li><a href="'.str_replace('{page}', $page+1, $url).'">下一页</a></li>';
	return $s;
}

/*
// 用例：pages('user-list-{page}.htm', 100, 10, 5);
function pages($url, $totalnum, $page, $pagesize = 20) {
	$totalpage = ceil($totalnum / $pagesize);
	if($totalpage < 2) return '';
	$page = min($totalpage, $page);
	$shownum = 5;	// 显示多少个页 * 2

	$start = max(1, $page - $shownum);
	$end = min($totalpage, $page + $shownum);

	// 不足 $shownum，补全左右两侧
	$right = $page + $shownum - $totalpage;
	$right > 0 && $start = max(1, $start -= $right);
	$left = $page - $shownum;
	$left < 0 && $end = min($totalpage, $end -= $left);

	$s = '';
	$page != 1 && $s .= '<a href="'.str_replace('{page}', $page-1, $url).'">◀</a>';
	if($start > 1) $s .= '<a href="'.str_replace('{page}', 1, $url).'">1 '.($start > 2 ? '... ' : '').'</a>';
	for($i=$start; $i<=$end; $i++) {
		if($i == $page) {
			$s .= '<a href="'.str_replace('{page}', $i, $url).'" class="active">'.$i.'</a>';// active
		} else {
			$s .= '<a href="'.str_replace('{page}', $i, $url).'">'.$i.'</a>';
		}
	}
	if($end != $totalpage) $s .= '<a href="'.str_replace('{page}', $totalpage, $url).'">'.($totalpage - $end > 1 ? '... ' : '').$totalpage.'</a>';
	$page != $totalpage && $s .= '<a href="'.str_replace('{page}', $page+1, $url).'">▶</a>';
	return $s;
}

// 简单的上一页，下一页，比较省资源，不用count(), 推荐使用。
function simple_pages($url, $totalnum, $page, $pagesize = 20) {
	$totalpage = ceil($totalnum / $pagesize);
	if($totalpage < 2) return '';
	$page = min($totalpage, $page);

	$s = '';
	$page > 1 AND $s .= '<a href="'.str_replace('{page}', $page-1, $url).'">上一页</a>';
	$s .= " $page / $totalpage ";
	$totalnum >= $pagesize AND $page != $totalpage AND $s .= '<a href="'.str_replace('{page}', $page+1, $url).'">下一页</a>';
	return $s;
}
*/

function page($page, $n, $pagesize) {
	$total = ceil($n / $pagesize);
	$total < 1 AND $total = 1;
	return mid($page, 1, $total);
}

function mid($n, $min, $max) {
	if($n < $min) return $min;
	if($n > $max) return $max;
	return $n;
}

function humandate($timestamp) {
	global $time;
	$seconds = $time - $timestamp;
	if($seconds > 31536000) {
		return date('Y-n-j', $timestamp);
	} elseif($seconds > 2592000) {
		return floor($seconds / 2592000).'月前';
	} elseif($seconds > 86400) {
		return floor($seconds / 86400).'天前';
	} elseif($seconds > 3600) {
		return floor($seconds / 3600).'小时前';
	} elseif($seconds > 60) {
		return floor($seconds / 60).'分钟前';
	} else {
		return $seconds.'秒前';
	}
}

function humannumber($num) {
	$num > 100000 && $num = ceil($num / 10000).'万';
	return $num;
}

function humansize($num) {
	if($num > 1073741824) {
		return number_format($num / 1073741824, 2, '.', '').'G';
	} elseif($num > 1048576) {
		return number_format($num / 1048576, 2, '.', '').'M';
	} elseif($num > 1024) {
		return number_format($num / 1024, 2, '.', '').'K';
	} else {
		return $num.'B';
	}
}

// 不安全的获取 IP 方式，在开启 CDN 的时候，如果被人猜到真实 IP，则可以伪造。
function ip() {
	global $conf;
	$ip = '127.0.0.1';
	if(empty($conf['cdn_on'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
	} else {
		if(isset($_SERVER['HTTP_CDN_SRC_IP'])) {
			$ip = $_SERVER['HTTP_CDN_SRC_IP'];
		} elseif(isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			$arr = array_filter(explode(',', $ip));
			$ip = end($arr);
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
	}
	return long2ip(ip2long($ip));
}

// 安全获取用户IP，信任 CDN 发过来的 X-FORWARDED-FOR
/*
function ip() {
	global $conf;
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1'; // 如果有 CDN 的时候，为离服务器最近的 IP
	if(empty($conf['cdn_ip']) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return $ip;
	} else {
		// 判断 cdnip 合法性，严格过滤 HTTP_X_FORWARDED_FOR
		// X-Forwarded-For: client1, proxy1, proxy2, ...
		// 离服务器最最近的为最后一个 proxy2，应该在 $conf['cdn_ip'] 当中才安全可信
		foreach($conf['cdn_ip'] as $cdnip) {
			$pos1 = strrpos($cdnip, '.');
			$pos2 = strrpos($ip, '.');
			// 合法 CDN IP 段
			if($ip == $cdnip || ($pos1 == $pos2 && substr($cdnip, $pos1) == '.*' && substr($cdnip, 0, $pos1) == substr($ip, 0, $pos2))) {
				$userips = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['HTTP_X_REAL_IP'];
				if(empty($userips)) return $ip; // 此处 CDN 未转发 userip，有错误，可能需要记录日志
				$arr = array_values(array_filter(explode(',', $userips)));
				return long2ip(ip2long(end($arr)));
			}
		}
		return $ip;
	}
}
*/

// 日志记录
function xn_log($s, $file = 'error') {
	global $time, $ip, $conf;
	$uid = intval(G('uid'));
	$day = date('Ymd', $time);
	$mtime = date('Y-m-d H:i:s'); // 默认值为 time()
	$url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	$logpath = $conf['log_path'].$day;
	!is_dir($logpath) AND mkdir($logpath, 0777, true);

	$s = str_replace(array("\r\n", "\n", "\t"), ' ', $s);
	$s = "<?php exit;?>\t$mtime\t$ip\t$url\t$uid\t$s\r\n";

	@error_log($s, 3, $logpath."/$file.php");
}

/*
	中国国情下的判断浏览器类型，简直就是五代十国，乱七八糟，对博主的收集表示感谢

	参考：
	http://www.cnblogs.com/wangchao928/p/4166805.html
	http://www.useragentstring.com/pages/Internet%20Explorer/
	https://github.com/serbanghita/Mobile-Detect/blob/master/Mobile_Detect.php

	Mozilla/4.0 (compatible; MSIE 5.0; Windows NT)
	Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)
	Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.2)
	Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)

	Win7+ie9：
	Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0; .NET CLR 2.0.50727; SLCC2; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; Tablet PC 2.0; .NET4.0E)

	win7+ie11，模拟 78910 头是一样的
	mozilla/5.0 (windows nt 6.1; wow64; trident/7.0; rv:11.0) like gecko

	Win7+ie8：
	Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; InfoPath.3)

	WinXP+ie8：
	Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; GTB7.0)

	WinXP+ie7：
	Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)

	WinXP+ie6：
	Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)

	傲游3.1.7在Win7+ie9,高速模式:
	Mozilla/5.0 (Windows; U; Windows NT 6.1; ) AppleWebKit/534.12 (KHTML, like Gecko) Maxthon/3.0 Safari/534.12

	傲游3.1.7在Win7+ie9,IE内核兼容模式:
	Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E)

	搜狗
	搜狗3.0在Win7+ie9,IE内核兼容模式:
	Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)

	搜狗3.0在Win7+ie9,高速模式:
	Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.3 (KHTML, like Gecko) Chrome/6.0.472.33 Safari/534.3 SE 2.X MetaSr 1.0

	360
	360浏览器3.0在Win7+ie9:
	Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E)

	QQ 浏览器
	QQ 浏览器6.9(11079)在Win7+ie9,极速模式:
	Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/13.0.782.41 Safari/535.1 QQBrowser/6.9.11079.201

	QQ浏览器6.9(11079)在Win7+ie9,IE内核兼容模式:
	Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E) QQBrowser/6.9.11079.201

	阿云浏览器
	阿云浏览器 1.3.0.1724 Beta 在Win7+ie9:
	Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)

	MIUI V5
	Mozilla/5.0 (Linux; U; Android <android-version>; <location>; <MODEL> Build/<ProductLine>) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30 XiaoMi/MiuiBrowser/1.0
*/
function get__browser() {
	// 默认为 chrome 标准浏览器
	$browser = array(
		'device'=>'pc', // pc|mobile|pad
		'name'=>'chrome', // chrome|firefox|ie|opera
		'version'=>30,
	);
	$agent = _SERVER('HTTP_USER_AGENT');
	// 主要判断是否为垃圾 IE6789
	if(strpos($agent, 'msie') !== FALSE || stripos($agent, 'trident') !== FALSE) {
		$browser['name'] = 'ie';
		$browser['version'] = 8;
		preg_match('#msie\s*([\d\.]+)#is', $agent, $m);
		if(!empty($m[1])) {
			if(strpos($agent, 'compatible; msie 7.0;') !== FALSE) {
				$browser['version'] = 8;
			} else {
				$browser['version'] = intval($m[1]);
			}
		} else {
			// 匹配兼容模式 Trident/7.0，兼容模式下会有此标志 $trident = 7;
			preg_match('#Trident/([\d\.]+)#is', $agent, $m);
			if(!empty($m[1])) {
				$trident = intval($m[1]);
				$trident == 4 AND $browser['version'] = 8;
				$trident == 5 AND $browser['version'] = 9;
				$trident > 5 AND $browser['version'] = 10;
			}
		}
	}

	if(isset($_SERVER['HTTP_X_WAP_PROFILE']) || (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap") || stripos($agent, 'phone')  || stripos($agent, 'mobile') || strpos($agent, 'ipod'))) {
		$browser['device'] = 'mobile';
	} elseif(strpos($agent, 'pad') !== FALSE) {
		$browser['device'] = 'pad';
		$browser['name'] = '';
		$browser['version'] = '';
	/*
	} elseif(strpos($agent, 'miui') !== FALSE) {
		$browser['device'] = 'mobile';
		$browser['name'] = 'xiaomi';
		$browser['version'] = '';
	*/
	} else {
		$robots = array('bot', 'spider', 'slurp');
		foreach($robots as $robot) {
			if(strpos($agent, $robot) !== FALSE) {
				$browser['name'] = 'robot';
				return $browser;
			}
		}
	}
	return $browser;
}

function check_browser($browser) {
	if($browser['name'] == 'ie' && $browser['version'] < 8) {
		include './view/htm/browser.htm';
		exit;
	}
}

function is_robot() {
	$agent = _SERVER('HTTP_USER_AGENT');
	$robots = array('bot', 'spider', 'slurp');
	foreach($robots as $robot) {
		if(strpos($agent, $robot) !== FALSE) {
			return TRUE;
		}
	}
	return FALSE;
}

function browser_lang() {
	// return 'zh-cn';
	$accept = _SERVER('HTTP_ACCEPT_LANGUAGE');
	$accept = substr($accept, 0, strpos($accept, ';'));
	if(strpos($accept, 'ko-kr') !== FALSE) {
		return 'ko-kr';
	// } elseif(strpos($accept, 'en') !== FALSE) {
	// 	return 'en';
	} else {
		return 'zh-cn';
	}
}

/**
 * URL format: http://www.domain.com/demo/?user-login.htm?a=b&c=d
 * URL format: http://www.domain.com/demo/user-login.htm?a=b&c=d
 * array(
 *     0 => user,
 *     1 => login
 *     a => b
 *     c => d
 * )
 */
function xn_init_query_string() {
	
	!empty($_SERVER['HTTP_X_REWRITE_URL']) AND $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
	!isset($_SERVER['REQUEST_URI']) AND $_SERVER['REQUEST_URI'] = '';
	
	// 兼容 iis6
	$_SERVER['REQUEST_URI'] = str_replace('/index.php?', '/', $_SERVER['REQUEST_URI']);
	// 处理: /demo/?user-login.htm?a=b&c=d
	// 结果：/demo/user-login.htm?a=b&c=d
	$request_url = str_replace('/?', '/', $_SERVER['REQUEST_URI']);
	$arr = parse_url($request_url);
	
	$q = $arr['path'];
	$pos = strrpos($q, '/');
	$pos === FALSE && $pos = -1;
	$q = substr($q, $pos + 1); // 截取最后一个 / 后面的内容
	if(substr($q, -4) == '.htm') $q = substr($q, 0, -4);
	$r = $q ? (array)explode('-', $q) : array();

	// 将 xxx.htm?a=b&c=d 后面的正常的 _GET 放到 $_SERVER['_GET']
	if(!empty($arr['query'])) {
		parse_str($arr['query'], $arr2);
		$_SERVER['_GET'] = $arr2;
		$r += $arr2;
	}
	
	$_SERVER['REQUEST_URI_NO_PATH'] = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1);
	
	// 是否开启 /user/login 这种格式的 URL
	global $conf;
	if(!empty($conf['url_rewrite_on']) && $conf['url_rewrite_on'] == 3) {
		$r = xn_init_query_string_by_path_formt($_SERVER['REQUEST_URI']) + $r;
	}

	isset($r[0]) AND $r[0] == 'index.php' AND $r[0] = 'index';
	return $r;
}

/**
 * 支持 URL format: http://www.domain.com/user/login?a=1&b=2
 * array(
 *     0 => user,
 *     1 => login,
 *     a => 1,
 *     b => 2
 * )
 */
function xn_init_query_string_by_path_formt($s) {
	$get = array();
	substr($s, 0, 1) == '/' AND $s = substr($s, 1);
	$arr = explode('/', $s);
	$get = $arr;
	$last = array_pop($arr);
	if(strpos($last, '?') !== FALSE) {
		$get = $arr;
		$arr1 = explode('?', $last);
		parse_str($arr1[1], $arr2);
		$get[] = $arr1[0];
		$get = array_merge($get, $arr2);
	}
	return $get;
}

// 安全请求一个 URL
// ini_set('default_socket_timeout', 60);
function http_get($url, $timeout = 5, $times = 3) {
//	$arr = array(
//			'ssl' => array (
//			'verify_peer'   => TRUE,
//			'cafile'        => './cacert.pem',
//			'verify_depth'  => 5,
//			'method'  	=> 'GET',
//			'timeout'  	=> $timeout,
//			'CN_match'      => 'secure.example.com'
//		)
//	);
	$arr = array(
		'http' => array(
			'method'=> 'GET',
			'timeout' => $timeout
		)
	);
	$stream = stream_context_create($arr);
	while($times-- > 0) {
		$s = file_get_contents($url, NULL, $stream, 0, 4096000);
		if($s !== FALSE) return $s;
	}
	return FALSE;
}

function http_post($url, $post = '', $timeout = 10, $times = 3) {
	$stream = stream_context_create(array('http' => array('header' => "Content-type: application/x-www-form-urlencoded\r\nx-requested-with: XMLHttpRequest", 'method' => 'POST', 'content' => $post, 'timeout' => $timeout)));
	while($times-- > 0) {
		$s = file_get_contents($url, NULL, $stream, 0, 4096000);
		if($s !== FALSE) return $s;
	}
	return FALSE;
}

function https_get($url, $timeout=30, $cookie = '') {
	return https_post($url, $timeout, '', $cookie);
}

function https_post($url, $timeout=30, $post = '', $cookie = '') {
	$w = stream_get_wrappers();
	$allow_url_fopen = strtolower(ini_get('allow_url_fopen'));
	$allow_url_fopen = (empty($allow_url_fopen) || $allow_url_fopen == 'off') ? 0 : 1;
	if(extension_loaded('openssl') && in_array('https', $w) && $allow_url_fopen) {
		return file_get_contents($url);
	} elseif (!function_exists('curl_init')) {
		return xn_error(-1, 'server not installed curl.');
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 2); // 1/2
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在，默认可以省略
	if($post) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	if($cookie) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: $cookie"));
	}
	(!ini_get('safe_mode') && !ini_get('open_basedir')) && curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转, 安全模式不允许
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	$data = curl_exec($ch);
	if(curl_errno($ch)) {
		return xn_error(-1, 'Errno'.curl_error($ch));
	}
	if(!$data) {
		curl_close($ch);
		return '';
	}

	list($header, $data) = explode("\r\n\r\n", $data);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if($http_code == 301 || $http_code == 302) {
		$matches = array();
		preg_match('/Location:(.*?)\n/', $header, $matches);
		$url = trim(array_pop($matches));
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$data = curl_exec($ch);
	}
	curl_close($ch);
	return $data;
}

// 多线程抓取数据，需要CURL支持，一般在命令行下执行，此函数收集于互联网，由 xiuno 整理。
function http_multi_get($urls) {
	// 如果不支持，则转为单线程顺序抓取
	if(!function_exists('curl_multi_init')) {
		$data = array();
		foreach($urls as $k=>$url) {
			$data[$k] = https_get($url);
		}
		return $data;
	}

	$multi_handle = curl_multi_init();
	foreach ($urls as $i => $url) {
		$conn[$i] = curl_init($url);
		curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
		$timeout = 3;
		curl_setopt($conn[$i], CURLOPT_CONNECTTIMEOUT, $timeout); // 超时 seconds
		curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, 1);
		//curl_easy_setopt(curl, CURLOPT_NOSIGNAL, 1);
		curl_multi_add_handle($multi_handle, $conn[$i]);
	}
	do{
		$mrc = curl_multi_exec($multi_handle, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);
	while($active and $mrc == CURLM_OK) {
		if(curl_multi_select($multi_handle) != - 1) {
			do{
				$mrc = curl_multi_exec($multi_handle, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
	}
	foreach($urls as $i => $url) {
		$data[$i] = curl_multi_getcontent($conn[$i]);
		curl_multi_remove_handle($multi_handle, $conn[$i]);
		curl_close($conn[$i]);
	}
	return $data;
}

function file_get_content_try($file, $times = 3) {
	while($times-- > 0) {
		$fp = fopen($file, 'rb');
		if($fp) {
			$size = filesize($file);
			if($size == 0) return '';
			$s = fread($fp, $size);
			fclose($fp);
			return $s;
		} else {
			sleep(1);
		}
	}
	return FALSE;
}

function file_put_content_try($file, $s, $times = 3) {
	while($times-- > 0) {
		$fp = fopen($file, 'wb');
		if($fp AND flock($fp, LOCK_EX)){
			$n = fwrite($fp, $s);
			version_compare(PHP_VERSION, '5.3.2', '>=') AND flock($fp, LOCK_UN);
			fclose($fp);
			clearstatcache();
			return $n;
		} else {
			sleep(1);
		}
	}
	return FALSE;
}

// 判断一个字符串是否在另外一个字符串里面，分隔符 ,
function in_string($s, $str) {
	if(!$s || !$str) return FALSE;
	$s = ",$s,";
	$str = ",$str,";
	return strpos($str, $s) !== FALSE;
}

function move_upload_file($srcfile, $destfile) {
	//$r = move_uploaded_file($srcfile, $destfile);
	$r = copy($srcfile, $destfile);
	return $r;
}

// 文件后缀名，不包含 .
function file_ext($filename, $max = 16) {
	$ext = strtolower(substr(strrchr($filename, '.'), 1));
	$ext = xn_urlencode($ext);
	strlen($ext) > $max AND $ext = substr($filename, 0, $max);
	return $ext;
}

// 文件的前缀，不包含 .
function file_pre($filename, $max = 32) {
	return substr($filename, 0, strrpos($filename, '.'));
}

// 获取路径中的文件名
function file_name($path) {
	return substr($path, strrpos($path, '/') + 1);
}

// 在 header 头中发送DEBUG信息
function t($name = '') {
	global $starttime;
	header("Time $name:".substr(microtime(1) - $starttime, 0, 7));
}

// 获取 http://xxx.com/path/
function http_url_path() {
	$port = $_SERVER['SERVER_PORT'];
	//$portadd = ($port == 80 ? '' : ':'.$port);
	$host = $_SERVER['HTTP_HOST'];  // host 里包含 port
	$path = substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
	$http = (($port == 443) || (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')) ? 'https' : 'http';
	return  "$http://$host$path/";
}

function url($url) {
	global $conf;
	!isset($conf['url_rewrite_on']) AND $conf['url_rewrite_on'] = 0;
	$r = $path = $query = '';
	if(strpos($url, '/') !== FALSE) {
		$path = substr($url, 0, strrpos($url, '/') + 1);
		$query = substr($url, strrpos($url, '/') + 1);
	} else {
		$path = '';
		$query = $url;
	}
	if($conf['url_rewrite_on'] == 0) {
		$r = $path . '?' . $query . '.htm';
	} elseif($conf['url_rewrite_on'] == 1) {
		$r = $path . $query . '.htm';
	} elseif($conf['url_rewrite_on'] == 2) {
		$r = $path . '?' . str_replace('-', '/', $query);
	} elseif($conf['url_rewrite_on'] == 3) {
		$r = $path . str_replace('-', '/', $query);
	}
	return $r;
}

// 递归遍历目录
function glob_recursive($pattern, $flags = 0) {
	$files = glob($pattern, $flags);
	foreach(glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
		 $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
	}
	return $files;
}

// 递归删除目录，这个函数比较危险，传参一定要小心
function rmdir_recusive($dir, $keepdir = 0) {
	if($dir == '/' || $dir == './' || $dir == '../') return FALSE;// 不允许删除根目录，避免程序意外删除数据。
	if(!is_dir($dir)) return FALSE;
	
	substr($dir, -1) != '/' AND $dir .= '/';
	$dir2 = $dir.'*';
	
	$files = glob($dir2);
	foreach($files as $file) {
		if($file == '.' || $file == '..') continue;
		if(!is_dir($file)) {
			unlink($file);
		} else {
			rmdir_recusive($file);
		}
	}
	if(!$keepdir) rmdir($dir);
	return TRUE;
}

// 随机字符
function xn_rand($n = 16) {
	$str = '0123456789abcdefghijklmnopqrstuvwxyz';
	$len = strlen($str);
	$return = '';
	for($i=0; $i<$n; $i++) {
		$r = rand(1, $len);
		$return .= $str[$r - 1];
	}
	return $return;
}

// 检测文件是否可写，兼容 windows
function xn_is_writable($file) {
	
	if(PHP_OS != 'WINNT') {
		return is_writable($file);
	} else {
		// 如果是 windows，比较麻烦，这也只是大致检测，不够精准。
		if(is_file($file)) {
			$fp = fopen($file, 'a+');
			if(!$fp) return FALSE;
			fclose($fp);
			return TRUE;
		} elseif(is_dir($file)) {
			$tmpfile = $file.uniqid().'.tmp';
			$r = touch($tmpfile);
			if(!$r) return FALSE;
			if(!is_file($tmpfile)) return FALSE;
			unlink($tmpfile);
			return TRUE;
		} else {
			return FALSE;
		}
	}
}

function xn_shutdown_handle() {
}

function xn_debug_info() {
	global $db, $starttime;
	$s = '';
	if(DEBUG) {
		$s .= '<div class="small break-all">';
		$s .= '<p>Processed Time:'.(microtime(1) - $starttime).'</p>';
		if(IN_CMD) {
			foreach($db->sqls as $sql) {
				$s .= "$sql\r\n";
			}
		} else {
			$s .= "\r\n<ul>\r\n";
			foreach($db->sqls as $sql) {
				$s .= "<li>$sql</li>\r\n";
			}
			$s .= "</ul>\r\n";
			$s .= '_REQUEST:<br>';
			$s .= xn_txt_to_html(print_r($_REQUEST, 1));
			$s .= '';
		}
		$s .= '</div>';
	}
	return $s;
}

// 解码客户端提交的 base64 数据
function base64_decode_image_data($data) {
	if(substr($data, 0, 10) == 'data:image') {
		$data = substr($data, strpos($data, ',') + 1);	// 去掉 data:image/png;base64,
	}
	$data = base64_decode($data);
	return $data;
}

// 输出
function http_404() {
	header('HTTP/1.1 404 Not Found'); 
	header('Status: 404 Not Found'); 
	echo '<h1>404 Not Found</h1>';
	exit;
}

// 无权限访问
function http_403() {
	header('HTTP/1.1 403 Forbidden'); 
	header('Status: 403 Forbidden'); 
	echo '<h1>403 Forbidden</h1>';
	exit;
}

function http_location($url) {
	header('Location:'.$url);
	exit;
}

/* 需要兼容，请自行打开开这段注释，默认不兼容

// 兼容 3.0，如果没有使用过，可以砍掉
function array_to_sqladd($arr) {
	return db_array_to_update_sqladd($arr);
}

// 兼容 3.0，如果没有使用过，可以砍掉
function cond_to_sqladd($cond) {
	return db_cond_to_sqladd($cond);
}

// 兼容 3.0，如果没有使用过，可以砍掉
function orderby_to_sqladd($orderby) {
	return db_orderby_to_sqladd($orderby);
}
*/

// 无 Notice 方式的获取超级全局变量中的 key
function _GET($k) { return isset($_GET[$k]) ? $_GET[$k] : NULL; }
function _POST($k) { return isset($_POST[$k]) ? $_POST[$k] : NULL; }
function _COOKIE($k) { return isset($_COOKIE[$k]) ? $_COOKIE[$k] : NULL; }
function _REQUEST($k) { return isset($_REQUEST[$k]) ? $_REQUEST[$k] : NULL; }
function _ENV($k) { return isset($_ENV[$k]) ? $_ENV[$k] : NULL; }
function _SERVER($k) { return isset($_SERVER[$k]) ? $_SERVER[$k] : NULL; }
function _SESSION($k) {global $g_session; return isset($_SESSION[$k]) ? $_SESSION[$k] : (isset($g_session[$k]) ? $g_session[$k] : NULL); }
function GLOBALS($k) { return isset($GLOBALS[$k]) ? $GLOBALS[$k] : NULL; }
function G($k) { return isset($GLOBALS[$k]) ? $GLOBALS[$k] : NULL; }

?>