<?php
/**
 * Fixed Assets Depreciation Service
 * 
 * Centralized service for all fixed asset depreciation calculations and operations.
 * Handles different depreciation methods and integration with the posting engine.
 */

require_once __DIR__ . '/../../../config/database.php';

class DepreciationService {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Calculate depreciation for a specific asset and period
     * 
     * @param int $financialAssetId ID of the financial asset record
     * @param string $periodDate Date for which to calculate depreciation (YYYY-MM-DD)
     * @return array Depreciation calculation details
     */
    public function calculateDepreciation($financialAssetId, $periodDate) {
        // Get asset financial details
        $asset = $this->getFinancialAsset($financialAssetId);
        if (!$asset) {
            throw new Exception("Financial asset not found");
        }

        // Get depreciation method
        $method = $this->getDepreciationMethod($asset['depreciation_method_id']);
        if (!$method) {
            throw new Exception("Depreciation method not found");
        }

        // Calculate based on method
        switch ($method['method_code']) {
            case 'STRAIGHT_LINE':
                return $this->calculateStraightLine($asset, $periodDate);

            case 'DECLINING_BALANCE':
                return $this->calculateDecliningBalance($asset, $periodDate);

            case 'DOUBLE_DECLINING':
                return $this->calculateDoubleDeclining($asset, $periodDate);

            default:
                throw new Exception("Unsupported depreciation method: " . $method['method_code']);
        }
    }

    /**
     * Generate complete depreciation schedule for an asset
     * 
     * @param int $financialAssetId ID of the financial asset record
     * @return array Complete depreciation schedule
     */
    public function generateSchedule($financialAssetId) {
        $asset = $this->getFinancialAsset($financialAssetId);
        if (!$asset) {
            throw new Exception("Financial asset not found");
        }

        $method = $this->getDepreciationMethod($asset['depreciation_method_id']);
        if (!$method) {
            throw new Exception("Depreciation method not found");
        }

        $schedule = [];
        $currentDate = new DateTime($asset['depreciation_start_date']);
        $endDate = new DateTime($asset['capitalization_date']);
        $endDate->modify('+' . $asset['useful_life_months'] . ' months');

        $openingNBV = $asset['capitalized_cost'];
        $accumulatedDepreciation = 0;

        while ($currentDate <= $endDate) {
            $periodDate = $currentDate->format('Y-m-d');

            // Skip if already posted for this period
            if ($this->isDepreciationPosted($financialAssetId, $periodDate)) {
                $currentDate->modify('+1 month');
                continue;
            }

            // Calculate depreciation for this period
            $depreciation = $this->calculateDepreciation($financialAssetId, $periodDate);

            // Update accumulated depreciation and NBV
            $accumulatedDepreciation += $depreciation['amount'];
            $closingNBV = $openingNBV - $depreciation['amount'];

            // Add to schedule
            $schedule[] = [
                'period_date' => $periodDate,
                'opening_book_value' => $openingNBV,
                'depreciation_amount' => $depreciation['amount'],
                'accumulated_depreciation' => $accumulatedDepreciation,
                'closing_book_value' => $closingNBV,
                'status' => 'draft'
            ];

            // Update for next iteration
            $openingNBV = $closingNBV;
            $currentDate->modify('+1 month');
        }

        return $schedule;
    }

    /**
     * Post depreciation for a period and create journal entries
     * 
     * @param array $depreciationData Depreciation data to post
     * @param int $userId ID of user performing the action
     * @return array Result with journal entry ID and success status
     */
    public function postDepreciation($depreciationData, $userId) {
        $this->db->beginTransaction();

        try {
            // Get asset details
            $asset = $this->getFinancialAsset($depreciationData['financial_asset_id']);
            if (!$asset) {
                throw new Exception("Financial asset not found");
            }

            // Get category details
            $category = $this->getAssetCategory($asset['asset_category_id']);
            if (!$category) {
                throw new Exception("Asset category not found");
            }

            // Check if already posted
            if ($this->isDepreciationPosted($depreciationData['financial_asset_id'], $depreciationData['period_date'])) {
                throw new Exception("Depreciation already posted for this period");
            }

            // Create journal entry
            $journalEntryId = $this->createDepreciationJournalEntry(
                $depreciationData,
                $category,
                $userId
            );

            // Update depreciation schedule record
            $this->updateDepreciationSchedule(
                $depreciationData['financial_asset_id'],
                $depreciationData['period_date'],
                $journalEntryId
            );

            // Update accumulated depreciation and NBV in asset record
            $this->updateAssetDepreciation(
                $depreciationData['financial_asset_id'],
                $depreciationData['depreciation_amount']
            );

            // Record in history
            $this->recordAssetHistory(
                $depreciationData['financial_asset_id'],
                'depreciation',
                $depreciationData,
                null,
                $userId
            );

            $this->db->commit();

            return [
                'success' => true,
                'journal_entry_id' => $journalEntryId,
                'message' => 'Depreciation posted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error posting depreciation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate net book value of an asset
     * 
     * @param int $financialAssetId ID of the financial asset record
     * @return float Net book value
     */
    public function calculateBookValue($financialAssetId) {
        $asset = $this->getFinancialAsset($financialAssetId);
        if (!$asset) {
            return 0;
        }

        // Net Book Value = Capitalized Cost - Accumulated Depreciation - Impairment
        return $asset['capitalized_cost'] - $asset['accumulated_depreciation'] - $asset['impairment_amount'];
    }

    /**
     * Calculate gain/loss on asset disposal
     * 
     * @param array $asset Asset financial data
     * @param float $disposalValue Proceeds from disposal
     * @return float Gain (positive) or loss (negative)
     */
    public function calculateGainLoss($asset, $disposalValue) {
        $netBookValue = $this->calculateBookValue($asset['id']);
        return $disposalValue - $netBookValue;
    }

    /**
     * Record asset impairment
     * 
     * @param array $impairmentData Impairment data
     * @param int $userId ID of user performing the action
     * @return array Result with journal entry ID and success status
     */
    public function recordImpairment($impairmentData, $userId) {
        $this->db->beginTransaction();

        try {
            // Get asset details
            $asset = $this->getFinancialAsset($impairmentData['financial_asset_id']);
            if (!$asset) {
                throw new Exception("Financial asset not found");
            }

            // Get category details
            $category = $this->getAssetCategory($asset['asset_category_id']);
            if (!$category) {
                throw new Exception("Asset category not found");
            }

            // Calculate impairment loss
            $impairmentLoss = $asset['net_book_value'] - $impairmentData['recoverable_amount'];
            if ($impairmentLoss <= 0) {
                throw new Exception("No impairment loss - book value is less than recoverable amount");
            }

            // Create journal entry for impairment
            $journalEntryId = $this->createImpairmentJournalEntry(
                $impairmentData,
                $impairmentLoss,
                $category,
                $userId
            );

            // Update asset record
            $this->updateAssetImpairment(
                $impairmentData['financial_asset_id'],
                $impairmentLoss,
                $impairmentData['recoverable_amount']
            );

            // Record impairment
            $this->recordImpairmentTransaction(
                $impairmentData,
                $impairmentLoss,
                $journalEntryId,
                $userId
            );

            // Record in history
            $this->recordAssetHistory(
                $impairmentData['financial_asset_id'],
                'impairment',
                $impairmentData,
                null,
                $userId
            );

            $this->db->commit();

            return [
                'success' => true,
                'journal_entry_id' => $journalEntryId,
                'impairment_loss' => $impairmentLoss,
                'message' => 'Impairment recorded successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error recording impairment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record asset disposal
     * 
     * @param array $disposalData Disposal data
     * @param int $userId ID of user performing the action
     * @return array Result with journal entry ID and success status
     */
    public function recordDisposal($disposalData, $userId) {
        $this->db->beginTransaction();

        try {
            // Get asset details
            $asset = $this->getFinancialAsset($disposalData['financial_asset_id']);
            if (!$asset) {
                throw new Exception("Financial asset not found");
            }

            // Get category details
            $category = $this->getAssetCategory($asset['asset_category_id']);
            if (!$category) {
                throw new Exception("Asset category not found");
            }

            // Calculate gain/loss
            $gainLoss = $this->calculateGainLoss($asset, $disposalData['disposal_value']);

            // Create journal entry for disposal
            $journalEntryId = $this->createDisposalJournalEntry(
                $disposalData,
                $gainLoss,
                $category,
                $userId
            );

            // Update asset record
            $this->updateAssetDisposal(
                $disposalData['financial_asset_id'],
                $disposalData['disposal_value'],
                $gainLoss
            );

            // Record disposal
            $this->recordDisposalTransaction(
                $disposalData,
                $gainLoss,
                $journalEntryId,
                $userId
            );

            // Record in history
            $this->recordAssetHistory(
                $disposalData['financial_asset_id'],
                'disposal',
                $disposalData,
                null,
                $userId
            );

            $this->db->commit();

            return [
                'success' => true,
                'journal_entry_id' => $journalEntryId,
                'gain_loss' => $gainLoss,
                'message' => 'Asset disposal recorded successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error recording disposal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record asset transfer
     * 
     * @param array $transferData Transfer data
     * @param int $userId ID of user performing the action
     * @return array Result with journal entry ID and success status
     */
    public function transferAsset($transferData, $userId) {
        $this->db->beginTransaction();

        try {
            // Get asset details
            $asset = $this->getFinancialAsset($transferData['financial_asset_id']);
            if (!$asset) {
                throw new Exception("Financial asset not found");
            }

            // Create journal entry for transfer
            $journalEntryId = $this->createTransferJournalEntry(
                $transferData,
                $userId
            );

            // Record transfer
            $this->recordTransferTransaction(
                $transferData,
                $journalEntryId,
                $userId
            );

            // Record in history
            $this->recordAssetHistory(
                $transferData['financial_asset_id'],
                'transfer',
                $transferData,
                null,
                $userId
            );

            $this->db->commit();

            return [
                'success' => true,
                'journal_entry_id' => $journalEntryId,
                'message' => 'Asset transfer recorded successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error recording transfer: ' . $e->getMessage()
            ];
        }
    }

    // Private helper methods

    /**
     * Get financial asset details
     */
    private function getFinancialAsset($financialAssetId) {
        $stmt = $this->db->prepare("
            SELECT * FROM fixed_asset_financials 
            WHERE id = ?
        ");
        $stmt->execute([$financialAssetId]);
        return $stmt->fetch();
    }

    /**
     * Get depreciation method details
     */
    private function getDepreciationMethod($depreciationMethodId) {
        $stmt = $this->db->prepare("
            SELECT * FROM depreciation_methods 
            WHERE id = ?
        ");
        $stmt->execute([$depreciationMethodId]);
        return $stmt->fetch();
    }

    /**
     * Get asset category details
     */
    private function getAssetCategory($assetCategoryId) {
        $stmt = $this->db->prepare("
            SELECT * FROM fixed_asset_categories 
            WHERE id = ?
        ");
        $stmt->execute([$assetCategoryId]);
        return $stmt->fetch();
    }

    /**
     * Check if depreciation is already posted for an asset and period
     */
    private function isDepreciationPosted($financialAssetId, $periodDate) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM asset_depreciation_schedules 
            WHERE financial_asset_id = ? AND period_date = ? AND status = 'posted'
        ");
        $stmt->execute([$financialAssetId, $periodDate]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Calculate straight-line depreciation
     */
    private function calculateStraightLine($asset, $periodDate) {
        // Depreciable Amount = Acquisition Cost - Residual Value
        $depreciableAmount = $asset['capitalized_cost'] - $asset['residual_value'];

        // Monthly Depreciation = Depreciable Amount / Useful Life in Months
        $monthlyDepreciation = $depreciableAmount / $asset['useful_life_months'];

        // Check if we're in the first year (partial depreciation)
        $capitalizationDate = new DateTime($asset['capitalization_date']);
        $depreciationDate = new DateTime($periodDate);
        $depreciationStart = new DateTime($asset['depreciation_start_date']);

        // If period date is before depreciation start date, no depreciation
        if ($depreciationDate < $depreciationStart) {
            return [
                'amount' => 0,
                'method' => 'STRAIGHT_LINE',
                'period' => $periodDate,
                'note' => 'Before depreciation start date'
            ];
        }

        // If period date is after useful life, no depreciation
        $usefulLifeEnd = (clone $depreciationStart)->modify('+' . $asset['useful_life_months'] . ' months');
        if ($depreciationDate > $usefulLifeEnd) {
            return [
                'amount' => 0,
                'method' => 'STRAIGHT_LINE',
                'period' => $periodDate,
                'note' => 'After useful life'
            ];
        }

        return [
            'amount' => $monthlyDepreciation,
            'method' => 'STRAIGHT_LINE',
            'period' => $periodDate,
            'note' => 'Straight-line depreciation'
        ];
    }

    /**
     * Calculate declining balance depreciation
     */
    private function calculateDecliningBalance($asset, $periodDate) {
        // Annual depreciation rate (e.g., 20% for 5-year life)
        $annualRate = 1 - (pow($asset['residual_value'] / $asset['capitalized_cost'], 1 / ($asset['useful_life_months'] / 12)));

        // Monthly depreciation rate
        $monthlyRate = pow(1 + $annualRate, 1/12) - 1;

        // Calculate opening NBV
        $openingNBV = $this->calculateBookValue($asset['id']);

        // Depreciation for the month
        $depreciation = $openingNBV * $monthlyRate;

        // Ensure we don't depreciate below residual value
        $remainingValue = $openingNBV - $depreciation;
        if ($remainingValue < $asset['residual_value']) {
            $depreciation = $openingNBV - $asset['residual_value'];
        }

        return [
            'amount' => $depreciation,
            'method' => 'DECLINING_BALANCE',
            'period' => $periodDate,
            'note' => 'Declining balance depreciation'
        ];
    }

    /**
     * Calculate double declining balance depreciation
     */
    private function calculateDoubleDeclining($asset, $periodDate) {
        // Straight-line rate (1 / useful life in years)
        $straightLineRate = 12 / $asset['useful_life_months'];

        // Double declining rate
        $doubleDecliningRate = 2 * $straightLineRate;

        // Calculate opening NBV
        $openingNBV = $this->calculateBookValue($asset['id']);

        // Depreciation for the month
        $depreciation = $openingNBV * $doubleDecliningRate;

        // Ensure we don't depreciate below residual value
        $remainingValue = $openingNBV - $depreciation;
        if ($remainingValue < $asset['residual_value']) {
            $depreciation = $openingNBV - $asset['residual_value'];
        }

        return [
            'amount' => $depreciation,
            'method' => 'DOUBLE_DECLINING',
            'period' => $periodDate,
            'note' => 'Double declining balance depreciation'
        ];
    }

    /**
     * Create journal entry for depreciation
     */
    private function createDepreciationJournalEntry($depreciationData, $category, $userId) {
        // Get posting engine
        require_once __DIR__ . '/../../finance/PostingEngine.php';

        // Create journal entry
        $journalData = [
            'reference' => 'DEP-' . date('Ym') . '-' . $depreciationData['financial_asset_id'],
            'date' => $depreciationData['period_date'],
            'description' => 'Depreciation Expense for Asset #' . $depreciationData['financial_asset_id'] . ' - ' . date('F Y', strtotime($depreciationData['period_date'])),
            'module' => 'fixed_assets',
            'status' => 'posted',
            'created_by' => $userId
        ];

        // Create debit entry for depreciation expense
        $debit = [
            'account_id' => $category['depreciation_expense_gl_account'],
            'debit' => $depreciationData['depreciation_amount'],
            'credit' => 0,
            'narration' => 'Depreciation expense for Asset #' . $depreciationData['financial_asset_id']
        ];

        // Create credit entry for accumulated depreciation
        $credit = [
            'account_id' => $category['accumulated_depreciation_gl_account'],
            'debit' => 0,
            'credit' => $depreciationData['depreciation_amount'],
            'narration' => 'Accumulated depreciation for Asset #' . $depreciationData['financial_asset_id']
        ];

        // Post the journal entry
        $postingEngine = new PostingEngine();
        $result = $postingEngine->postJournalEntry($journalData, [$debit, $credit]);

        return $result['journal_entry_id'] ?? null;
    }

    /**
     * Update depreciation schedule record
     */
    private function updateDepreciationSchedule($financialAssetId, $periodDate, $journalEntryId) {
        $stmt = $this->db->prepare("
            UPDATE asset_depreciation_schedules 
            SET status = 'posted', journal_entry_id = ?, posted_at = NOW()
            WHERE financial_asset_id = ? AND period_date = ?
        ");
        $stmt->execute([$journalEntryId, $financialAssetId, $periodDate]);
    }

    /**
     * Update accumulated depreciation and NBV in asset record
     */
    private function updateAssetDepreciation($financialAssetId, $depreciationAmount) {
        $stmt = $this->db->prepare("
            UPDATE fixed_asset_financials 
            SET accumulated_depreciation = accumulated_depreciation + ?, 
                net_book_value = net_book_value - ?
            WHERE id = ?
        ");
        $stmt->execute([$depreciationAmount, $depreciationAmount, $financialAssetId]);
    }

    /**
     * Record asset history
     */
    private function recordAssetHistory($financialAssetId, $eventType, $newValues, $oldValues, $userId) {
        $stmt = $this->db->prepare("
            INSERT INTO fixed_asset_history 
            (financial_asset_id, event_type, event_date, old_values, new_values, created_by)
            VALUES (?, ?, CURDATE(), ?, ?, ?)
        ");
        $stmt->execute([
            $financialAssetId,
            $eventType,
            json_encode($oldValues),
            json_encode($newValues),
            $userId
        ]);
    }

    /**
     * Create journal entry for impairment
     */
    private function createImpairmentJournalEntry($impairmentData, $impairmentLoss, $category, $userId) {
        // Get posting engine
        require_once __DIR__ . '/../../finance/PostingEngine.php';

        // Create journal entry
        $journalData = [
            'reference' => 'IMP-' . date('Ym') . '-' . $impairmentData['financial_asset_id'],
            'date' => $impairmentData['impairment_date'],
            'description' => 'Impairment Loss for Asset #' . $impairmentData['financial_asset_id'],
            'module' => 'fixed_assets',
            'status' => 'posted',
            'created_by' => $userId
        ];

        // Create debit entry for impairment expense
        $debit = [
            'account_id' => $category['depreciation_expense_gl_account'],
            'debit' => $impairmentLoss,
            'credit' => 0,
            'narration' => 'Impairment loss for Asset #' . $impairmentData['financial_asset_id']
        ];

        // Create credit entry for accumulated impairment
        $credit = [
            'account_id' => $category['accumulated_depreciation_gl_account'],
            'debit' => 0,
            'credit' => $impairmentLoss,
            'narration' => 'Accumulated impairment for Asset #' . $impairmentData['financial_asset_id']
        ];

        // Post the journal entry
        $postingEngine = new PostingEngine();
        $result = $postingEngine->postJournalEntry($journalData, [$debit, $credit]);

        return $result['journal_entry_id'] ?? null;
    }

    /**
     * Update asset impairment
     */
    private function updateAssetImpairment($financialAssetId, $impairmentLoss, $recoverableAmount) {
        $stmt = $this->db->prepare("
            UPDATE fixed_asset_financials 
            SET impairment_amount = impairment_amount + ?, 
                net_book_value = ?
            WHERE id = ?
        ");
        $stmt->execute([$impairmentLoss, $recoverableAmount, $financialAssetId]);
    }

    /**
     * Record impairment transaction
     */
    private function recordImpairmentTransaction($impairmentData, $impairmentLoss, $journalEntryId, $userId) {
        $stmt = $this->db->prepare("
            INSERT INTO asset_impairments 
            (financial_asset_id, impairment_date, book_value_before_impairment, impairment_amount, 
             recoverable_amount, new_book_value, journal_entry_id, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $impairmentData['financial_asset_id'],
            $impairmentData['impairment_date'],
            $impairmentData['book_value_before_impairment'],
            $impairmentLoss,
            $impairmentData['recoverable_amount'],
            $impairmentData['recoverable_amount'],
            $journalEntryId,
            $impairmentData['notes'],
            $userId
        ]);
    }

    /**
     * Create journal entry for disposal
     */
    private function createDisposalJournalEntry($disposalData, $gainLoss, $category, $userId) {
        // Get posting engine
        require_once __DIR__ . '/../../finance/PostingEngine.php';

        // Create journal entry
        $journalData = [
            'reference' => 'DIS-' . date('Ym') . '-' . $disposalData['financial_asset_id'],
            'date' => $disposalData['disposal_date'],
            'description' => 'Disposal of Asset #' . $disposalData['financial_asset_id'] . 
                           ' (' . $disposalData['disposal_method'] . ')',
            'module' => 'fixed_assets',
            'status' => 'posted',
            'created_by' => $userId
        ];

        // Remove asset cost
        $removeAsset = [
            'account_id' => $category['asset_gl_account'],
            'debit' => 0,
            'credit' => $disposalData['net_book_value'],
            'narration' => 'Remove asset cost for disposal of Asset #' . $disposalData['financial_asset_id']
        ];

        // Remove accumulated depreciation
        $removeAccumDep = [
            'account_id' => $category['accumulated_depreciation_gl_account'],
            'debit' => $disposalData['accumulated_depreciation'],
            'credit' => 0,
            'narration' => 'Remove accumulated depreciation for disposal of Asset #' . $disposalData['financial_asset_id']
        ];

        // Record proceeds
        $proceeds = [
            'account_id' => getClearingAccountId(), // This should be implemented based on your chart of accounts
            'debit' => $disposalData['disposal_value'],
            'credit' => 0,
            'narration' => 'Proceeds from disposal of Asset #' . $disposalData['financial_asset_id']
        ];

        // Record gain/loss
        if ($gainLoss > 0) {
            // Gain on disposal
            $gainLossEntry = [
                'account_id' => getGainAccountId(), // This should be implemented based on your chart of accounts
                'debit' => 0,
                'credit' => $gainLoss,
                'narration' => 'Gain on disposal of Asset #' . $disposalData['financial_asset_id']
            ];
        } else {
            // Loss on disposal
            $gainLossEntry = [
                'account_id' => getLossAccountId(), // This should be implemented based on your chart of accounts
                'debit' => abs($gainLoss),
                'credit' => 0,
                'narration' => 'Loss on disposal of Asset #' . $disposalData['financial_asset_id']
            ];
        }

        // Post the journal entry
        $postingEngine = new PostingEngine();
        $entries = [$removeAsset, $removeAccumDep, $proceeds, $gainLossEntry];
        $result = $postingEngine->postJournalEntry($journalData, $entries);

        return $result['journal_entry_id'] ?? null;
    }

    /**
     * Update asset disposal
     */
    private function updateAssetDisposal($financialAssetId, $disposalValue, $gainLoss) {
        $stmt = $this->db->prepare("
            UPDATE fixed_asset_financials 
            SET status = 'disposed', net_book_value = 0
            WHERE id = ?
        ");
        $stmt->execute([$financialAssetId]);
    }

    /**
     * Record disposal transaction
     */
    private function recordDisposalTransaction($disposalData, $gainLoss, $journalEntryId, $userId) {
        $stmt = $this->db->prepare("
            INSERT INTO asset_disposals 
            (financial_asset_id, disposal_date, disposal_method, disposal_value, gain_loss, 
             journal_entry_id, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $disposalData['financial_asset_id'],
            $disposalData['disposal_date'],
            $disposalData['disposal_method'],
            $disposalData['disposal_value'],
            $gainLoss,
            $journalEntryId,
            $disposalData['notes'],
            $userId
        ]);
    }

    /**
     * Create journal entry for transfer
     */
    private function createTransferJournalEntry($transferData, $userId) {
        // Get posting engine
        require_once __DIR__ . '/../../finance/PostingEngine.php';

        // Create journal entry
        $journalData = [
            'reference' => 'TRF-' . date('Ym') . '-' . $transferData['financial_asset_id'],
            'date' => $transferData['transfer_date'],
            'description' => 'Transfer of Asset #' . $transferData['financial_asset_id'] . 
                           ' from ' . $transferData['from_department_id'] . 
                           ' to ' . $transferData['to_department_id'],
            'module' => 'fixed_assets',
            'status' => 'posted',
            'created_by' => $userId
        ];

        // For internal transfers, we may need to create offsetting entries
        // depending on your accounting policy for transfers

        // Post the journal entry
        $postingEngine = new PostingEngine();
        $result = $postingEngine->postJournalEntry($journalData, []);

        return $result['journal_entry_id'] ?? null;
    }

    /**
     * Record transfer transaction
     */
    private function recordTransferTransaction($transferData, $journalEntryId, $userId) {
        $stmt = $this->db->prepare("
            INSERT INTO asset_transfers 
            (financial_asset_id, transfer_date, from_department_id, to_department_id,
             from_cost_center_id, to_cost_center_id, from_location_id, to_location_id,
             transfer_reason, journal_entry_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $transferData['financial_asset_id'],
            $transferData['transfer_date'],
            $transferData['from_department_id'],
            $transferData['to_department_id'],
            $transferData['from_cost_center_id'],
            $transferData['to_cost_center_id'],
            $transferData['from_location_id'],
            $transferData['to_location_id'],
            $transferData['transfer_reason'],
            $journalEntryId,
            $userId
        ]);
    }
}
