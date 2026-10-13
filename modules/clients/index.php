<?php
// Superseded by the Customer Master in modules/customers/. Kept as a redirect so
// existing bookmarks and CRM deep links keep working.
require_once __DIR__ . '/../../helpers/redirect.php';

redirect_to('modules/customers/index.php');
