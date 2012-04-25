<?php
/**
* @file
* @brief ircbot
*
* @note
* - License: GPL version 3 or (at your option) any later version
*
* - Install
*   - # pear upgrade --force Archive_Tar
*   - # pear upgrade-all
*   - # pear install Net_SmartIRC
* - Execute
*   - $ nohup php -q ircbot.php &
* - cron
*   - crontab -e
*   - *\/10 * * * * /path/to/ircbot.cron.sh start >/dev/null 2>&1
*
* - http://pear.php.net/package/Net_SmartIRC/
* - http://www.phppro.jp/phptips/archives/vol36/1
* - http://d.hatena.ne.jp/anon_193/20090214/1234559387
*
* @version $Revision: 254 $
*/

require_once("mybot.class.php");

define('IRC_AUTORETRYMAX', 5);
define('IRC_TIMER_MINUTE', 60*1000);
define('IRC_CONFIG', CUR_DIR.DS.'ircbot.ini');

exit(main());

declare(ticks = 1);

// シグナルハンドラ関数
// SIGUSR1 をカレントのプロセス ID に送信します
// posix_kill(posix_getpid(), SIGUSR1);
function sig_handler($signo)
{
     switch ($signo) {
         case SIGTERM:
             // シャットダウンの処理
             fwrite(STDERR, "SIGTERM を受け取りました...\n");
             break;
         case SIGHUP:
             // 再起動の処理
             fwrite(STDERR, "SIGHUP を受け取りました...\n");
             break;
         case SIGUSR1:
             fwrite(STDERR, "SIGUSR1 を受け取りました...\n");
             break;
         default:
             // それ以外のシグナルの処理
             fwrite(STDERR, "$signo を受け取りました...\n");
             break;
     }
     exit($signo);
}

function main()
{
	mb_internal_encoding('UTF-8');
	mb_http_input('pass');
	mb_http_output('pass');

	// シグナルハンドラを設定します
	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGHUP,  "sig_handler");
	pcntl_signal(SIGUSR1, "sig_handler");

	$ini = parse_ini_file(IRC_CONFIG, true);
	$bot = &new MyBot($ini['server']);
	$irc = &new Net_SmartIRC();
	$irc->setDebug(SMARTIRC_DEBUG_ALL);
	$irc->setLogfile(CUR_DIR . DS . 'log' . DS . 'ircbot.log');
	$irc->setLogdestination(SMARTIRC_FILE); // ログをファイルに出力
	$irc->setUseSockets(true);
	$irc->setChannelSyncing(true); // 自動再join
/* 終了しなくなるためコメントアウト。再起動はcronにまかせる
	$irc->setAutoReconnect(true);
	$irc->setAutoRetry(true);
	$irc->setAutoRetryMax(IRC_AUTORETRYMAX);
*/

	// 再接続
	$irc->registerTimehandler(IRC_TIMER_MINUTE, $bot, 'timer_minute');

	// hiメッセージに応答
//	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^hi$', $bot, 'hello');

	// オペレータ権限を与える
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^naruto$', $bot, 'naruto');

	// quitメッセージで終了
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^quit$', $bot, 'quit');

	// help
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^help$', $bot, 'help');
	
	// URL検出
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^https?:\/\/([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$', $bot, 'url');

	// チャンネルに誰か来た
	$irc->registerActionhandler(SMARTIRC_TYPE_JOIN, '.*', $bot, 'naruto');

	// ニックネーム列挙
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^bosyu|users', $bot, 'nick_list');
	
	// オペレータ列挙
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^ops$', $bot, 'op_list');
	
	// ログ
	$irc->registerActionhandler(SMARTIRC_TYPE_JOIN, '.*', $bot, 'logger');
	$irc->registerActionhandler(SMARTIRC_TYPE_QUIT, '.*', $bot, 'logger');
	$irc->registerActionhandler(SMARTIRC_TYPE_PART, '.*', $bot, 'logger');
	$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '.*', $bot, 'logger');
	$irc->registerActionhandler(SMARTIRC_TYPE_NOTICE, '.*', $bot, 'logger');

	$server = &$ini['server'];
	$irc->connect($server['host'], intval($server['port']));
	$irc->login($server['nickname'], $server['realname']);
	$irc->join( array( $bot->encode($server['channel']) ) );

	$irc->listen();
	$irc->disconnect();
}
