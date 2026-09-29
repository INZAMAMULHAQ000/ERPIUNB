<?php
/**
 * TaxService — THE single, centralized Tax Engine for the entire ERP.
 *
 * Every taxable document (customer invoice, vendor bill, expense, credit note,
 * debit note — or any future module) MUST compute its tax through this class.
 * Never duplicate tax math inside a module.
 *
 * Responsibilities:
 *   - Config-driven, country-agnostic tax rules (types, rates, groups, statuses)
 *   - Historical rate preservation: the rate actually used at transaction time
 *     is resolved by effective dates and returned back to the caller to store.
 *   - Tax-exclusive and tax-inclusive pricing with configurable rounding.
 *   - Tax periods (open/submitted/closed) blocking normal modifications.
 *   - A single tax_transactions register (output / input / adjustment) and
 *     reversals, so reports reconcile with the Input/Output Tax GL accounts.
 *
 * Usage example (as specified in F8):
 *   $calc = TaxService::calculateLineTax(1000, ['tax_rate_id' => 1]);
 *   // => ['net_amount'=>1000, 'tax_amount'=>50, 'total_amount'=>1050, ...]
 */

require_once __DIR__ . '/../helpers/finance_helpers.php';

class TaxService {

    // ---------------------------------------------------------------- statuses
    const TAXABLE      = 'taxable';
    const ZERO_RATED   = 'zero_rated';
    const EXEMPT       = 'exempt';
    const OUT_OF_SCOPE = 'out_of_scope';

    // ---------------------------------------------------------------- directions
    const DIR_OUTPUT      = 'output';
    const DIR_INPUT       = 'input';
    const DIR_ADJUSTMENT  = 'adjustment';

    // ------------------------------------------------- source module identifiers
    const SOURCE_INVOICE     = 'customer_invoice';
    const SOURCE_BILL        = 'vendor_bill';
    const SOURCE_EXPENSE     = 'company_expense';
    const SOURCE_CREDIT_NOTE = 'credit_note';
    const SOURCE_DEBIT_NOTE  = 'debit_note';
    const SOURCE_ADJUSTMENT  = 'tax_adjustment';

    /** Valid tax statuses, for dropdowns / validation. */
    public static function statuses() {
        return [
            self::TAXABLE      => 'Taxable',
            self::ZERO_RATED   => 'Zero Rated',
            self::EXEMPT       => 'Exempt',
            self::OUT_OF_SCOPE => 'Out of Scope',
        ];
    }

    // ================================================================== CONFIG

    /**
     * Read a tax_* setting (auto-prefixed).
     * @param string $key e.g. 'default_tax_type_id' -> reads setting 'tax_default_tax_type_id'
     */
    public static function config($key, $default = null) {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $db = getDB();
                $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tax\\_%'")->fetchAll();
                foreach ($rows as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Throwable $e) {
                $cache = []; // DB not ready (e.g. during setup) — treat as empty
            }
        }
        $key = 'tax_' . ltrim($key, 'tax_');
        return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
    }

    /** @return bool whether prices are entered tax-inclusive. */
    public static function pricesInclusive() {
        return self::config('pricing_method', 'exclusive') === 'inclusive';
    }

    /** Round a monetary amount using the configured rounding method. */
    public static function roundAmount($amount) {
        $method = self::config('rounding_method', 'half_up');
        $prec   = (int)self::config('decimal_precision', 2);
        $mul    = 10 ** $prec;

        switch ($method) {
            case 'round_up':
                return ceil(($amount * $mul) - 1e-9) / $mul;
            case 'round_down':
                return floor(($amount * $mul) + 1e-9) / $mul;
            case 'half_down': {
                $scaled = $amount * $mul;
                $frac   = $scaled - floor($scaled);
                $rounded = (abs($frac - 0.5) < 1e-9) ? floor($scaled) : round($scaled);
                return $rounded / $mul;
            }
            case 'nearest':
                return round(($amount * $mul) - 1e-9, 0, PHP_ROUND_HALF_DOWN) / $mul;
            case 'free':
                return round($amount, $prec + 2);
            case 'half_up':
            default:
                return round($amount, $prec);
        }
    }

    public static function precision() {
        return (int)self::config('decimal_precision', 2);
    }

    // ================================================================= TAX TYPES

    public static function getTaxType($id) {
        if (!$id) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tax_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getTaxTypeByCode($code) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tax_types WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public static function listTaxTypes($includeInactive = false) {
        $db = getDB();
        $sql = "SELECT * FROM tax_types";
        if (!$includeInactive) $sql .= " WHERE status = 'active'";
        $sql .= " ORDER BY name";
        return $db->query($sql)->fetchAll();
    }

    /** Default tax type from config; falls back to the first active type. */
    public static function getDefaultTaxType() {
        $id = (int)self::config('default_tax_type_id', 0);
        $type = $id ? self::getTaxType($id) : null;
        if ($type) return $type;
        $types = self::listTaxTypes();
        return $types[0] ?? null;
    }

    // ================================================================= TAX RATES

    public static function getTaxRate($id) {
        if (!$id) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT r.*, t.name AS tax_type_name, t.code AS tax_type_code FROM tax_rates r JOIN tax_types t ON t.id = r.tax_type_id WHERE r.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function listTaxRates($taxTypeId = null, $includeInactive = false) {
        $db = getDB();
        $sql = "SELECT r.*, t.name AS tax_type_name, t.code AS tax_type_code FROM tax_rates r JOIN tax_types t ON t.id = r.tax_type_id";
        $where = [];
        $params = [];
        if ($taxTypeId)   { $where[] = "r.tax_type_id = ?"; $params[] = $taxTypeId; }
        if (!$includeInactive) { $where[] = "r.status = 'active'"; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY t.code, r.rate_percentage DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Default rate for the given (or default) tax type. */
    public static function getDefaultTaxRate($taxTypeId = null) {
        if (!$taxTypeId) {
            $type = self::getDefaultTaxType();
            $taxTypeId = $type ? (int)$type['id'] : 0;
        }
        if (!$taxTypeId) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tax_rates WHERE tax_type_id = ? AND status = 'active' AND is_default = 1 ORDER BY effective_from DESC LIMIT 1");
        $stmt->execute([$taxTypeId]);
        $rate = $stmt->fetch();
        if ($rate) return $rate;
        $stmt = $db->prepare("SELECT * FROM tax_rates WHERE tax_type_id = ? AND status = 'active' ORDER BY rate_percentage DESC LIMIT 1");
        $stmt->execute([$taxTypeId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Resolve the applicable rate for a tax type as of a date.
     * Supports historical rates: callers store the returned rate_id on the line,
     * so later rate changes never retroactively alter old transactions.
     *
     * @param int|null   $taxTypeId
     * @param string|null $date      'Y-m-d' — defaults to today
     * @param int|null   $rateId     optional pinned rate (e.g. a stored historical id)
     * @return array|null rate row
     */
    public static function getApplicableTaxRate($taxTypeId, $date = null, $rateId = null) {
        $date = $date ?: date('Y-m-d');
        $db = getDB();

        // Explicit pinned rate: honour it (keeps historical transactions intact).
        if ($rateId) {
            $stmt = $db->prepare("SELECT * FROM tax_rates WHERE id = ? AND status = 'active'");
            $stmt->execute([$rateId]);
            return $stmt->fetch() ?: null;
        }
        if (!$taxTypeId) return null;

        // Effective on date, preferring the default rate.
        $stmt = $db->prepare("
            SELECT * FROM tax_rates
            WHERE tax_type_id = ? AND status = 'active'
              AND (effective_from IS NULL OR effective_from <= ?)
              AND (effective_to   IS NULL OR effective_to   >= ?)
            ORDER BY is_default DESC, rate_percentage DESC, effective_from DESC
            LIMIT 1
        ");
        $stmt->execute([$taxTypeId, $date, $date]);
        $rate = $stmt->fetch();
        if ($rate) return $rate;

        // Fall back to the default rate regardless of window.
        return self::getDefaultTaxRate($taxTypeId);
    }

    /** True when a new active window would overlap an existing active window. */
    public static function rateWindowConflicts($taxTypeId, $effectiveFrom, $effectiveTo, $ignoreRateId = null) {
        $db = getDB();
        $sql = "SELECT COUNT(*) FROM tax_rates
                WHERE tax_type_id = ? AND status = 'active'
                  AND NOT (effective_to   IS NOT NULL AND ? IS NOT NULL AND effective_to   < ?)
                  AND NOT (? IS NOT NULL AND effective_from > ?)
                  AND NOT (? IS NOT NULL AND effective_to   IS NULL)";
        $params = [$taxTypeId, $effectiveFrom, $effectiveFrom, $effectiveTo, $effectiveTo, $effectiveTo];
        if ($ignoreRateId) { $sql .= " AND id <> ?"; $params[] = $ignoreRateId; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ================================================================= TAX GROUPS

    public static function getTaxGroup($id) {
        if (!$id) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tax_groups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getTaxGroupLines($taxGroupId) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT gl.*, r.rate_name, r.rate_percentage, r.tax_type_id, r.effective_from, r.effective_to, r.status AS rate_status, t.code AS tax_type_code
            FROM tax_group_lines gl
            JOIN tax_rates r ON r.id = gl.tax_rate_id
            JOIN tax_types t ON t.id = r.tax_type_id
            WHERE gl.tax_group_id = ?
            ORDER BY gl.sequence, gl.id
        ");
        $stmt->execute([$taxGroupId]);
        return $stmt->fetchAll();
    }

    public static function listTaxGroups($includeInactive = false) {
        $db = getDB();
        $sql = "SELECT * FROM tax_groups";
        if (!$includeInactive) $sql .= " WHERE status = 'active'";
        $sql .= " ORDER BY group_name";
        return $db->query($sql)->fetchAll();
    }

    /**
     * Default/effective group for a tax type; the group whose lines are active
     * as of the date (else the configured default, else the first active group).
     */
    public static function getApplicableTaxGroup($taxTypeId, $date = null) {
        $date = $date ?: date('Y-m-d');
        $groups = self::listTaxGroups();
        foreach ($groups as $g) {
            foreach (self::getTaxGroupLines((int)$g['id']) as $line) {
                if ((int)$line['tax_type_id'] !== (int)$taxTypeId) continue;
                $from = $line['effective_from'] ?: $date;
                $to   = $line['effective_to']   ?: $date;
                if ($from <= $date && $to >= $date) {
                    return $g;
                }
            }
        }
        $gid = (int)self::config('default_tax_group_id', 0);
        return self::getTaxGroup($gid);
    }

    // ================================================================= STATUSES

    public static function isTaxable($status)    { return $status === self::TAXABLE; }
    public static function isZeroRated($status)  { return $status === self::ZERO_RATED; }
    public static function isExempt($status)     { return $status === self::EXEMPT || $status === self::OUT_OF_SCOPE; }
    public static function isValidStatus($status){ return array_key_exists($status, self::statuses()); }

    // ================================================================= CALCULATION

    /**
     * Calculate tax for a single line.
     *
     * @param float|string $taxableAmount base amount subject to tax. For tax-exclusive
     *      pricing this is the NET amount; for tax-inclusive pricing it is the GROSS
     *      amount from which the tax portion is extracted.
     * @param array $opts any of:
     *      - tax_rate_id   (int) pinned tax rate (historical preservation)
     *      - tax_group_id  (int) group -> resolves its first effective line rate
     *      - tax_type_id   (int)
     *      - tax_status    (string) default 'taxable'
     *      - exemption_id  (int)
     *      - inclusive     (bool|null) force inclusive/exclusive, null => config
     *      - date          ('Y-m-d')
     *
     * @return array {
     *   valid, message,
     *   net_amount, tax_amount, total_amount,
     *   taxable_amount (= net), total (alias gross),
     *   rate_percentage, rate_name, tax_rate_id, tax_group_id,
     *   tax_type_id, tax_type_name, tax_status, exemption_id,
     *   inclusive, rounding_method, precision
     * }
     */
    public static function calculateLineTax($taxableAmount, array $opts = []) {
        $amount     = max(0, (float)$taxableAmount);
        $status     = $opts['tax_status'] ?? self::TAXABLE;
        $date       = $opts['date'] ?? date('Y-m-d');
        $taxTypeId  = $opts['tax_type_id'] ?? (int)self::config('default_tax_type_id', 0);
        $rateId     = $opts['tax_rate_id'] ?? null;
        $groupId    = $opts['tax_group_id'] ?? null;
        $exemption  = $opts['exemption_id'] ?? null;

        if (!self::isValidStatus($status)) $status = self::TAXABLE;

        // Resolve the rate (group line first, then pinned rate, then effective default).
        $rate = null;
        if ($groupId) {
            foreach (self::getTaxGroupLines((int)$groupId) as $line) {
                $from = $line['effective_from'] ?: $date;
                $to   = $line['effective_to']   ?: $date;
                if ($line['rate_status'] === 'active' && $from <= $date && $to >= $date) {
                    $rate = self::getTaxRate((int)$line['tax_rate_id']);
                    break;
                }
            }
            if (!$rate) { // group exists but nothing effective on date → use first active line
                $lines = self::getTaxGroupLines((int)$groupId);
                if ($lines) $rate = self::getTaxRate((int)$lines[0]['tax_rate_id']);
            }
        }
        if (!$rate && $rateId) $rate = self::getTaxRate((int)$rateId);
        if (!$rate) {
            $rate = ($taxTypeId || $taxTypeId === null) ? self::getApplicableTaxRate($taxTypeId, $date, $rateId) : null;
        }
        if (!$rate) $rate = ['rate_percentage' => 0, 'rate_name' => 'No Tax', 'tax_type_id' => $taxTypeId, 'id' => null];

        $taxTypeId  = $taxTypeId ?: (int)($rate['tax_type_id'] ?? 0);
        $rateId     = $rateId ?: ($rate['id'] ?? null);
        $pct        = (float)$rate['rate_percentage'];
        $inclusive  = $opts['inclusive'] ?? self::pricesInclusive();
        $inclusive  = (bool)$inclusive;

        // Non-taxable statuses never carry tax.
        $taxable = self::isTaxable($status);
        if (!$taxable) $pct = 0.0;

        if ($inclusive) {
            // Given gross: net = gross / (1 + rate), tax = gross - net, total = gross.
            $net  = $amount / (1 + $pct / 100);
            $net  = self::roundAmount($net);
            $tax  = self::roundAmount($amount - $net);
            $gross = $amount;
        } else {
            $net  = $amount;
            $tax  = self::roundAmount($net * $pct / 100);
            $gross = self::roundAmount($net + $tax);
        }

        $taxType = self::getTaxType((int)$taxTypeId);

        return [
            'valid'            => true,
            'message'          => '',
            'net_amount'       => $net,
            'tax_amount'       => $tax,
            'total_amount'     => $gross,
            'taxable_amount'   => $net,
            'total'            => $gross,
            'rate_percentage'  => $taxable ? (float)$rate['rate_percentage'] : 0.0,
            'rate_name'        => $rate['rate_name'] ?? '',
            'tax_rate_id'      => $rateId,
            'tax_group_id'     => $groupId,
            'tax_type_id'      => $taxTypeId,
            'tax_type_name'    => $taxType ? $taxType['name'] : '',
            'tax_status'       => $status,
            'exemption_id'     => $exemption,
            'inclusive'        => $inclusive,
            'rounding_method'  => self::config('rounding_method', 'half_up'),
            'precision'        => self::precision(),
        ];
    }

    /**
     * Aggregate multiple line calculations into document totals, with a
     * per-rate breakdown for reports/integration.
     *
     * @param array $lines each element: result of calculateLineTax() OR a raw
     *      line array ({taxable_amount, tax_status, tax_rate_id, ...}).
     * @return array { valid, net_total, tax_total, gross_total, rates, lines }
     */
    public static function calculateDocumentTax(array $lines, array $opts = []) {
        $results = [];
        $rateMap = [];
        $net = $tax = $gross = 0.0;

        foreach ($lines as $line) {
            $calc = (isset($line['total_amount']) && isset($line['tax_amount'])) || isset($line['net_amount'])
                ? $line                                   // already calculated
                : self::calculateLineTax($line['taxable_amount'] ?? 0, $line + $opts);
            $results[] = $calc;

            $net   += (float)$calc['net_amount'];
            $tax   += (float)$calc['tax_amount'];
            $gross += (float)$calc['total_amount'];

            $rid = (int)($calc['tax_rate_id'] ?? 0);
            if (!isset($rateMap[$rid])) {
                $rateMap[$rid] = [
                    'tax_rate_id'     => $calc['tax_rate_id'] ?? null,
                    'rate_percentage' => (float)($calc['rate_percentage'] ?? 0),
                    'rate_name'       => $calc['rate_name'] ?? '',
                    'basis'           => 0.0,
                    'tax'             => 0.0,
                    'lines'           => 0,
                ];
            }
            $rateMap[$rid]['basis'] += (float)$calc['net_amount'];
            $rateMap[$rid]['tax']   += (float)$calc['tax_amount'];
            $rateMap[$rid]['lines']++;
        }

        $net   = self::roundAmount($net);
        $tax   = self::roundAmount($tax);
        $gross = self::roundAmount($gross);

        return [
            'valid'       => true,
            'net_total'   => $net,
            'tax_total'   => $tax,
            'gross_total' => $gross,
            'rates'       => array_values($rateMap),
            'lines'       => $results,
        ];
    }

    // ================================================================= PERIODS

    public static function getPeriod($id) {
        if (!$id) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tax_periods WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Open period covering $date for $taxTypeId, else null. */
    public static function getPeriodForDate($taxTypeId, $date = null) {
        $date = $date ?: date('Y-m-d');
        if (!$taxTypeId) return null;
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM tax_periods
            WHERE tax_type_id = ? AND start_date <= ? AND end_date >= ? AND status <> 'closed'
            ORDER BY status = 'submitted', start_date DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$taxTypeId, $date, $date]);
        return $stmt->fetch() ?: null;
    }

    /** Any period covering the date for $taxTypeId — open, submitted or closed. */
    public static function getAnyPeriodForDate($taxTypeId, $date = null) {
        $date = $date ?: date('Y-m-d');
        if (!$taxTypeId) return null;
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM tax_periods
            WHERE tax_type_id = ? AND start_date <= ? AND end_date >= ?
            ORDER BY status = 'closed', status = 'submitted', start_date DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$taxTypeId, $date, $date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Ensure a cover period exists for the date (auto-creates a monthly period
     * named 'Month YYYY' when tax_auto_period is enabled).
     *
     * If a period already covers the date it is returned — but once that period
     * is CLOSED it stays authoritative: we must never shadow it with a freshly
     * auto-created open period, or the closed-period guard in recordDocumentTax()
     * would be silently bypassed and closed months could accept new transactions.
     */
    public static function ensurePeriodForDate($taxTypeId, $date = null) {
        $date = $date ?: date('Y-m-d');
        $period = self::getPeriodForDate($taxTypeId, $date);   // open/submitted first
        if ($period) return $period;
        $any = self::getAnyPeriodForDate($taxTypeId, $date);   // a closed cover must win
        if ($any) return $any;
        if (!self::config('auto_period', '1')) return null;

        $db = getDB();
        $start = date('Y-m-01', strtotime($date));
        $end   = date('Y-m-t', strtotime($date));
        $name  = date('F Y', strtotime($date));
        $stmt = $db->prepare("
            INSERT INTO tax_periods (tax_type_id, period_name, start_date, end_date, status)
            VALUES (?, ?, ?, ?, 'open')
        ");
        $stmt->execute([$taxTypeId, $name, $start, $end]);
        return self::getPeriod((int)$db->lastInsertId());
    }

    public static function periodIsClosed($taxPeriodId) {
        $p = self::getPeriod($taxPeriodId);
        return $p && $p['status'] === 'closed';
    }

    /**
     * Block normal modifications to closed tax periods.
     * @throws RuntimeException when the period is closed.
     */
    public static function assertPeriodOpen($taxPeriodId, $recordId = null) {
        if ($taxPeriodId && self::periodIsClosed($taxPeriodId)) {
            $p = self::getPeriod($taxPeriodId);
            throw new RuntimeException('Tax period "' . (string)($p['period_name'] ?? '') . '" is closed. Normal tax transactions cannot be modified. Create a tax adjustment or reversal instead.');
        }
    }

    // ============================================================ TRANSACTIONS

    /**
     * Record one or more tax_transactions rows for a source document.
     *
     * @param array $data required keys: source_module, source_record_id,
     *      source_document_no, transaction_date, tax_type_id, direction, created_by.
     *      optional: period_id, counterparty_type, counterparty_id, reference.
     *      lines: array of {taxable_amount, tax_amount, tax_rate, tax_rate_id,
     *      tax_group_id, tax_status, exemption_id}.
     *
     * @throws RuntimeException on invalid data or a closed period (unless the
     *      row is a reversal or an adjustment direction).
     */
    public static function recordDocumentTax(array $data) {
        $db = getDB();
        $module   = $data['source_module'] ?? '';
        $recordId = (int)($data['source_record_id'] ?? 0);
        $docNo    = $data['source_document_no'] ?? '';
        $date     = $data['transaction_date'] ?? date('Y-m-d');
        $typeId   = (int)($data['tax_type_id'] ?? 0);
        $direction= $data['direction'] ?? self::DIR_OUTPUT;
        $userId   = (int)($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
        $lines    = $data['lines'] ?? [];

        if (!$module || !$recordId || !$typeId) {
            throw new RuntimeException('Tax transaction requires source_module, source_record_id and tax_type_id.');
        }
        if (empty($lines)) return 0;

        // Period resolution + closed-period guard (adjustments/reversals exempt).
        $periodId = $data['period_id'] ?? null;
        if (!$periodId) {
            $period = self::ensurePeriodForDate($typeId, $date);
            $periodId = $period ? (int)$period['id'] : null;
        }
        $isAdjustmentOrReversal = $direction === self::DIR_ADJUSTMENT || !empty($data['is_reversal']);
        if (!$isAdjustmentOrReversal) {
            self::assertPeriodOpen($periodId);
        }

        $stmt = $db->prepare("
            INSERT INTO tax_transactions
                (tax_type_id, tax_period_id, source_module, source_record_id, source_document_no,
                 transaction_date, taxable_amount, tax_amount, tax_direction, tax_rate,
                 tax_rate_id, tax_group_id, tax_status, exemption_id,
                 counterparty_type, counterparty_id, status, reversal_of, reference, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NULL, ?, ?)
        ");

        $count = 0;
        foreach ($lines as $line) {
            // Negative amounts are allowed only for reversal rows.
            $stmt->execute([
                $typeId,
                $periodId ?: null,
                $module,
                $recordId,
                $docNo,
                $date,
                (float)($line['taxable_amount'] ?? 0),
                (float)($line['tax_amount'] ?? 0),
                $direction,
                (float)($line['tax_rate'] ?? 0),
                $line['tax_rate_id'] ?? null,
                $line['tax_group_id'] ?? null,
                $line['tax_status'] ?? self::TAXABLE,
                $line['exemption_id'] ?? null,
                $data['counterparty_type'] ?? 'none',
                $data['counterparty_id'] ?? null,
                $data['reference'] ?? '',
                $userId,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Reverse every active transaction of a source document by writing
     * negative mirror rows (status 'reversed', reversal_of set). Leaves the
     * originals intact so the audit trail is complete.
     *
     * @return array ['rows'=>n, 'tax'=>reverse tax sum]
     */
    public static function reverseDocumentTax($sourceModule, $sourceRecordId, $date, $userId, $reason = '') {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM tax_transactions
            WHERE source_module = ? AND source_record_id = ? AND status = 'active'
        ");
        $stmt->execute([$sourceModule, $sourceRecordId]);
        $rows = $stmt->fetchAll();
        if (!$rows) return ['rows' => 0, 'tax' => 0.0];

        $insert = $db->prepare("
            INSERT INTO tax_transactions
                (tax_type_id, tax_period_id, source_module, source_record_id, source_document_no,
                 transaction_date, taxable_amount, tax_amount, tax_direction, tax_rate,
                 tax_rate_id, tax_group_id, tax_status, exemption_id,
                 counterparty_type, counterparty_id, status, reversal_of, reference, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reversed', ?, ?, ?)
        ");

        $taxSum = 0.0;
        $rowsWritten = 0;
        foreach ($rows as $r) {
            $taxSum += (float)$r['tax_amount'];
            $insert->execute([
                $r['tax_type_id'],
                $r['tax_period_id'],
                $sourceModule,
                $sourceRecordId,
                $r['source_document_no'],
                $date ?: $r['transaction_date'],
                -(float)$r['taxable_amount'],
                -(float)$r['tax_amount'],
                $r['tax_direction'],
                $r['tax_rate'],
                $r['tax_rate_id'],
                $r['tax_group_id'],
                $r['tax_status'],
                $r['exemption_id'],
                $r['counterparty_type'],
                $r['counterparty_id'],
                (int)$r['id'],
                $reason ?: ('Reversal of ' . $r['source_document_no']),
                $userId,
            ]);
            $rowsWritten++;
        }
        return ['rows' => $rowsWritten, 'tax' => $taxSum];
    }

    public static function findSourceTransactions($sourceModule, $sourceRecordId) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT t.*, tp.status AS period_status, tp.period_name,
                   tt.name AS tax_type_name, r.rate_name
            FROM tax_transactions t
            JOIN tax_types tt ON tt.id = t.tax_type_id
            LEFT JOIN tax_periods tp ON tp.id = t.tax_period_id
            LEFT JOIN tax_rates r ON r.id = t.tax_rate_id
            WHERE t.source_module = ? AND t.source_record_id = ?
            ORDER BY t.id
        ");
        $stmt->execute([$sourceModule, $sourceRecordId]);
        return $stmt->fetchAll();
    }

    // ============================================================ GL ACCOUNTS

    /** GL account id backing a tax direction (output / input / adjustment). */
    public static function getTaxAccountId($direction) {
        switch ($direction) {
            case self::DIR_INPUT:  return (int)self::config('input_tax_account_id', 0);
            case self::DIR_ADJUSTMENT: return (int)self::config('adjustment_account_id', 0);
            case self::DIR_OUTPUT:
            default:               return (int)self::config('output_tax_account_id', 0);
        }
    }

    public static function getAccount($accountId) {
        if (!$accountId) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT c.*, g.group_name, g.nature FROM chart_of_accounts c LEFT JOIN account_groups g ON g.id = c.group_id WHERE c.id = ?");
        $stmt->execute([$accountId]);
        return $stmt->fetch() ?: null;
    }

    public static function accountDisplay($accountId) {
        $acct = self::getAccount($accountId);
        if (!$acct) return 'Unassigned';
        return $acct['account_code'] . ' - ' . $acct['account_name'];
    }

    /** Owner side of a source module (for filters/reports): customer/vendor/none. */
    public static function counterpartyOf($sourceModule) {
        switch ($sourceModule) {
            case self::SOURCE_INVOICE:
            case self::SOURCE_CREDIT_NOTE: return 'customer';
            case self::SOURCE_BILL:
            case self::SOURCE_DEBIT_NOTE:
            case self::SOURCE_EXPENSE:     return 'vendor';
            default:                       return 'none';
        }
    }
}