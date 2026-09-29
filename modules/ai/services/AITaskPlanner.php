<?php
/**
 * AI F10 — Task Planner.
 *
 * Generates structured task plans from user objectives.
 * Plans are proposals — validated server-side before execution.
 */

class AITaskPlanner
{
    /**
     * Build a task plan from a user objective.
     * Returns a structured plan that can be validated and executed.
     *
     * @param string $objective The user's task objective
     * @param array $availableTools Available read tools
     * @param array $availableActions Available write actions
     * @return array{success: bool, plan: array|null, error: string|null}
     */
    public static function buildPlan(string $objective, array $availableTools, array $availableActions): array
    {
        $objective = trim($objective);
        if ($objective === '') {
            return ['success' => false, 'plan' => null, 'error' => 'Objective is required.'];
        }

        // Analyze objective and build plan
        $steps = [];
        $stepNum = 1;

        $lowerObj = strtolower($objective);

        // ── Procurement-related tasks ──

        if (self::matchesAny($lowerObj, ['purchase requisition', 'pr', 'requisition'])) {
            if (self::matchesAny($lowerObj, ['pending', 'approval', 'review'])) {
                $steps[] = self::readStep($stepNum++, 'Search pending purchase requisitions', 'procurement.search_purchase_requisitions', ['status' => 'pending_approval']);
            } elseif (self::matchesAny($lowerObj, ['approved'])) {
                $steps[] = self::readStep($stepNum++, 'Search approved purchase requisitions', 'procurement.search_purchase_requisitions', ['status' => 'approved']);
            } else {
                $steps[] = self::readStep($stepNum++, 'Search purchase requisitions', 'procurement.search_purchase_requisitions', []);
            }
            $steps[] = self::reasoningStep($stepNum++, 'Analyze and summarize requisition data');
        }

        if (self::matchesAny($lowerObj, ['purchase order', 'po ']) && !self::matchesAny($lowerObj, ['requisition'])) {
            if (self::matchesAny($lowerObj, ['pending', 'draft'])) {
                $steps[] = self::readStep($stepNum++, 'Search pending purchase orders', 'procurement.search_purchase_orders', ['status' => 'draft']);
            } else {
                $steps[] = self::readStep($stepNum++, 'Search purchase orders', 'procurement.search_purchase_orders', []);
            }
            $steps[] = self::reasoningStep($stepNum++, 'Analyze and summarize order data');
        }

        if (self::matchesAny($lowerObj, ['supplier', 'vendor'])) {
            if (self::matchesAny($lowerObj, ['create', 'add', 'new'])) {
                // This is an action — will require confirmation
                $params = self::extractSupplierParams($lowerObj);
                $steps[] = self::actionStep($stepNum++, 'Create supplier', 'procurement.create_supplier', $params);
            } else {
                $searchQuery = self::extractSearchQuery($lowerObj, ['supplier', 'vendor']);
                $steps[] = self::readStep($stepNum++, 'Search suppliers', 'procurement.search_vendors', ['query' => $searchQuery ?: '']);
                $steps[] = self::reasoningStep($stepNum++, 'Summarize supplier information');
            }
        }

        // ── Generic search/analysis tasks ──

        if (empty($steps)) {
            if (self::matchesAny($lowerObj, ['search', 'find', 'list', 'show'])) {
                $steps[] = self::reasoningStep($stepNum++, 'Analyze request and determine available data sources');
                $steps[] = self::reasoningStep($stepNum++, 'Generate summary based on available information');
            } else {
                $steps[] = self::reasoningStep($stepNum++, 'Analyze the request');
                $steps[] = self::reasoningStep($stepNum++, 'Generate response');
            }
        }

        // Always add a final summary step
        if (end($steps)['type'] !== 'reasoning') {
            $steps[] = self::reasoningStep($stepNum++, 'Generate final summary for user');
        }

        $plan = [
            'title' => self::generateTitle($objective),
            'description' => $objective,
            'steps' => $steps,
        ];

        return ['success' => true, 'plan' => $plan, 'error' => null];
    }

    /**
     * Format plan for AI system prompt.
     */
    public static function formatPlanForAI(array $plan): string
    {
        $lines = ["Task Plan: {$plan['title']}"];
        $lines[] = '';
        foreach ($plan['steps'] as $step) {
            $type = ucfirst($step['type']);
            $status = $step['type'] === 'action' ? ' [REQUIRES CONFIRMATION]' : '';
            $lines[] .= "{$step['step']}. [{$type}] {$step['title']}{$status}";
        }
        return implode("\n", $lines);
    }

    // ── Helpers ──

    private static function readStep(int $num, string $title, string $toolId, array $input): array
    {
        return [
            'step' => $num,
            'type' => 'read',
            'title' => $title,
            'tool_id' => $toolId,
            'input' => $input,
            'purpose' => $title,
        ];
    }

    private static function actionStep(int $num, string $title, string $actionId, array $input): array
    {
        return [
            'step' => $num,
            'type' => 'action',
            'title' => $title,
            'action_id' => $actionId,
            'input' => $input,
            'purpose' => $title,
        ];
    }

    private static function reasoningStep(int $num, string $title): array
    {
        return [
            'step' => $num,
            'type' => 'reasoning',
            'title' => $title,
            'purpose' => $title,
        ];
    }

    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) return true;
        }
        return false;
    }

    private static function extractSearchQuery(string $objective, array $excludeWords): string
    {
        $words = explode(' ', $objective);
        $query = [];
        foreach ($words as $word) {
            if (!in_array(strtolower($word), $excludeWords) && strlen($word) > 2) {
                $query[] = $word;
            }
        }
        return implode(' ', array_slice($query, 0, 3));
    }

    private static function extractSupplierParams(string $objective): array
    {
        $params = [];

        // Try to extract supplier name from common patterns
        if (preg_match('/called?\s+(.+?)(?:\s+called|\s+with|\s+from|\s+\.|$)/i', $objective, $m)) {
            $params['vendor_name'] = trim($m[1]);
        } elseif (preg_match('/named?\s+(.+?)(?:\s+named|\s+with|\s+from|\s+\.|$)/i', $objective, $m)) {
            $params['vendor_name'] = trim($m[1]);
        }

        return $params;
    }

    private static function generateTitle(string $objective): string
    {
        $title = ucfirst(substr($objective, 0, 100));
        if (strlen($objective) > 100) $title .= '...';
        return $title;
    }
}
