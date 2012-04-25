#!/bin/bash
#
# プロセス開始/終了
#
# $Revision: 133 $

PWD=`pwd`
SCRIPT_DIR=$(cd $(dirname $0) && pwd)
PARENT_DIR=`dirname $SCRIPT_DIR`
PROG=$SCRIPT_DIR/ircbot.php
SCRIPT_PID="$SCRIPT_DIR/run_script.pid"
SCRIPT_NAME=`basename "$0"`

killproc() {	# kill named processes
	pid=`/bin/ps ax | /bin/grep "$1" | /bin/awk '{print $1}'`
	[ "$pid" != "" ] && kill $pid
}

case "$1" in
'start')
	if [ -f $SCRIPT_PID ]; then
		PID=`cat $SCRIPT_PID`
		if (ps ax | /bin/awk '{print $1}' | /bin/grep $PID >/dev/null); then
			exit
		fi
	fi

	echo $$ > $SCRIPT_PID

	# バッチ処理
	/usr/bin/php -q $PROG

	/bin/rm -f $SCRIPT_PID
	;;
'stop')
	killproc $PROG
	;;
*)
	echo "Usage: $SCRIPT_NAME { start | stop }"
	;;
esac
