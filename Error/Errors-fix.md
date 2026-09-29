Fatal error: Uncaught Error: Call to undefined function formatAmount() in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\index.php:61 Stack trace: #0 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\index.php on line 61



Warning: require_once(../../../helpers/finance_helpers.php): Failed to open stream: No such file or directory in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\depreciation\process.php on line 4

Fatal error: Uncaught Error: Failed opening required '../../../helpers/finance_helpers.php' (include_path='C:\xampp\php\PEAR') in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\depreciation\process.php:4 Stack trace: #0 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\depreciation\process.php on line 4


Warning: require_once(../../../helpers/redirect.php): Failed to open stream: No such file or directory in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\disposal\create.php on line 4

Fatal error: Uncaught Error: Failed opening required '../../../helpers/redirect.php' (include_path='C:\xampp\php\PEAR') in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\disposal\create.php:4 Stack trace: #0 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\disposal\create.php on line 4



Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ac.category_name' in 'field list' in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\register.php:44 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\register.php(44): PDO->prepare('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\register.php on line 44



Fatal error: Uncaught TypeError: PDO::prepare(): Argument #2 ($options) must be of type array, int given in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\depreciation.php:13 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\depreciation.php(13): PDO->prepare('\r\n SELECT \r\n...', 2) #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\depreciation.php on line 13


Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ac.category_name' in 'field list' in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\nbv.php:34 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\nbv.php(34): PDO->prepare('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\nbv.php on line 34



Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ac.category_name' in 'field list' in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\disposal.php:24 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\disposal.php(24): PDO->prepare('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\disposal.php on line 24



Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ac.category_name' in 'field list' in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\gain_loss.php:25 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\gain_loss.php(25): PDO->prepare('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\gain_loss.php on line 25



Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ac.category_name' in 'field list' in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\category.php:9 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\category.php(9): PDO->query('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\category.php on line 9


Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'erp_db.asset_transfers' doesn't exist in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\movement.php:26 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\movement.php(26): PDO->prepare('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\movement.php on line 26


Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'erp_db.asset_impairments' doesn't exist in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\impairment.php:26 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\impairment.php(26): PDO->prepare('\r\n SELECT \r\n...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\impairment.php on line 26


Fatal error: Uncaught TypeError: PDO::prepare(): Argument #2 ($options) must be of type array, int given in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\monthly.php:11 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\monthly.php(11): PDO->prepare('\r\n SELECT \r\n...', 2) #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\reports\monthly.php on line 11


Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'account_type' in 'field list' in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\categories\setup.php:37 Stack trace: #0 C:\xampp\htdocs\ERP\modules\finance\fixed_assets\categories\setup.php(37): PDO->query('SELECT id, acco...') #1 {main} thrown in C:\xampp\htdocs\ERP\modules\finance\fixed_assets\categories\setup.php on line 37







