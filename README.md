php-ircbot
==========

PHP製IRC BOT

## install

- Install
  - # pear install Net_SmartIRC
  - # vi ircbot.ini

- cron
  - crontab -e
  - */10 * * * * /path/to/ircbot.cron.sh start >/dev/null 2>&1

## 機能

- URLを貼り付けるとタイトルタグを抽出して発言
- チャンネルログ保存
- 自動オペレータ

## 既知の問題

- DHCP等でIPアドレスが変更になった際に追従できない。stop > startする必要がある
 - /path/to/ircbot.cron.sh stop
