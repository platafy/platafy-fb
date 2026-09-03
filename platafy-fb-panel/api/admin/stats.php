<?php
/**
 * PLATAFY FB - API Admin: Estatísticas Dashboard
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/license_utils.php';

requireAdmin();
checkExpiredLicenses();

try {
    $pdo = db();
    
    // Contadores
    $total = $pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active'")->fetchColumn();
    $expired = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'expired'")->fetchColumn();
    $revoked = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'revoked'")->fetchColumn();
    $inactive = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'inactive'")->fetchColumn();
    
    // Receita mensal (últimos 30 dias)
    $revenueStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'approved' AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $monthlyRevenue = $revenueStmt->fetchColumn();
    
    // Receita por mês (últimos 6 meses)
    $revenueByMonth = $pdo->query("
        SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
        FROM payments WHERE status = 'approved' AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
    
    // Licenças por plano
    $byPlan = $pdo->query("
        SELECT plan_type, COUNT(*) as count 
        FROM licenses WHERE status = 'active'
        GROUP BY plan_type
    ")->fetchAll();
    
    // Últimas 5 atividades
    $recentLogs = $pdo->query("
        SELECT al.*, l.license_key, l.client_name
        FROM activity_logs al
        LEFT JOIN licenses l ON al.license_id = l.id
        ORDER BY al.created_at DESC LIMIT 5
    ")->fetchAll();
    
    // Licenças prestes a expirar (próximos 7 dias)
    $expiringSoon = $pdo->query("
        SELECT * FROM licenses 
        WHERE status = 'active' AND expires_at IS NOT NULL 
        AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        ORDER BY expires_at ASC LIMIT 10
    ")->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$total,
            'active' => (int)$active,
            'expired' => (int)$expired,
            'revoked' => (int)$revoked,
            'inactive' => (int)$inactive,
            'monthly_revenue' => round((float)$monthlyRevenue, 2)
        ],
        'revenue_by_month' => $revenueByMonth,
        'licenses_by_plan' => $byPlan,
        'recent_activity' => $recentLogs,
        'expiring_soon' => $expiringSoon
    ]);
    
} catch (Exception $e) {
    error_log("[PLATAFY Stats] Error: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao carregar estatísticas']);
}
