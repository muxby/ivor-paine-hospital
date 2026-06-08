<?php
require 'includes/db.php';
$res = dbQuery("SELECT c.name FROM sys.columns c JOIN sys.objects o ON c.object_id = o.object_id WHERE o.name = 'TREATMENT_NOTES'");
print_r(dbFetchAll($res));
