<?php
/**
* @file
* @brief ircbot
*
* @note
* @version $Revision: 234 $
*/

require_once("Net/SmartIRC.php");

if(!defined('CUR_DIR')) define('CUR_DIR', dirname(__FILE__));
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

/// IRCの文字コード
define('IRC_ENCODING', 'iso-2022-jp');

/// このプラグインの文字コード
defined('PLUGIN_MYBOT_ENCODING') or define('PLUGIN_MYBOT_ENCODING', 'UTF-8');

/// リンクタイトルの最大長
defined('PLUGIN_URL_TITLE_MAX') or define('PLUGIN_URL_TITLE_MAX', 255);

class MyBot
{ 
	var $system_revision = '$Revision: 234 $';
	var $system_date = '$Date: 2012-02-12 12:59:59 +0900 (日, 12  2月 2012) $';
	var $options = array(
		'host' => '',
		'port' => '',
		'channel' =>  '',
		'msg_hello' => 'こんにちは ',
		'msg_quit'	=> 'ごきげんよう ',
		'op' => array(
			'#^192\.168\.\d{1,3}\.\d{1,3}$#',
			'#^127\.0\.0\.1$#',
		),
		'log_dir' => '',
	);

	// コンストラクタ
	function MyBot($options = array())
	{
		$this->options = array_merge($this->options, $options);
		if($this->options['log_dir'] == '') $this->options['log_dir'] = CUR_DIR . DS . 'log';
		mb_internal_encoding('UTF-8');
		mb_detect_order("ascii,eucjp,sjis,UTF-8");
	}

	// タイマー処理
	function timer_minute(&$irc)
	{
		$channel = &$irc->getChannel( $this->encode($this->options['channel']) );
		// bot一人でかつ、opが無い場合終了して、op復活
		if($irc->isJoined( $this->encode($this->options['channel']) ) && !$irc->isOpped( $this->encode($this->options['channel']) ) ){
			$user_count = count($channel->users);
			if($user_count > 1) return; // bot以外がいる場合、再起動しない
			$irc->log(SMARTIRC_DEBUG_NOTICE, "The automatic termination: op not found.", __FILE__, __LINE__);
			$irc->quit( $this->encode($this->options['msg_quit']) );
			// 再起動はcronにまかせる
		}
		
		if( !$irc->isJoined( $this->encode($this->options['channel']))
			|| !$channel
			|| count($channel) <= 0
		 ){
			$irc->quit( $this->encode($this->options['msg_quit']) );
		}
	}

	// hiメッセージ
	function hello(&$irc, &$data)
	{ 
		$irc->message(SMARTIRC_TYPE_NOTICE, $data->channel, $this->encode($this->options['msg_hello'] .$data->nick) ); 
	}

	/// urlからタイトル取得
	function url(&$irc, &$data){
		$url = trim($data->message);
		$title = false;
		
		if( version_compare(PHP_VERSION, '5.0', '>=') ){
			$option = array(
				'http' => array(
					'timeout' => 5,
					'method' => 'GET',
					'header' => 'Referer: ' . $url . "\r\n"
								. 'User-Agent: ' . 'Mozilla/5.0 (Windows; U; Windows NT 5.1; ja; rv:1.9.2) Gecko/20100115 Firefox/3.6 (.NET CLR 3.5.30729)' . "\r\n"
								. 'Connection: close' . "\r\n"
				)
			);
			$context = stream_context_create($option);
			$urldata = @file_get_contents($url, 0, $context);
		}else{
			$urldata = @file_get_contents($url);
		}

		if($urldata === false) return $title; // 取得失敗
		if( preg_match('#[^\'\"]<title[^\>]*>(.*?)</title>[^\'\"]#is', $urldata, $matches) ){
			$encoding = mb_detect_encoding($urldata);

			$title = $matches[1];
			if(preg_match("/&#[xX]*[0-9a-zA-Z]{2,8};/", $title)){ // 数値参照形式 -> 文字列
				$title = $this->nument2chr($title, $encoding);
			}

			$title = mb_convert_encoding($title, PLUGIN_MYBOT_ENCODING, $encoding);// 内部文字コードに変換
			$title = html_entity_decode($title, ENT_QUOTES, PLUGIN_MYBOT_ENCODING);
			$title =  $this->mb_trim($title);
			$title = mb_strimwidth($title, 0, PLUGIN_URL_TITLE_MAX, "...", PLUGIN_MYBOT_ENCODING); // 長すぎる場合はカット
			$title = mb_convert_encoding($title, IRC_ENCODING, PLUGIN_MYBOT_ENCODING); // 出力文字コードに変換
		}
		
		if($title != '') {
			$irc->message(SMARTIRC_TYPE_NOTICE, $data->channel, $title);
		}
	}

	// なると付与
	// ニックネームが自分だったら処理をしない
	// HOSTを参照してローカルのユーザーだったらオペレーション権限を付与
	// それ以外の場合は「ごきげんよう」と挨拶するｗ
	function naruto(&$irc, &$data){
		if ($data->nick == $irc->_nick) return;

		if( $this->isOpped($irc, $data) ){
			$irc->op($data->channel,$data->nick);
		}

		$irc->message(SMARTIRC_TYPE_NOTICE, $data->channel, $this->encode('ごきげんよう ').$data->nick);
	}

	// bot終了
	// ごきげんようと挨拶を残して動作を終了する．
	// これも誤認識しないようにローカルのユーザーが「quit」と入力した場合だけに限定
	function quit(&$irc, &$data){
		if( $this->isOpped($irc, $data) ){
			$irc->quit( $this->encode($this->options['msg_quit']) );
		}
	}

	/// ヘルプ
	function help(&$irc, &$data){
		$str =<<<EOD
> ircbot {$this->system_revision} {$this->system_date}
> naruto           なると配布
> bosyu users      ニックネーム一覧
> ops              オペレータ一覧
> http://～        HTMLタイトル自動表示
> help             このヘルプ
EOD;
		$str = $this->encode($str);
		$strs = explode("\n", $str);
		foreach($strs as $key => $val){
			$irc->message(SMARTIRC_TYPE_NOTICE, $data->channel, $val);
		}
	}

	/// PHPシステム環境文字列→IRC日本語文字列の変換
	function encode($str)
	{
		return mb_convert_encoding($str,IRC_ENCODING, "auto");
	}
	
	// IRC日本語文字列→PHPシステム環境文字列の変換
	function decode($str)
	{
		return mb_convert_encoding($str, mb_internal_encoding(), IRC_ENCODING);
	}
	
	// オペレータか？
	function isOpped(&$irc, &$data)
	{
		foreach($this->options['op'] as $key => $val){
			if(preg_match($val,$data->host)
			|| preg_match($val,$data->nick)
			){
				return true;
			}
		}
		return false;
	}

	/// ログ出力
	function logger(&$irc, &$data){
/* var_export($data, true)
Net_SmartIRC_data::__set_state(array(
   'from' => 'test-bot!~foo@exsample.com',
   'nick' => 'test-bot',
   'ident' => '~foo',
   'host' => '192.168.1.10.exsample.com',
   'channel' => '#test',
   'message' => '#test',
   'messageex' => 
  array (
	0 => '#test',
  ),
   'type' => 64,
   'rawmessage' => ':test-bot!~foo@exsample.com JOIN :#test',
   'rawmessageex' => 
  array (
	0 => ':test-bot!~foo@exsample.com',
	1 => 'JOIN',
	2 => ':#test',
  ),
))
*/

		if( !is_dir($this->options['log_dir']) ) mkdir($this->options['log_dir'], 0777);
		$filename = date('Ymd') . '.txt';
		
		$message = '';
		switch($data->type){
		case SMARTIRC_TYPE_JOIN:
			$message = sprintf("%s  %s:%s - %s(%s): has joined channel\n"
			, date('H:i:s')
			, $this->options['host'] . ':' . $this->options['port']
			, $this->decode($data->channel)
			, $this->decode($data->nick)
			, $this->decode($data->from)
			);
			break;
		case SMARTIRC_TYPE_QUIT:
		case SMARTIRC_TYPE_PART:
			$message = sprintf("%s  %s:%s - %s: has left IRC \"\"%s\"\"\n"
			, date('H:i:s')
			, $this->options['host'] . ':' . $this->options['port']
			, $this->decode($data->channel)
			, $this->decode($data->nick)
			, $this->decode($data->message)
			);
		default:
			$message = sprintf("%s  %s:%s - %s: %s\n"
			, date('H:i:s')
			, $this->options['host'] . ':' . $this->options['port']
			, $this->decode($data->channel)
			, $this->decode($data->nick)
			, $this->decode($data->message)
			);
			break;
		}
		file_put_contents($this->options['log_dir'].DS.$filename, $message, FILE_APPEND);
	}

	/// オペレータ列挙
	function op_list(&$irc, &$data)
	{
		$list = '';
		// $irc->channel[$data->channel] だと文字化けするためかうまく取得できない。getChannel()を使う
		$channel = &$irc->getChannel($data->channel);
		foreach ($channel->ops as $key => $value) {
			$list .= ' '.$key;
		}
		if($list == '') return;

		$irc->message(SMARTIRC_TYPE_NOTICE, $data->channel, $list);
	}

	/// ニックネーム列挙
	function nick_list(&$irc, &$data)
	{
		$list = '';
		// $irc->channel[$data->channel] だと文字化けするためかうまく取得できない。getChannel()を使う
		$channel = &$irc->getChannel($data->channel);
		foreach ($channel->users as $key => $value) {
			$list .= ' '.$key;
		}
		if($list == '') return;

		$irc->message(SMARTIRC_TYPE_CHANNEL, $data->channel, $list);
	}
	
	/// 数値文字参照を文字に変換(&#x0000;)
	function nument2chr($string, $encode_to='utf-8') {
		// 文字コードチェック、mb_detect_order()が関係する
		$encoding = strtolower(mb_detect_encoding($string));
		if (!preg_match("/^utf/", $encoding) and $encoding != 'ascii') {
			return '';
		}
		
		// 16 進数の文字参照(らしき表記)が含まれているか
		$excluded_hex = $string;
		if (preg_match("/&#[xX][0-9a-zA-Z]{2,8};/", $string)) {
			// 16 進数表現は 10 進数に変換
			$excluded_hex = preg_replace("/&#[xX]([0-9a-zA-Z]{2,8});/e", "'&#'.hexdec('$1').';'", $string);
		}
		return mb_decode_numericentity($excluded_hex, array(0x0, 0x10000, 0, 0xfffff), $encode_to);
	}
	
	/// マルチバイトtrim
	function mb_trim($str)
	{
		$whitespace = '[\s\0\x0b\p{Zs}\p{Zl}\p{Zp}]';
		$pattern = array(
			sprintf('/(^%s+|%s+$)/u', $whitespace, $whitespace), // 前後の空白
			"/[\r\n]+/", // 改行
			"/[\s]+/", // 空白の連続
		);
		$replacement = array(
			'',
			'',
			' ',
		);
		$ret = preg_replace($pattern, $replacement, $str);
		return $ret;
	}
}
