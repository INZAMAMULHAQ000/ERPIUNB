<?php
/**
 * AI F13 — Event Bus Service.
 *
 * Manages ERP event registration, emission, and subscription processing.
 * Provides a clean interface for modules to publish events that can
 * trigger AI automations.
 */
class AIEventBusService
{
    /**
     * Register all known ERP events at bootstrap.
     */
    public static function registerDefaultEvents(PDO $db): array
    {
        $events = [
            // Procurement events
            ['event_name' => 'procurement.requisition.created', 'module' => 'procurement', 'description' => 'A purchase requisition was created'],
            ['event_name' => 'procurement.requisition.approved', 'module' => 'procurement', 'description' => 'A purchase requisition was approved'],
            ['event_name' => 'procurement.requisition.rejected', 'module' => 'procurement', 'description' => 'A purchase requisition was rejected'],
            ['event_name' => 'procurement.requisition.submitted', 'module' => 'procurement', 'description' => 'A purchase requisition was submitted for approval'],
            ['event_name' => 'procurement.po.created', 'module' => 'procurement', 'description' => 'A purchase order was created'],
            ['event_name' => 'procurement.po.approved', 'module' => 'procurement', 'description' => 'A purchase order was approved'],
            ['event_name' => 'procurement.po.received', 'module' => 'procurement', 'description' => 'Goods were received against a purchase order'],
            ['event_name' => 'procurement.supplier.created', 'module' => 'procurement', 'description' => 'A new supplier was created'],
            ['event_name' => 'procurement.supplier.updated', 'module' => 'procurement', 'description' => 'A supplier record was updated'],
            ['event_name' => 'procurement.rfq.created', 'module' => 'procurement', 'description' => 'A request for quotation was created'],
            ['event_name' => 'procurement.rfq.response', 'module' => 'procurement', 'description' => 'A supplier responded to an RFQ'],

            // Finance events
            ['event_name' => 'finance.invoice.created', 'module' => 'finance', 'description' => 'An invoice was created'],
            ['event_name' => 'finance.invoice.paid', 'module' => 'finance', 'description' => 'An invoice was marked as paid'],
            ['event_name' => 'finance.payment.processed', 'module' => 'finance', 'description' => 'A payment was processed'],
            ['event_name' => 'finance.budget.threshold', 'module' => 'finance', 'description' => 'A budget threshold was reached'],

            // HR events
            ['event_name' => 'hr.leave.requested', 'module' => 'hr', 'description' => 'A leave request was submitted'],
            ['event_name' => 'hr.leave.approved', 'module' => 'hr', 'description' => 'A leave request was approved'],
            ['event_name' => 'hr.employee.onboarded', 'module' => 'hr', 'description' => 'A new employee was onboarded'],

            // Inventory events
            ['event_name' => 'inventory.stock.low', 'module' => 'inventory', 'description' => 'Stock level fell below threshold'],
            ['event_name' => 'inventory.stock.received', 'module' => 'inventory', 'description' => 'Stock was received into inventory'],

            // System events
            ['event_name' => 'system.user.created', 'module' => 'system', 'description' => 'A new user was created'],
            ['event_name' => 'system.user.role_changed', 'module' => 'system', 'description' => 'A user role was changed'],
        ];

        $registered = 0;
        foreach ($events as $event) {
            $result = AIAutomationSchedulerService::registerEvent(
                $db,
                $event['event_name'],
                $event['module'],
                $event['description']
            );
            if ($result['success']) $registered++;
        }

        return ['success' => true, 'registered' => $registered, 'total' => count($events)];
    }

    /**
     * Emit an event from any module.
     *
     * @param PDO $db Database connection
     * @param string $eventName Event name (e.g., 'procurement.requisition.created')
     * @param array $payload Event data (record_id, record_type, plus custom fields)
     * @param int|null $userId User who triggered the event
     * @return array ['success' => bool, 'triggered' => int]
     */
    public static function emit(PDO $db, string $eventName, array $payload, ?int $userId = null): array
    {
        return AIAutomationSchedulerService::emitEvent($db, $eventName, $payload, $userId);
    }

    /**
     * Convenience: emit with automatic user detection from session.
     */
    public static function emitFromSession(PDO $db, string $eventName, array $payload): array
    {
        $userId = $_SESSION['user_id'] ?? null;
        return self::emit($db, $eventName, $payload, $userId);
    }

    /**
     * Get all registered events with subscription counts.
     */
    public static function getEventsWithSubscriptions(PDO $db): array
    {
        $stmt = $db->prepare("
            SELECT er.*,
                   COUNT(es.id) as subscription_count
            FROM ai_event_registry er
            LEFT JOIN ai_event_subscriptions es ON er.event_name = es.event_name AND es.enabled = 1
            WHERE er.enabled = 1
            GROUP BY er.id
            ORDER BY er.module, er.event_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get recent events with processing status.
     */
    public static function getRecentEvents(PDO $db, int $limit = 50, ?string $module = null): array
    {
        $sql = "SELECT * FROM ai_event_log";
        $params = [];
        if ($module) {
            $sql .= " WHERE event_name LIKE ?";
            $params[] = $module . '.%';
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get event statistics for dashboard.
     */
    public static function getEventStats(PDO $db, int $hours = 24): array
    {
        $stats = [];

        // Total events in period
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_event_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hours]);
        $stats['total_events'] = (int) $stmt->fetchColumn();

        // Events by module
        $stmt = $db->prepare("
            SELECT SUBSTRING_INDEX(event_name, '.', 1) as module, COUNT(*) as count
            FROM ai_event_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY module ORDER BY count DESC
        ");
        $stmt->execute([$hours]);
        $stats['by_module'] = $stmt->fetchAll();

        // Triggered automations
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_event_log
            WHERE processed = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hours]);
        $stats['triggered'] = (int) $stmt->fetchColumn();

        // Failed triggers
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_event_log
            WHERE processed = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hours]);
        $stats['failed'] = (int) $stmt->fetchColumn();

        return $stats;
    }
}
