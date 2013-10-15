2013/8/3

眠くて早く帰りたいのでさらっと書きます.

テーブルの内容：
渡された紙と[sqlQuery]の"createTable.sql"ファイルの中身を見れば分かります.

"config.inc.php"：
データベースにアクセスするためのユーザ情報やパスワードが記載されています.

"DataBaseHanlder.php":
データベースのAPIになります。
こちらのファイルの中身にSQL文を記載していますが、
本来APIなのでこちらのファイルを見る必要はありません。
エラーが発生した、テーブルの内容が変わった場合などは要修正。

"DataBaseGetInfo.php"：
こちらがデータベースから必要なデータを抽出するための、
APIの使用例を記述したファイルになります。
皆さんは主にこちらのファイルを参考に、
ビューワーに必要な形式の情報に変換するように努めて下さい。
※デバック用でファイルからトピックなどを読み込んで起動も出来ます。
下のコマンドで実行可能。

php DataBaseGetInfo.php exampleTestGetInfo/info.txt


"DataBaseSetInfo.php":
こちらがデータベースから必要なデータを登録するための、
APIの使用例を記述したファイルになります。
ICAからデータを抽出した際に登録する処理や、
人力でデータベースからデータを入力する処理に使って下さい。

※下のコマンドで例を実行可能
 （ただし、データを挿入する処理なのでテキストファイルの中身だけ見た方が無難）

php DataBaseSetInfo.php exampleTestSetInfo/User.txt
php DataBaseSetInfo.php exampleTestSetInfo/Follow.txt
php DataBaseSetInfo.php exampleTestSetInfo/Topic.txt
php DataBaseSetInfo.php exampleTestSetInfo/Date.txt
php DataBaseSetInfo.php exampleTestSetInfo/UserTweet.txt
