<?php
# This test file does
# 1) create the tables found in config.php
# 2) populates them with $demo_texts
#
# It does not run script.php

require_once 'setup.php';

$demo_texts = [
  # should download  twice
  " 
<xml>

<img
  alt='xoobar'
  src=\"http://s3.buysellads.com/1258100/223291-1400632720.gif\" 
  />

  <img alt='xoobar' src=\"http://s3.buysellads.com/1258100/223291-1400632720.gif\" />

<img />

</xml>
",

# should download never:
  " 
<xml>
<img alt='xoobar' src=\"http://mawercer.de/failure.png\" />
</xml>
",

# testing bad doc, should not be touched!

"
  <x
"
];

$mysqli->query("DROP TABLE IF EXISTS ".IMAGE_STATUS_TABLE_Q);

// create table
foreach ($database_columns_to_process as $cfg) {
  $mysqli->query("DROP TABLE IF EXISTS `{$cfg['table']}`");
  $mysqli->query(" 
  CREATE TABLE `{$cfg['table']}` (
    `id` int auto_increment PRIMARY KEY,
    `{$cfg['field']}` varchar(255) NOT NULL
  ) engine = MYISAM default character set = utf8 collate = utf8_general_ci
  ");

  $stmt_insert_text = $mysqli->prepare("INSERT INTO `{$cfg['table']}` (`{$cfg['field']}`) VALUES (?)");

  foreach ($demo_texts as $text) {
    $stmt_insert_text->bind_param('s', $text);
    $stmt_insert_text->execute();
  }
}
