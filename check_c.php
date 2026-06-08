<?php
require_once 'includes/db.php';
$res = dbQuery("SELECT definition FROM sys.check_constraints WHERE name IN ('CK_COMPLAINT_SEVERITY', 'CK_TREATMENT_STATUS')");
print_r(dbFetchAll($res));
