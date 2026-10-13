<?php
// Superseded by the Customer Master in modules/customers/.
require_once __DIR__ . '/../../helpers/redirect.php';

redirect_to('modules/customers/view.php?id=' . (int) ($_GET['id'] ?? 0));
