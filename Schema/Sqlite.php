<?php

namespace Kanboard\Plugin\TrelloJSON2Kanboard\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec('ALTER TABLE projects ADD COLUMN is_trello_imported INTEGER DEFAULT "0"');
}
